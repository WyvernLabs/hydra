<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Services\Files\AllowedPaths;
use Illuminate\Http\Client\ConnectionException;
use Pterodactyl\Services\Hydra\Addons\McLogsAddon;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Exceptions\Http\Server\FileSizeTooLargeException;
use Pterodactyl\Exceptions\Http\Connection\DaemonConnectionException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\UploadLogRequest;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Hydra: uploads the log file of the server to mclo.gs (addon "mclo.gs").
 */
class McLogsController extends ClientApiController
{
    public function __construct(private DaemonFileRepository $files, private McLogsAddon $addon)
    {
        parent::__construct();
    }

    public function __invoke(UploadLogRequest $request, Server $server): array
    {
        $path = $this->addon->path();
        AllowedPaths::for($server, $request->user())->assertAllowed($path);

        try {
            $content = $this->files->setServer($server)->getContent($path, McLogsAddon::MAX_BYTES);
        } catch (FileSizeTooLargeException) {
            throw new BadRequestHttpException(trans('client/server.console.mclogs.too_large'));
        } catch (DaemonConnectionException) {
            throw new BadRequestHttpException(trans('client/server.console.mclogs.not_found', ['path' => $path]));
        }

        if (trim($content) === '') {
            throw new BadRequestHttpException(trans('client/server.console.mclogs.empty', ['path' => $path]));
        }

        try {
            $response = Http::asForm()->timeout(20)->post(McLogsAddon::API, ['content' => $content]);
        } catch (ConnectionException) {
            throw new ServiceUnavailableHttpException(null, trans('client/server.console.mclogs.failed'));
        }

        if (!$response->successful() || !$response->json('success') || !is_string($url = $response->json('url'))) {
            throw new ServiceUnavailableHttpException(null, trans('client/server.console.mclogs.failed'));
        }

        Activity::event('server:console.mclogs')->property(['file' => $path, 'url' => $url])->log();

        return ['url' => $url];
    }
}
