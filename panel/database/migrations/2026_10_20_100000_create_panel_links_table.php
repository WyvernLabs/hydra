<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Hydra: links in the sidebar of the client area. Each one leads to an
     * own URL or to a page written in Markdown in the admin area.
     */
    public function up(): void
    {
        Schema::create('panel_links', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 8)->default('page');
            $table->json('title');
            $table->string('icon', 32)->default('document-text');
            $table->string('slug', 64)->nullable()->unique();
            $table->string('url', 2048)->nullable();
            $table->json('content')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('public')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('panel_links')->insert([
            [
                'type' => 'page',
                'title' => json_encode(['en' => 'Imprint', 'de' => 'Impressum']),
                'icon' => 'identification',
                'slug' => 'imprint',
                'url' => null,
                'content' => json_encode([
                    'en' => "# Imprint\n\nThis page can be edited in the admin area under **Settings > Links**.",
                    'de' => "# Impressum\n\nDiese Seite kann im Adminbereich unter **Einstellungen > Links** bearbeitet werden.",
                ]),
                'enabled' => true,
                'public' => true,
                'sort' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'type' => 'page',
                'title' => json_encode(['en' => 'Terms of Service', 'de' => 'Nutzungsbedingungen']),
                'icon' => 'scale',
                'slug' => 'terms',
                'url' => null,
                'content' => json_encode([
                    'en' => "# Terms of Service\n\nThis page can be edited in the admin area under **Settings > Links**.",
                    'de' => "# Nutzungsbedingungen\n\nDiese Seite kann im Adminbereich unter **Einstellungen > Links** bearbeitet werden.",
                ]),
                'enabled' => true,
                'public' => true,
                'sort' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                // Hidden until an invite link is entered in the admin area.
                'type' => 'url',
                'title' => json_encode(['en' => 'Discord', 'de' => 'Discord']),
                'icon' => 'discord',
                'slug' => null,
                'url' => null,
                'content' => null,
                'enabled' => true,
                'public' => false,
                'sort' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_links');
    }
};
