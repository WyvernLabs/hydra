<?php

namespace Pterodactyl\Services\Hydra\Addons;

use Illuminate\Http\UploadedFile;
use Pterodactyl\Services\Hydra\Motd\MotdService;
use Illuminate\Validation\ValidationException;

/**
 * Hydra: custom MOTD with a picture for Minecraft Java servers that are
 * offline, starting, stopping or suspended, e.g. as an advertisement. Wings
 * answers the server list ping of players on the port of the server while it
 * does not run; the texts of each state are set here.
 */
class MotdAddon extends Addon
{
    public const STATES = ['offline', 'starting', 'stopping', 'suspended'];

    public const FAVICON = 'hydra/motd/favicon.png';

    public function key(): string
    {
        return 'motd';
    }

    public function icon(): string
    {
        return 'megaphone';
    }

    public function rules(): array
    {
        return [
            'eggs' => 'nullable|array',
            'eggs.*' => 'integer|exists:eggs,id',
            'states' => 'required|array',
            'states.*.line1' => 'nullable|string|max:256',
            'states.*.line2' => 'nullable|string|max:256',
            'states.*.label' => 'nullable|string|max:128',
            'favicon_upload' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:4096',
            'favicon_remove' => 'nullable|boolean',
        ];
    }

    public function prepare(array $values): array
    {
        if (($values['favicon_upload'] ?? null) instanceof UploadedFile) {
            $this->storeFavicon($values['favicon_upload']);
        } elseif (!empty($values['favicon_remove'])) {
            @unlink(storage_path('app/' . self::FAVICON));
        }
        unset($values['favicon_upload'], $values['favicon_remove']);

        $values['eggs'] = array_values(array_unique(array_map('intval', $values['eggs'] ?? [])));
        // Fields that were not sent keep their default; an emptied field stays empty.
        $states = [];
        foreach (self::STATES as $state) {
            foreach (['line1', 'line2', 'label'] as $field) {
                if (array_key_exists($field, $values['states'][$state] ?? [])) {
                    $states[$state][$field] = trim((string) $values['states'][$state][$field]);
                }
            }
        }
        $values['states'] = $states;

        return $values;
    }

    public function missing(): ?string
    {
        return empty($this->eggIds()) ? trans('admin/addons.motd.no_eggs') : null;
    }

    public function summary(): array
    {
        return [
            trans('admin/addons.motd.summary_eggs', ['count' => count($this->eggIds())]),
            $this->favicon() ? trans('admin/addons.motd.summary_picture') : trans('admin/addons.motd.summary_no_picture'),
        ];
    }

    /**
     * Pushes the new settings to the nodes, also when the addon was switched off.
     */
    public function saved(): void
    {
        app(MotdService::class)->syncAll();
    }

    /**
     * @return int[]
     */
    public function eggIds(): array
    {
        $eggs = $this->get('eggs', []);
        if (is_string($eggs)) {
            $eggs = json_decode($eggs, true);
        }

        return is_array($eggs) ? array_values(array_map('intval', $eggs)) : [];
    }

    /**
     * Texts of every state, the defaults where nothing was set.
     *
     * @return array<string, array{line1: string, line2: string, label: string}>
     */
    public function states(): array
    {
        $stored = $this->get('states');
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        $stored = is_array($stored) ? $stored : [];

        $states = [];
        foreach (self::STATES as $state) {
            foreach (['line1', 'line2', 'label'] as $field) {
                $value = $stored[$state][$field] ?? null;
                $states[$state][$field] = is_string($value) ? $value : trans("admin/addons.motd.defaults.$state.$field");
            }
        }

        return $states;
    }

    /**
     * The picture as base64 encoded 64×64 PNG, null without one.
     */
    public function favicon(): ?string
    {
        $path = storage_path('app/' . self::FAVICON);

        return is_file($path) ? base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * Scales the picture to 64×64 (cut to a square in the middle), which is
     * the only size Minecraft shows.
     *
     * @throws ValidationException
     */
    private function storeFavicon(UploadedFile $file): void
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw ValidationException::withMessages(['favicon_upload' => trans('admin/addons.motd.invalid_picture')]);
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = imagecreatetruecolor(64, 64);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, intdiv($width - $side, 2), intdiv($height - $side, 2), 64, 64, $side, $side);

        $path = storage_path('app/' . self::FAVICON);
        @mkdir(dirname($path), 0750, true);
        imagepng($target, $path, 9);
        imagedestroy($source);
        imagedestroy($target);
    }
}
