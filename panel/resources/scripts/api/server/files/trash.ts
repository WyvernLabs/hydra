import http from '@/api/http';

// Hydra: trash of the file manager.
export interface TrashEntry {
    id: string;
    path: string;
    name: string;
    directory: boolean;
    size: number;
    deletedAt: Date;
    expiresAt: Date;
}

export interface TrashListing {
    entries: TrashEntry[];
    enabled: boolean;
    days: number;
}

export const getTrash = async (uuid: string): Promise<TrashListing> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/trash`);

    return {
        entries: (data.data || []).map((entry: any) => ({
            id: entry.id,
            path: entry.path,
            name: entry.name,
            directory: entry.directory,
            size: entry.size,
            deletedAt: new Date(entry.deleted_at),
            expiresAt: new Date(entry.expires_at),
        })),
        enabled: data.meta.enabled,
        days: data.meta.days,
    };
};

// Returns the paths the entries were restored to, by id.
export const restoreTrash = async (uuid: string, ids: string[]): Promise<Record<string, string>> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/files/trash/restore`, { ids }, { timeout: 300000 });

    return data.restored || {};
};

export const deleteTrash = async (uuid: string, ids: string[] | 'all'): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/files/trash/delete`, ids === 'all' ? { all: true } : { ids }, {
        timeout: 300000,
    });
};
