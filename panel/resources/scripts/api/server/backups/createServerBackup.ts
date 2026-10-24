import http from '@/api/http';
import { ServerBackup } from '@/api/server/types';
import { rawDataToServerBackup } from '@/api/transformers';

interface RequestParameters {
    name?: string;
    ignored?: string;
    isLocked: boolean;
    // Hydra: "own_sftp" stores it on the own SFTP server.
    location?: 'default' | 'own_sftp';
}

export default async (uuid: string, params: RequestParameters): Promise<ServerBackup> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/backups`, {
        name: params.name,
        ignored: params.ignored,
        is_locked: params.isLocked,
        location: params.location || 'default',
    });

    return rawDataToServerBackup(data);
};
