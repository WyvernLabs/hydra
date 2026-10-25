<?php

namespace Pterodactyl\Services\Hydra\Minecraft;

use Carbon\CarbonImmutable;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerVariable;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\ServerStopper;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Repositories\Wings\DaemonProfileRepository;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * Hydra: installs a build of mcjars.app on a server (addon "versions"). The
 * installation steps are always loaded from mcjars by the panel, never taken
 * from the browser, and run on the node one after another: downloads, unzips
 * and removals of files. A "server.jar" ends up under the jar file name of the
 * server, and the Docker image of the egg with the needed Java is picked.
 */
class VersionInstallService
{
    // Longest time a single download may take, in seconds.
    public const DOWNLOAD_TIMEOUT = 600;

    public function __construct(
        private McJarsClient $client,
        private ServerStopper $stopper,
        private DaemonFileRepository $files,
        private DaemonProfileRepository $profiles,
    ) {
    }

    /**
     * @return array{type: string, version: string, build: string, build_id: int, java: int|null, image_changed: bool}
     *
     * @throws \Throwable
     */
    public function install(Server $server, int $buildId, bool $wipe, bool $eula): array
    {
        $build = $this->client->build($buildId);
        $type = (string) ($build['type'] ?? '');
        $version = (string) ($build['versionId'] ?? $build['projectVersionId'] ?? '');
        if (is_null($this->client->type($type)) || $version === '') {
            throw new DisplayException(__('server/versions.errors.unknown_build'));
        }
        $steps = $this->steps($build['installation'] ?? []);
        $java = $this->client->versions($type)[$version]['java'] ?? null;

        $this->stopper->stop($server, __('server/versions.errors.stop_failed'));

        if ($wipe) {
            $this->profiles->setServer($server)->wipe();
        }

        $files = $this->files->setServer($server);
        $jar = $this->jarFile($server);
        foreach ($steps as $step) {
            switch ($step['type']) {
                case 'download':
                    $target = $step['file'] === '/server.jar' ? $jar : $step['file'];
                    $files->pull($step['url'], $this->dirname($target), [
                        'filename' => basename($target),
                        'foreground' => true,
                        'timeout' => self::DOWNLOAD_TIMEOUT,
                    ]);
                    break;
                case 'unzip':
                    $files->decompressFile($step['location'], ltrim(substr($step['file'], strlen(rtrim($step['location'], '/'))), '/'));
                    break;
                case 'remove':
                    $files->deleteFiles($this->dirname($step['location']), [basename($step['location'])]);
                    break;
            }
        }

        // Forge, NeoForge and others unpack a server.jar of their own.
        if ($jar !== '/server.jar' && collect($steps)->contains('type', 'unzip')) {
            try {
                $files->deleteFiles($this->dirname($jar), [basename($jar)]);
                $files->renameFiles('/', [['from' => 'server.jar', 'to' => ltrim($jar, '/')]]);
            } catch (DaemonConnectionException) {
                // The archive had no server.jar, the jar file stays as it is.
            }
        }

        if ($eula) {
            $files->putContent('/eula.txt', "# Accepted in the panel (https://aka.ms/MinecraftEULA)\neula=true\n");
        }

        $this->updateVariables($server, $type, $version, (string) ($build['buildNumber'] ?? $build['name'] ?? 'latest'));

        $image = $this->imageFor($server, is_numeric($java) ? (int) $java : null);
        $server->forceFill([
            'image' => $image ?? $server->image,
            'hydra_minecraft' => [
                'type' => $type,
                'version' => $version,
                'build' => (string) ($build['name'] ?? $build['buildNumber'] ?? $buildId),
                'build_id' => $buildId,
                'installed_at' => CarbonImmutable::now()->toIso8601String(),
            ],
        ])->save();

        return [
            'type' => $type,
            'version' => $version,
            'build' => (string) ($build['name'] ?? $buildId),
            'build_id' => $buildId,
            'java' => is_numeric($java) ? (int) $java : null,
            'image_changed' => !is_null($image) && $server->wasChanged('image'),
        ];
    }

