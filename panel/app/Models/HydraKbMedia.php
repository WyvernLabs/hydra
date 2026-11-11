<?php

namespace Pterodactyl\Models;

/**
 * Hydra: picture or video uploaded for the knowledgebase. The file lies in
 * storage/app/hydra/knowledgebase/<uuid> and is served by
 * Base\KnowledgebaseMediaController with the same visibility as the
 * knowledgebase itself.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $mime
 * @property int $size
 * @property int|null $user_id
 */
class HydraKbMedia extends Model
{
    public const RESOURCE_NAME = 'hydra_kb_media';

    public const IMAGES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    public const VIDEOS = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];

    protected $table = 'hydra_kb_media';

    protected bool $immutableDates = true;

    protected $fillable = ['uuid', 'name', 'mime', 'size', 'user_id'];

    protected $casts = ['size' => 'integer', 'user_id' => 'integer'];

    public static array $validationRules = [
        'uuid' => 'required|string|size:36',
        'name' => 'required|string|max:191',
        'mime' => 'required|string|max:64',
        'size' => 'required|integer|min:0',
        'user_id' => 'nullable|integer',
    ];

    public static function mimes(): array
    {
        return self::IMAGES + self::VIDEOS;
    }

    public function path(): string
    {
        return 'hydra/knowledgebase/' . $this->uuid;
    }

    public function isVideo(): bool
    {
        return in_array($this->mime, self::VIDEOS, true);
    }

    /**
     * Relative URL; the extension tells the Markdown renderer whether to draw
     * a video player.
     */
    public function url(): string
    {
        $extension = array_search($this->mime, self::mimes(), true) ?: 'bin';

        return route('knowledgebase.media', ['uuid' => $this->uuid, 'extension' => $extension], false);
    }
}
