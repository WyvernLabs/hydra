import useSWR from 'swr';
import http, { getPaginationSet, PaginatedResult } from '@/api/http';
import { ServerBackup } from '@/api/server/types';
import { rawDataToServerBackup } from '@/api/transformers';
import { ServerContext } from '@/state/server';
import { createContext, useContext } from 'react';

interface ctx {
    page: number;
    setPage: (value: number | ((s: number) => number)) => void;
}

export const Context = createContext<ctx>({ page: 1, setPage: () => 1 });

// Hydra: backup space in bytes, and whether the own SFTP server can be used.
type BackupResponse = PaginatedResult<ServerBackup> & {
    backupCount: number;
    storageUsed: number;
    storageLimit: number;
    ownSftp: boolean;
};

export default () => {
    const { page } = useContext(Context);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<BackupResponse>(['server:backups', uuid, page], async () => {
        const { data } = await http.get(`/api/client/servers/${uuid}/backups`, { params: { page } });

        return {
            items: (data.data || []).map(rawDataToServerBackup),
            pagination: getPaginationSet(data.meta.pagination),
            backupCount: data.meta.backup_count,
            storageUsed: data.meta.storage_used || 0,
            storageLimit: data.meta.storage_limit || 0,
            ownSftp: !!data.meta.own_sftp,
        };
    });
};
