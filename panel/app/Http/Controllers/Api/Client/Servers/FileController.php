<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Enum\JwtScope;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Services\Hydra\Addons\TrashAddon;
use Pterodactyl\Services\Nodes\NodeJWTService;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Transformers\Api\Client\FileObjectTransformer;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CopyFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\PullFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ListFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ChmodFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\RenameFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CreateFolderRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DecompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\GetFileContentsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\WriteFileContentRequest;

class FileController extends ClientApiController
{
    /**
     * FileController constructor.
     */
    public function __construct(
        private NodeJWTService $jwtService,
        private DaemonFileRepository $fileRepository,
    ) {
        parent::__construct();
    }

    /**
     * Returns a listing of files in a given directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function directory(ListFilesRequest $request, Server $server): array
    {
        $paths = $this->paths($request, $server);
        $paths->assertBrowsable($request->get('directory'));

        $contents = $paths->filterListing($request->get('directory'), $this->fileRepository
            ->setServer($server)
            ->getDirectory($request->get('directory') ?? '/'));

        return $this->fractal->collection($contents)
            ->transformWith($this->getTransformer(FileObjectTransformer::class))
            ->toArray();
    }

    /**
     * Return the contents of a specified file for the user.
     *
     * @throws \Throwable
     */
    public function contents(GetFileContentsRequest $request, Server $server): Response
    {
        $this->paths($request, $server)->assertAllowed($request->get('file'));

        $response = $this->fileRepository->setServer($server)->getContent(
            $request->get('file'),
            config('pterodactyl.files.max_edit_size')
        );

        Activity::event('server:file.read')->property('file', $request->get('file'))->log();

        return new Response($response, Response::HTTP_OK, ['Content-Type' => 'text/plain']);
    }

    /**
     * Generates a one-time token with a link that the user can use to
     * download a given file.
     *
     * @throws \Throwable
     */
    public function download(GetFileContentsRequest $request, Server $server): array
    {
        $this->paths($request, $server)->assertAllowed($request->get('file'), rawurldecode($request->get('file')));

        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
            ->setUser($request->user())
            ->setClaims([
                'file_path' => rawurldecode($request->get('file')),
                'server_uuid' => $server->uuid,
            ])
            ->setScopes(JwtScope::FileDownload)
            ->handle($server->node, $request->user()->id . $server->uuid);

        Activity::event('server:file.download')->property('file', $request->get('file'))->log();

        return [
            'object' => 'signed_url',
            'attributes' => [
                'url' => sprintf(
                    '%s/download/file?token=%s',
                    $server->node->getConnectionAddress(),
                    $token->toString()
                ),
            ],
        ];
    }

    /**
     * Hydra: a link that shows an image, video or music file in the file
     * manager. Unlike a download link it can be used more than once until it
     * expires, since players load files in parts.
     *
     * @throws \Throwable
     */
    public function preview(GetFileContentsRequest $request, Server $server): array
    {
        $this->paths($request, $server)->assertAllowed($request->get('file'));

        $token = $this->jwtService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(30))
            ->setUser($request->user())
            ->setClaims([
                'file_path' => $request->get('file'),
                'server_uuid' => $server->uuid,
                'hydra_preview' => true,
            ])
            ->setScopes(JwtScope::FileDownload)
            ->handle($server->node, $request->user()->id . $server->uuid);