    /**
     * The installation steps in order, with every path checked to stay inside
     * the server and every download to come from https.
     *
     * @return array<int, array{type: string, url?: string, file?: string, location?: string}>
     */
    public function steps(array $installation): array
    {
        $steps = [];
        foreach ($installation as $group) {
            foreach (is_array($group) ? $group : [] as $step) {
                $type = $step['type'] ?? null;
                if ($type === 'download') {
                    $url = (string) ($step['url'] ?? '');
                    if (!str_starts_with($url, 'https://') || filter_var($url, FILTER_VALIDATE_URL) === false) {
                        throw new DisplayException(__('server/versions.errors.invalid_build'));
                    }
                    $steps[] = ['type' => 'download', 'url' => $url, 'file' => $this->path($step['file'] ?? null)];
                } elseif ($type === 'unzip') {
                    $file = $this->path($step['file'] ?? null);
                    $location = $this->path($step['location'] ?? null, true);
                    if (!str_starts_with($file, rtrim($location, '/') . '/')) {
                        throw new DisplayException(__('server/versions.errors.invalid_build'));
                    }
                    $steps[] = ['type' => 'unzip', 'file' => $file, 'location' => $location];
                } elseif ($type === 'remove') {
                    $steps[] = ['type' => 'remove', 'location' => $this->path($step['location'] ?? null)];
                } else {
                    throw new DisplayException(__('server/versions.errors.invalid_build'));
                }
            }
        }
        if (!collect($steps)->contains('type', 'download')) {
            throw new DisplayException(__('server/versions.errors.invalid_build'));
        }

        return $steps;
    }

    /**
     * An absolute path inside the server; "." only where a folder is meant.
     */
    private function path(mixed $value, bool $folder = false): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || str_contains($value, "\0") || str_contains($value, '\\') || in_array('..', explode('/', $value), true)) {
            throw new DisplayException(__('server/versions.errors.invalid_build'));
        }
        $path = AllowedPaths::normalize($value);
        if ($path === '/' && !$folder) {
            throw new DisplayException(__('server/versions.errors.invalid_build'));
        }

        return $path;
    }

    private function dirname(string $path): string
    {
        $dir = dirname($path);

        return $dir === '.' || $dir === '\\' ? '/' : $dir;
    }

    /**
     * Servers of the universal egg (it has SERVER_TYPE) keep the software in
     * their variables, so a reinstall installs the same again. Other eggs keep
     * their variables, which may mean something else there.
     */
    private function updateVariables(Server $server, string $type, string $version, string $build): void
    {
        $variables = $server->variables()->get()->keyBy('env_variable');
        if (!$variables->has('SERVER_TYPE')) {
            return;
        }

        foreach (['SERVER_TYPE' => $type, 'MINECRAFT_VERSION' => $version, 'BUILD_NUMBER' => ltrim($build, '#')] as $env => $value) {
            if ($variable = $variables->get($env)) {
                ServerVariable::query()->updateOrCreate(
                    ['server_id' => $server->id, 'variable_id' => $variable->id],
                    ['variable_value' => $value]
                );
            }
        }
    }

    /**
     * Where the server keeps its jar, from the SERVER_JARFILE variable of the egg.
     */
    private function jarFile(Server $server): string
    {
        $variable = $server->variables()->where('env_variable', 'SERVER_JARFILE')->first();
        $value = trim((string) ($variable->server_value ?? $variable->default_value ?? ''));

        try {
            return $value === '' ? '/server.jar' : $this->path($value);
        } catch (DisplayException) {
            return '/server.jar';
        }
    }

    /**
     * The Docker image of the egg for the Java version, the next newer one if
     * there is none for exactly this version; null when the egg has no images
     * named after Java versions. Alternative JVMs (GraalVM, OpenJ9) are only
     * used when there is no regular image for that version.
     */
    public function imageFor(Server $server, ?int $java): ?string
    {
        if (is_null($java)) {
            return null;
        }

        $candidates = $alternatives = [];
        foreach ($server->egg->docker_images ?? [] as $name => $image) {
            if (preg_match('/java[ _-]?(\d+)/i', "$name $image", $match)) {
                if (preg_match('/graal|j9/i', "$name $image")) {
                    $alternatives[(int) $match[1]] ??= $image;
                } else {
                    $candidates[(int) $match[1]] ??= $image;
                }
            }
        }
        $candidates += $alternatives;
        if (empty($candidates)) {
            return null;
        }

        ksort($candidates);
        foreach ($candidates as $version => $image) {
            if ($version >= $java) {
                return $image;
            }
        }

        return end($candidates);
    }
}
