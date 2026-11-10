<?php

namespace Pterodactyl\Repositories\Wings;

/**
 * Hydra: export of the server's files to another server over SFTP, done by
 * the node of the server (addon "exporter"). Same endpoints as the import,
 * under /hydra/export.
 *
 * @method \Pterodactyl\Repositories\Wings\DaemonExportRepository setServer(\Pterodactyl\Models\Server $server)
 */
class DaemonExportRepository extends DaemonImportRepository
{
    protected string $kind = 'export';
}
