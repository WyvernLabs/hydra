<?php

namespace Pterodactyl\Services\Users;

use Illuminate\Support\Str;
use Pterodactyl\Models\User;
use Illuminate\Http\UploadedFile;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Hydra: profile pictures and banners. Files are stored as uploaded, so
 * animated GIFs and transparent PNGs/WebPs stay as they are.
 */
class ProfileMediaService
{
    public const TYPES = ['avatar', 'banner'];

    /**
     * Image types that may be uploaded. SVGs are not allowed as they could
     * carry scripts.
     */
    public const MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public const DEFAULT_UPLOAD_LIMIT = 4;

    public static function directory(): string
    {
        return storage_path('app/hydra/profiles');
    }

    /**
     * The upload limit in megabytes, configurable in the admin area.
     */
    public static function uploadLimit(): int
    {
        $limit = (int) config('hydra.profiles.upload_limit', self::DEFAULT_UPLOAD_LIMIT);

        return $limit > 0 ? $limit : self::DEFAULT_UPLOAD_LIMIT;
    }

    /**
     * Absolute path of the user's picture or banner, or null if there is none.
     */
    public static function path(User $user, string $type): ?string
    {
        $file = in_array($type, self::TYPES, true) ? $user->getAttribute($type) : null;
        if (empty($file) || basename($file) !== $file) {
            return null;
        }

        $path = self::directory() . DIRECTORY_SEPARATOR . $file;

        return is_file($path) ? $path : null;
    }

    /**
     * URL of the user's picture or banner, or null if there is none. The
     * file name is part of the URL so browsers pick up a new upload at once.
     */
    public static function url(User $user, string $type): ?string
    {
        if (is_null(self::path($user, $type))) {
            return null;
        }

        return sprintf('/profiles/%s/%s?v=%s', $user->uuid, $type, substr(md5($user->getAttribute($type)), 0, 8));
    }

    /**
     * Stores an uploaded picture or banner and removes the previous one.
     *
     * @throws DisplayException
     */
    public function store(User $user, string $type, UploadedFile $file): void
    {
        // Trust the file contents rather than the name or the browser.
        $info = @getimagesize($file->getRealPath());
        $extension = $info ? (self::MIME_TYPES[$info['mime']] ?? null) : null;
        if (is_null($extension)) {
            throw new DisplayException(trans('exceptions.profile.invalid_image'));
        }

        if (!is_dir(self::directory())) {
            mkdir(self::directory(), 0755, true);
        }

        $name = sprintf('%s-%s-%s.%s', $user->uuid, $type, Str::random(10), $extension);
        $file->move(self::directory(), $name);

        $this->remove($user, $type);
        $user->forceFill([$type => $name])->saveOrFail();
    }

    /**
     * Removes the user's picture or banner.
     */
    public function remove(User $user, string $type): void
    {
        if ($path = self::path($user, $type)) {
            @unlink($path);
        }

        if (!is_null($user->getAttribute($type))) {
            $user->forceFill([$type => null])->saveOrFail();
        }
    }

    /**
     * Removes all files of a user, used when the account is deleted.
     */
    public static function removeFiles(User $user): void
    {
        foreach (self::TYPES as $type) {
            if ($path = self::path($user, $type)) {
                @unlink($path);
            }
        }
    }
}
