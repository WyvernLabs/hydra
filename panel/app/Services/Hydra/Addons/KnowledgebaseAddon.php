<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Pterodactyl\Models\HydraKbPost;

/**
 * Hydra: knowledgebase. The team writes posts in categories and
 * subcategories under Admin > Service Management > Knowledgebase; users find
 * it in the sidebar of the client area. The address (e.g. /knowledgebase)
 * can be changed, and it can be opened without logging in.
 */
class KnowledgebaseAddon extends Addon
{
    public const DEFAULT_PATH = 'knowledgebase';

    /**
     * First parts of addresses that belong to the panel and cannot be used.
     */
    public const RESERVED = [
        'admin', 'api', 'auth', 'daemon', 'server', 'account', 'pages', 'status', 'profiles', 'tickets',
        'install', 'databases', 'branding', 'backgrounds', 'locales', 'pwa', 'hydra', 'themes', 'js', 'css',
        'assets', 'favicons', 'vendor', 'storage', 'phpmyadmin', 'sanctum', 'knowledgebase-media', 'hydra-maintenance',
    ];

    public function key(): string
    {
        return 'knowledgebase';
    }

    public function icon(): string
    {
        return 'book-open';
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'min:2', 'max:40', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', 'not_in:' . implode(',', self::RESERVED)],
            'public' => 'nullable|boolean',
            'upload_limit' => 'required|integer|between:1,500',
        ];
    }

    public function booleans(): array
    {
        return ['public'];
    }

    public function summary(): array
    {
        $posts = rescue(fn () => HydraKbPost::query()->count(), 0, false);

        return [
            trans('admin/addons.knowledgebase.summary_path', ['url' => '/' . $this->path()]),
            trans($this->isPublic() ? 'admin/addons.knowledgebase.summary_public' : 'admin/addons.knowledgebase.summary_private'),
            trans('admin/addons.knowledgebase.summary_posts', ['count' => $posts]),
        ];
    }

    public function path(): string
    {
        $path = (string) $this->get('path', self::DEFAULT_PATH);

        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $path) && !in_array($path, self::RESERVED, true) ? $path : self::DEFAULT_PATH;
    }

    public function url(): string
    {
        return '/' . $this->path();
    }

    public function isPublic(): bool
    {
        return filter_var($this->get('public', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Largest picture or video in megabytes.
     */
    public function uploadLimit(): int
    {
        return max(1, min(500, (int) $this->get('upload_limit', 50)));
    }
}
