import http from '@/api/http';

// Hydra: storage of configs for the config sync (addon "config_sync").

export interface ConfigStashEntry {
    id: number;
    name: string;
    path: string;
    size: number;
    createdBy: string | null;
    createdAt: Date;
}

export interface ConfigStash {
    entries: ConfigStashEntry[];
    maxEntries: number;
    maxSize: number;
}

const toEntry = (data: any): ConfigStashEntry => ({
    id: data.id,
    name: data.name,
    path: data.path,
    size: data.size,
    createdBy: data.created_by,
    createdAt: new Date(data.created_at),
});

const url = (uuid: string, path = '') => `/api/client/servers/${uuid}/config-stash${path}`;

export const getConfigStash = async (uuid: string): Promise<ConfigStash> => {
    const { data } = await http.get(url(uuid));

    return {
        entries: (data.entries || []).map(toEntry),
        maxEntries: data.max_entries,
        maxSize: data.max_size,
    };
};

export const getConfigStashContent = async (uuid: string, id: number): Promise<string> => {
    const { data } = await http.get(url(uuid, `/${id}`));

    return data.content;
};

export const storeConfig = async (uuid: string, values: { name: string; path: string; content: string }) =>
    toEntry((await http.post(url(uuid), values)).data);

export const deleteStoredConfig = async (uuid: string, id: number): Promise<void> => {
    await http.delete(url(uuid, `/${id}`));
};
