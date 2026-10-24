<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Services\Files\AllowedPaths;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Hydra\Addons\ImporterAddon;
use Pterodactyl\Services\Hydra\Games\GameSwitchService;
use Pterodactyl\Repositories\Wings\DaemonImportRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Hydra: copies the files of another server over SFTP onto this one (addon
 * "importer"). The node does the transfer and sends its progress over the
 * websocket; the login is passed on and never stored.
 */
class ImportController extends ClientApiController
{
    public function __construct(
        private ImporterAddon $addon,
        private DaemonImportRepository $daemon,
        private GameSwitchService $games,
    ) {
        parent::__construct();
    }

    public function status(Request $request, Server $server): array
    {
        $this->assertMayImport($request, $server);

        return $this->daemon->setServer($server)->status();
    }

    public function test(Request $request, Server $server): array
    {
        $this->assertMayImport($request, $server);
        $data = $request->validate($this->rules());

        return $this->daemon->setServer($server)->test($this->sftp($data), (string) ($data['source'] ?? ''));
    }

    public function start(Request $request, Server $server): Response
    {
        $this->assertMayImport($request, $server);
        $data = $request->validate($this->rules() + [
            'host_key' => 'required|string|max:200',
            'target' => 'nullable|string|max:4096',
            'mode' => ['required', Rule::in($this->addon->offersProfiles() ? ['keep', 'wipe', 'profile'] : ['keep', 'wipe'])],
            'profile_name' => 'nullable|string|max:60',
        ]);

        if (!is_null($server->status)) {
            throw new DisplayException(__('server/import.busy'));
        }
        if (($this->daemon->setServer($server)->status()['state'] ?? '') === 'copying') {
            throw new DisplayException(__('server/import.running'));
        }

        // The current files go first: removed or kept as a game profile.
        if ($data['mode'] !== 'keep') {
            $this->games->clear($server, $data['mode'] === 'profile', $data['profile_name'] ?? null);
        }

        $sftp = $this->sftp($data) + ['host_key' => $data['host_key']];
        $this->daemon->setServer($server)->start($sftp, (string) ($data['source'] ?? ''), (string) ($data['target'] ?? ''));

        Activity::event('server:import.start')
            ->property([
                'host' => $data['host'],
                'source' => (string) ($data['source'] ?? ''),
                'target' => '/' . ltrim((string) ($data['target'] ?? ''), '/'),
                'mode' => $data['mode'],
            ])
            ->log();

        return new Response('', Response::HTTP_ACCEPTED);
    }

    public function cancel(Request $request, Server $server): Response
    {
        $this->assertMayImport($request, $server);
        $this->daemon->setServer($server)->cancel();

        return new Response('', Response::HTTP_NO_CONTENT);
    }

    private function assertMayImport(Request $request, Server $server): void
    {
        if (!$request->user()->can(Permission::ACTION_SETTINGS_IMPORT, $server)) {
            throw new AccessDeniedHttpException();
        }
        // An import writes into every folder, so subusers limited to some folders cannot use it.
        if (AllowedPaths::for($server, $request->user())->isRestricted()) {
            throw new AccessDeniedHttpException(__('server/import.restricted'));
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
