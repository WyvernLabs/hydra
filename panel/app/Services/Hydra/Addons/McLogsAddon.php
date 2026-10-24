<?php

namespace Pterodactyl\Services\Hydra\Addons;

/**
 * Hydra: a button in the console uploads the log file of the server to
 * mclo.gs and shows the link in the console. The file is read by the panel and
 * sent to https://api.mclo.gs, which keeps it publicly readable.
 */
class McLogsAddon extends Addon
{
    public const API = 'https://api.mclo.gs/1/log';

    // mclo.gs refuses larger logs.
    public const MAX_BYTES = 10 * 1024 * 1024;

    public function key(): string
    {
        return 'mclogs';
    }

    public function icon(): string
    {
        return 'file-up';
    }

    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:191', 'regex:/^[^\\\\\0]+$/'],
        ];
    }

    public function summary(): array
    {
        return [trans('admin/addons.mclogs.summary', ['path' => $this->path()])];
    }

    /**
     * Log file inside the server, e.g. "logs/latest.log".
     */
    public function path(): string
    {
        return ltrim((string) $this->get('path', 'logs/latest.log'), '/') ?: 'logs/latest.log';
    }
}
