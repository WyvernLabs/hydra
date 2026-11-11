<?php

namespace Pterodactyl\Tests\Integration\Http\Controllers\Admin;

use Pterodactyl\Models\User;
use Pterodactyl\Models\AdminRole;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\HydraKbPost;
use Pterodactyl\Models\HydraKbMedia;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\HydraKbCategory;
use Pterodactyl\Services\Hydra\KnowledgebaseService;
use Pterodactyl\Tests\Integration\Http\HttpTestCase;

/**
 * Hydra: knowledgebase addon (admin area and the page for users).
 */
class KnowledgebaseTest extends HttpTestCase
{
    // 1×1 GIF.
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function setUp(): void
    {
        parent::setUp();

        if (!defined('LARAVEL_START')) {
            define('LARAVEL_START', microtime(true));
        }
    }

    public function tearDown(): void
    {
        HydraKbMedia::query()->get()->each(fn (HydraKbMedia $media) => Storage::disk('local')->delete($media->path()));
        HydraKbMedia::query()->delete();
        HydraKbPost::query()->delete();
        HydraKbCategory::query()->whereNotNull('parent_id')->delete();
        HydraKbCategory::query()->delete();

        parent::tearDown();
    }

    private function enable(bool $public = false): void
    {
        config()->set('hydra.addons.knowledgebase.enabled', true);
        config()->set('hydra.addons.knowledgebase.public', $public);
    }

    private function staff(array $permissions): User
    {
        $user = User::factory()->create();
        $user->adminRoles()->attach(AdminRole::query()->create(['name' => 'KB ' . uniqid(), 'color' => '#2fb67c', 'permissions' => $permissions]));

        return $user->refresh();
    }

    public function testEverythingIsGoneWhileTheAddonIsOff(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/knowledgebase')->assertNotFound();
        $this->actingAs($admin)->get('/knowledgebase')->assertNotFound();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertDontSee('/admin/knowledgebase', false);
    }

    public function testCategoriesAndPostsCanBeWrittenAndRead(): void
    {
        $this->enable();
        $admin = User::factory()->admin()->create();
        $default = config('app.locale');
        $other = $default === 'en' ? 'de' : 'en';

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('/admin/knowledgebase', false);
        $this->actingAs($admin)->get('/admin/knowledgebase')->assertOk()->assertSee('hydra-kb-sidebar', false);

        // Main category, then a subcategory of it.
        $this->actingAs($admin)->post('/admin/knowledgebase/categories', ['names' => [$default => 'Getting started', $other => 'Erste Schritte']])
            ->assertSessionHasNoErrors();
        $main = HydraKbCategory::query()->latest('id')->firstOrFail();
        $this->actingAs($admin)->post('/admin/knowledgebase/categories', ['names' => [$default => 'Minecraft'], 'parent_id' => $main->id])
            ->assertSessionHasNoErrors();
        $sub = HydraKbCategory::query()->latest('id')->firstOrFail();
        $this->assertSame($main->id, $sub->parent_id);

        // Only one level: a subcategory cannot get children.
        $this->actingAs($admin)->post('/admin/knowledgebase/categories', ['names' => [$default => 'Too deep'], 'parent_id' => $sub->id])->assertStatus(422);
        // The default language is required.
        $this->actingAs($admin)->post('/admin/knowledgebase/categories', ['names' => [$other => 'Nur andere Sprache']])->assertStatus(422);

        $this->actingAs($admin)->post('/admin/knowledgebase/posts', [
            'category_id' => $sub->id,
            'titles' => [$default => 'Install a modpack', $other => 'Modpack installieren'],
            'contents' => [$default => "**Step 1** ||secret||", $other => ''],
            'published' => '1',
        ])->assertSessionHasNoErrors();
        $post = HydraKbPost::query()->latest('id')->firstOrFail();
        $this->assertSame('install-a-modpack', $post->slug);
        $this->assertSame([$default => '**Step 1** ||secret||'], $post->contents);

        $this->actingAs($admin)->get("/admin/knowledgebase/posts/$post->id")->assertOk()->assertSee('Install a modpack');

        // Users see it in their language, missing texts fall back to the default language.
        $user = User::factory()->create(['language' => $other]);
        $page = $this->actingAs($user)->get('/knowledgebase/install-a-modpack')->assertOk();
        $page->assertSee('Modpack installieren')->assertSee('<strong>Step 1</strong>', false)->assertSee('md-spoiler', false);
        $this->actingAs($user)->get('/knowledgebase')->assertOk()->assertSee('Erste Schritte');

        // Drafts stay in the admin area.
        $post->update(['published' => false]);
        $this->actingAs($user)->get('/knowledgebase/install-a-modpack')->assertNotFound();

        // Deleting a category removes everything in it.
        $this->actingAs($admin)->delete("/admin/knowledgebase/categories/$main->id")->assertRedirect('/admin/knowledgebase');
        $this->assertSame(0, HydraKbPost::query()->count());
        $this->assertSame(0, HydraKbCategory::query()->count());
    }

    public function testVisitorsNeedAnAccountUnlessTheKnowledgebaseIsPublic(): void
    {
        $this->enable();
        $category = HydraKbCategory::query()->create(['names' => [config('app.locale') => 'FAQ']]);
        HydraKbPost::query()->create(['category_id' => $category->id, 'slug' => 'hello', 'titles' => [config('app.locale') => 'Hello'], 'contents' => [config('app.locale') => 'World'], 'published' => true]);

        $this->get('/knowledgebase/hello')->assertRedirect(route('auth.login'));

        config()->set('hydra.addons.knowledgebase.public', true);
        $this->get('/knowledgebase/hello')->assertOk()->assertSee('Hello')->assertSee('World');
    }

    public function testPermissionsAndUploads(): void
    {
        $this->enable();
        $viewer = $this->staff(['knowledgebase.view']);
        $this->actingAs($viewer)->get('/admin/knowledgebase')->assertOk();
        $this->actingAs($viewer)->post('/admin/knowledgebase/categories', ['names' => [config('app.locale') => 'X']])->assertForbidden();
        $this->actingAs($viewer)->get('/admin/knowledgebase/posts/new')->assertForbidden();

        $editor = $this->staff(['knowledgebase.view', 'knowledgebase.update']);
        $result = $this->actingAs($editor)->post('/admin/knowledgebase/upload', [
            'image' => UploadedFile::fake()->createWithContent('shot.gif', base64_decode(self::GIF)),
        ])->assertOk()->json();
        $this->assertMatchesRegularExpression('#^/knowledgebase-media/[0-9a-f-]{36}\.gif$#', $result['url']);
        $this->assertFalse($result['video']);

        $this->actingAs($editor)->get($result['url'])->assertOk()->assertHeader('Content-Type', 'image/gif');
        auth()->logout();
        $this->get($result['url'])->assertNotFound();

        $this->actingAs($editor)->post('/admin/knowledgebase/upload', [
            'image' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
        ])->assertStatus(422);
    }

    public function testVideosAndYoutubeLinksAreEmbedded(): void
    {
        $html = $this->app->make(KnowledgebaseService::class)->render(
            "![Clip](/knowledgebase-media/123e4567-e89b-12d3-a456-426614174000.mp4)\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n\nSee https://www.youtube.com/watch?v=dQw4w9WgXcQ here."
        );

        $this->assertStringContainsString('<video class="hydra-kb-video" src="/knowledgebase-media/123e4567-e89b-12d3-a456-426614174000.mp4" controls', $html);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $html);
        // Only a link alone on its line is embedded.
        $this->assertSame(1, substr_count($html, '<iframe'));
    }
}
