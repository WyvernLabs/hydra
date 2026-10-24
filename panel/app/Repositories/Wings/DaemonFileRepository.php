<?php

namespace Pterodactyl\Repositories\Wings;

use Illuminate\Support\Arr;
use Webmozart\Assert\Assert;
use Pterodactyl\Models\Server;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Exceptions\Http\Server\FileSizeTooLargeException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;

/**
 * @method \Pterodactyl\Repositories\Wings\DaemonFileRepository setNode(\Pterodactyl\Models\Node $node)
 * @method \Pterodactyl\Repositories\Wings\DaemonFileRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonFileRepository extends DaemonRepository
{
    /**
     * Return the contents of a given file.
     *
     * @param int|null $notLargerThan the maximum content length in bytes
     *
     * @throws TransferException
     * @throws FileSizeTooLargeException
     * @throws DaemonConnectionException
     */
    public function getContent(string $path, ?int $notLargerThan = null): string
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->get(
                sprintf('/api/servers/%s/files/contents', $this->server->uuid),
                [
                    'query' => ['file' => $path],
                ]
            );
        } catch (ClientException|TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        $length = (int) Arr::get($response->getHeader('Content-Length'), 0, 0);
        if ($notLargerThan && $length > $notLargerThan) {
            throw new FileSizeTooLargeException();
        }

        return $response->getBody()->__toString();
    }

    /**
     * Save new contents to a given file. This works for both creating and updating
     * a file.
     *
     * @throws DaemonConnectionException
     */
    public function putContent(string $path, string $content): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/write', $this->server->uuid),
                [
                    'query' => ['file' => $path],
                    'body' => $content,
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Return a directory listing for a given path.
     *
     * @throws DaemonConnectionException
     */
    public function getDirectory(string $path): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->get(
                sprintf('/api/servers/%s/files/list-directory', $this->server->uuid),
                [
                    'query' => ['directory' => $path],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * Creates a new directory for the server in the given $path.
     *
     * @throws DaemonConnectionException
     */
    public function createDirectory(string $name, string $path): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/create-directory', $this->server->uuid),
                [
                    'json' => [
                        'name' => $name,
                        'path' => $path,
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Renames or moves a file on the remote machine.
     *
     * @throws DaemonConnectionException
     */
    public function renameFiles(?string $root, array $files): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->put(
                sprintf('/api/servers/%s/files/rename', $this->server->uuid),
                [
                    'json' => [
                        'root' => $root ?? '/',
                        'files' => $files,
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Copy a given file and give it a unique name.
     *
     * @throws DaemonConnectionException
     */
    public function copyFile(string $location): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/copy', $this->server->uuid),
                [
                    'json' => [
                        'location' => $location,
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Delete a file or folder for the server.
     *
     * Hydra: with $trashSeconds the files are moved into the trash of the
     * server and kept there for that long.
     *
     * @throws DaemonConnectionException
     * @throws DisplayException
     */
    public function deleteFiles(?string $root, array $files, ?int $trashSeconds = null): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/delete', $this->server->uuid),
                [
                    'json' => array_filter([
                        'root' => $root ?? '/',
                        'files' => $files,
                        'hydra_trash' => $trashSeconds,
                    ], fn ($value) => !is_null($value)),
                ]
            );
        } catch (RequestException $exception) {
            $code = $exception->getResponse() ? (json_decode($exception->getResponse()->getBody()->__toString(), true)['code'] ?? null) : null;
            if ($code === 'trash_unavailable') {
                throw new DisplayException(__('client/files.trash.unavailable'));
            }

            throw new DaemonConnectionException($exception);
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Compress the given files or folders in the given root.
     *
     * @throws DaemonConnectionException
     */
    public function compressFiles(?string $root, array $files, ?string $progress = null): array
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            $response = $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/compress', $this->server->uuid),
                [
                    'json' => [
                        'root' => $root ?? '/',
                        'files' => $files,
                        // Hydra: the daemon reports the progress over the websocket under this id.
                        'progress' => $progress,
                    ],
                    // Wait for up to 15 minutes for the archive to be completed when calling this endpoint
                    // since it will likely take quite awhile for large directories.
                    'timeout' => 60 * 15,
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }

        return json_decode($response->getBody(), true);
    }

    /**
     * Decompresses a given archive file.
     *
     * @throws DaemonConnectionException
     */
    public function decompressFile(?string $root, string $file, bool $contained = false, ?string $progress = null): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/decompress', $this->server->uuid),
                [
                    'json' => [
                        'root' => $root ?? '/',
                        'file' => $file,
                        // Hydra: keep every entry inside the target folder and skip links,
                        // used for subusers that may only work in certain folders.
                        'contained' => $contained,
                        'progress' => $progress,
                    ],
                    // Wait for up to 15 minutes for the decompress to be completed when calling this endpoint
                    // since it will likely take quite awhile for large directories.
                    'timeout' => 60 * 15,
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Chmods the given files.
     *
     * @throws DaemonConnectionException
     */
    public function chmodFiles(?string $root, array $files): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/chmod', $this->server->uuid),
                [
                    'json' => [
                        'root' => $root ?? '/',
                        'files' => $files,
                    ],
                ]
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }

    /**
     * Pulls a file from the given URL and saves it to the disk.
     *
     * @throws DaemonConnectionException
     */
    public function pull(string $url, ?string $directory, array $params = []): ResponseInterface
    {
        Assert::isInstanceOf($this->server, Server::class);

        $attributes = [
            'url' => $url,
            'root' => $directory ?? '/',
            'file_name' => $params['filename'] ?? null,
            'use_header' => $params['use_header'] ?? null,
            'foreground' => $params['foreground'] ?? null,
            // Hydra: lets Wings stop the download when the user loses access.
            'hydra_user' => $params['user'] ?? null,
        ];

        try {
            return $this->getHttpClient()->post(
                sprintf('/api/servers/%s/files/pull', $this->server->uuid),
                array_filter([
                    'json' => array_filter($attributes, fn ($value) => !is_null($value)),
                    // Hydra: downloads in the foreground can take longer than a normal request.
                    'timeout' => $params['timeout'] ?? null,
                ])
            );
        } catch (TransferException $exception) {
            throw new DaemonConnectionException($exception);
        }
    }
}