        return [
            'object' => 'signed_url',
            'attributes' => [
                'url' => sprintf(
                    '%s/download/file?token=%s',
                    $server->node->getConnectionAddress(),
                    $token->toString()
                ),
            ],
        ];
    }

    /**
     * Writes the contents of the specified file to the server.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function write(WriteFileContentRequest $request, Server $server): JsonResponse
    {
        $this->paths($request, $server)->assertAllowed($request->get('file'));

        $this->fileRepository->setServer($server)->putContent($request->get('file'), $request->getContent());

        Activity::event('server:file.write')->property('file', $request->get('file'))->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Creates a new folder on the server.
     *
     * @throws \Throwable
     */
    public function create(CreateFolderRequest $request, Server $server): JsonResponse
    {
        $this->paths($request, $server)->assertAllowed(AllowedPaths::join($request->input('root', '/'), $request->input('name')));

        $this->fileRepository
            ->setServer($server)
            ->createDirectory($request->input('name'), $request->input('root', '/'));

        Activity::event('server:file.create-directory')
            ->property('name', $request->input('name'))
            ->property('directory', $request->input('root'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Renames a file on the remote machine.
     *
     * @throws \Throwable
     */
    public function rename(RenameFileRequest $request, Server $server): JsonResponse
    {
        $paths = $this->paths($request, $server);
        foreach ($request->input('files') as $file) {
            $paths->assertAllowed(
                AllowedPaths::join($request->input('root'), $file['from'] ?? ''),
                AllowedPaths::join($request->input('root'), $file['to'] ?? '')
            );
        }

        $this->fileRepository
            ->setServer($server)
            ->renameFiles($request->input('root'), $request->input('files'));

        Activity::event('server:file.rename')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Copies a file on the server.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function copy(CopyFileRequest $request, Server $server): JsonResponse
    {
        $this->paths($request, $server)->assertAllowed($request->input('location'));

        $this->fileRepository
            ->setServer($server)
            ->copyFile($request->input('location'));

        Activity::event('server:file.copy')->property('file', $request->input('location'))->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function compress(CompressFilesRequest $request, Server $server): array
    {
        set_time_limit(900);

        $this->assertFilesAllowed($request, $server, $request->input('files'));

        $file = $this->fileRepository->setServer($server)->compressFiles(
            $request->input('root'),
            $request->input('files'),
            $request->input('progress')
        );

        Activity::event('server:file.compress')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return $this->fractal->item($file)
            ->transformWith($this->getTransformer(FileObjectTransformer::class))
            ->toArray();
    }

    /**
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function decompress(DecompressFilesRequest $request, Server $server): JsonResponse
    {
        set_time_limit(900);

        $paths = $this->paths($request, $server);
        $paths->assertAllowed($request->input('root'), AllowedPaths::join($request->input('root'), $request->input('file')));

        $this->fileRepository->setServer($server)->decompressFile(
            $request->input('root'),
            $request->input('file'),
            $paths->isRestricted(),
            $request->input('progress')
        );

        Activity::event('server:file.decompress')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('file'))
            ->log();

        return new JsonResponse([], JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * Deletes files or folders for the server in the given root directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function delete(DeleteFileRequest $request, Server $server): JsonResponse
    {
        $this->assertFilesAllowed($request, $server, $request->input('files'));

        // Hydra: the files always go into the trash while it is enabled. Deleting for good is only
        // possible from the trash itself (permission file.delete-permanent).
        $trash = app(TrashAddon::class)->keepSeconds();

        $this->fileRepository->setServer($server)->deleteFiles(
            $request->input('root'),
            $request->input('files'),
            $trash
        );

        Activity::event(is_null($trash) ? 'server:file.delete' : 'server:file.trash')
            ->property('directory', $request->input('root'))
            ->property('files', $request->input('files'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Updates file permissions for file(s) in the given root directory.
     *
     * @throws \Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException
     */
    public function chmod(ChmodFilesRequest $request, Server $server): JsonResponse
    {
        $this->assertFilesAllowed($request, $server, array_column($request->input('files'), 'file'));

        $this->fileRepository->setServer($server)->chmodFiles(
            $request->input('root'),
            $request->input('files')
        );

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Requests that a file be downloaded from a remote location by Wings.
     *
     * @throws \Throwable
     */
    public function pull(PullFileRequest $request, Server $server): JsonResponse
    {
        $this->paths($request, $server)->assertAllowed(
            $request->input('directory'),
            AllowedPaths::join($request->input('directory'), $request->input('filename'))
        );

        $this->fileRepository->setServer($server)->pull(
            $request->input('url'),
            $request->input('directory'),
            [...$request->safe(['filename', 'use_header', 'foreground']), 'user' => $request->user()->uuid]
        );

        Activity::event('server:file.pull')
            ->property('directory', $request->input('directory'))
            ->property('url', $request->input('url'))
            ->log();

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * Hydra: the folders the user may work in on this server.
     */
    private function paths(Request $request, Server $server): AllowedPaths
    {
        return AllowedPaths::for($server, $request->user());
    }

    /**
     * Hydra: the root folder and every file in it must be allowed.
     */
    private function assertFilesAllowed(Request $request, Server $server, array $files): void
    {
        $paths = $this->paths($request, $server);
        $paths->assertAllowed($request->input('root'));

        foreach ($files as $file) {
            $paths->assertAllowed(AllowedPaths::join($request->input('root'), (string) $file));
        }
    }
}
