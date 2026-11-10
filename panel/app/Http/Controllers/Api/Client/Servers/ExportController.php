<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Repositories\Wings\DaemonExportRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: uploads the files of this server to another server over SFTP (addon
 * "exporter"). The node does the transfer and sends its progress over the
 * websocket; the login is passed on and never stored.
 */
class ExportController extends ClientApiController
{
    public function __construct(private DaemonExportRepository $daemon)
    {
        parent::__construct();
    }

    public function status(Request $request, Server $server): array
    {
        $this->assertMayExport($request, $server);

        return $this->daemon->setServer($server)->status();
    }

    public function test(Request $request, Server $server): array
    {
        $this->assertMayExport($request, $server);
        $data = $request->validate($this->rules());

        return $this->daemon->setServer($server)->testTarget(
            $this->sftp($data),
            AllowedPaths::normalize($data['source'] ?? '/'),
            (string) ($data['target'] ?? '')
        );
    }

    public function start(Request $request, Server $server): Response
    {
        $this->assertMayExport($request, $server);
        $data = $request->validate($this->rules() + ['host_key' => 'required|string|max:200']);

        if (($this->daemon->setServer($server)->status()['state'] ?? '') === 'copying') {
            throw new DisplayException(__('server/import.export_running'));
        }

        $source = AllowedPaths::normalize($data['source'] ?? '/');
        $sftp = $this->sftp($data) + ['host_key' => $data['host_key']];
        $this->daemon->setServer($server)->start($sftp, $source, (string) ($data['target'] ?? ''));

        Activity::event('server:export.start')
            ->property([
                'host' => $data['host'],
                'source' => $source,
                'target' => (string) ($data['target'] ?? ''),
            ])
            ->log();

        return new Response('', Response::HTTP_ACCEPTED);
    }

    public function cancel(Request $request, Server $server): Response
    {
        $this->assertMayExport($request, $server);
        $this->daemon->setServer($server)->cancel();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function assertMayExport(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_SETTINGS_EXPORT, $server)) {
            throw new AccessDeniedHttpException();
        }
        // An export reads every folder, so subusers limited to some folders cannot use it.
        if (AllowedPaths::for($server, $request->user())->isRestricted()) {
            throw new AccessDeniedHttpException(__('server/import.export_restricted'));
        }
    }

    private function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:253', 'regex:/^[A-Za-z0-9.\-:\[\]]+$/'],
            'port' => 'required|integer|between:1,65535',
            'username' => 'required|string|max:191',
            'password' => 'nullable|required_without:private_key|string|max:1024',
            'private_key' => 'nullable|required_without:password|string|max:16384',
            'source' => 'nullable|string|max:4096',
            'target' => 'nullable|string|max:4096',
        ];
    }

    private function sftp(array $data): array
    {
        return [
            'host' => trim($data['host'], '[]'),
            'port' => (int) $data['port'],
            'username' => $data['username'],
            'password' => (string) ($data['password'] ?? ''),
            'private_key' => (string) ($data['private_key'] ?? ''),
        ];
    }
}
