<?php

namespace Pterodactyl\Services\Hydra;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Models\HydraKbPost;
use Pterodactyl\Models\HydraKbMedia;
use Illuminate\Support\Facades\Storage;
use Pterodactyl\Models\HydraKbCategory;
use Illuminate\Database\Eloquent\Collection;
use Pterodactyl\Services\Hydra\Markdown\MarkdownService;

/**
 * Hydra: the knowledgebase addon. Categories, posts, uploads and the
 * Markdown of the posts, which may also show uploaded videos and YouTube
 * videos (a YouTube link alone on a line).
 */
class KnowledgebaseService
{
    public function __construct(private MarkdownService $markdown)
    {
    }

    /**
     * Main categories with their subcategories and posts, in their order.
     *
     * @return Collection<int, HydraKbCategory>
     */
    public function tree(bool $drafts = false): Collection
    {
        $posts = fn ($query) => $drafts ? $query : $query->where('published', true);

        return HydraKbCategory::query()
            ->whereNull('parent_id')
            ->with(['posts' => $posts, 'children.posts' => $posts])
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    /**
     * Posts in the order of the tree, for "previous / next" links.
     *
     * @return HydraKbPost[]
     */
    public function flatPosts(Collection $tree): array
    {
        $posts = [];
        foreach ($tree as $category) {
            foreach ($category->posts as $post) {
                $posts[] = $post;
            }
            foreach ($category->children as $child) {
                foreach ($child->posts as $post) {
                    $posts[] = $post;
                }
            }
        }

        return $posts;
    }

    /**
     * Keeps only non-empty texts of the given languages.
     */
    public static function texts(array $values, array $languages, int $max, bool $singleLine): array
    {
        $result = [];
        foreach ($languages as $code) {
            $value = str_replace("\r\n", "\n", (string) ($values[$code] ?? ''));
            $value = $singleLine ? trim(preg_replace('/\s+/u', ' ', $value)) : rtrim($value);
            if (trim($value) !== '') {
                $result[$code] = mb_substr($value, 0, $max);
            }
        }

        return $result;
    }

    /**
     * Address of a post from its title in the default language, unique.
     */
    public function slug(string $title, ?int $ignore = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'post';
        $slug = $base;
        for ($i = 2; HydraKbPost::query()->where('slug', $slug)->when($ignore, fn ($q) => $q->where('id', '!=', $ignore))->exists(); ++$i) {
            $slug = "$base-$i";
        }

        return $slug;
    }

    public function upload(User $user, UploadedFile $file): HydraKbMedia
    {
        $uuid = Uuid::uuid4()->toString();
        Storage::disk('local')->putFileAs('hydra/knowledgebase', $file, $uuid);

        return HydraKbMedia::query()->create([
            'uuid' => $uuid,
            'name' => mb_substr($file->getClientOriginalName() ?: 'file', 0, 191),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'user_id' => $user->id,
        ]);
    }

    /**
     * HTML of a post: Discord Markdown, uploaded videos as players and lone
     * YouTube links as embedded videos.
     */
    public function render(?string $markdown): string
    {
        $html = $this->markdown->render($markdown);

        // ![name](/knowledgebase-media/<uuid>.mp4) → video player.
        $html = preg_replace(
            '#<img src="(/knowledgebase-media/[0-9a-f-]{36}\.(?:mp4|webm))"(?: alt="([^"]*)")?[^>]*>#',
            '<video class="hydra-kb-video" src="$1" controls preload="metadata" title="$2"></video>',
            $html
        ) ?? $html;

        // A YouTube link alone in a paragraph → embedded video (without cookies).
        $html = preg_replace_callback(
            '#<p><a [^>]*?href="(https?://(?:www\.|m\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})[^"]*)"[^>]*>[^<]*</a></p>#',
            fn (array $match) => sprintf(
                '<div class="hydra-kb-embed"><iframe src="https://www.youtube-nocookie.com/embed/%s" title="YouTube" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
                $match[2]
            ),
            $html
        ) ?? $html;

        return $html;
    }

    /**
     * Uploads that no post uses any more and that are older than a day.
     */
    public function cleanup(): int
    {
        $used = HydraKbPost::query()->pluck('contents')->map(fn ($contents) => implode("\n", (array) $contents))->implode("\n");
        $removed = 0;

        HydraKbMedia::query()->where('created_at', '<', now()->subDay())->each(function (HydraKbMedia $media) use ($used, &$removed) {
            if (!str_contains($used, $media->uuid)) {
                Storage::disk('local')->delete($media->path());
                $media->delete();
                ++$removed;
            }
        });

        return $removed;
    }
}
