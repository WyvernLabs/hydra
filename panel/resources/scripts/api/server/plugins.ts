import http from '@/api/http';

// Hydra: plugin manager (addon "plugins"). Modrinth, Hangar and SpigotMC answer
// in the same shape, see PluginSource on the panel.

export type PluginSourceKey = 'modrinth' | 'hangar' | 'spigot';
export type PluginSort = 'relevance' | 'downloads' | 'updated' | 'newest';

export interface PluginSourceInfo {
    key: PluginSourceKey;
    name: string;
    software: string[];
}

export interface PluginSoftware {
    key: string;
    family: 'server' | 'proxy';
}

export interface PluginOverview {
    folder: string | null;
    sources: PluginSourceInfo[];
    software: PluginSoftware[];
    minecraftVersions: string[];
    defaults: { software: string; version: string | null };
}

export interface PluginProject {
    id: string;
    slug: string;
    name: string;
    author: string;
    description: string;
    icon: string | null;
    downloads: number;
    updated: string | null;
    url: string;
    installable: boolean;
    reason: 'premium' | 'external' | null;
}

export interface PluginVersion {
    id: string;
    name: string;
    channel: 'release' | 'beta' | 'alpha';
    date: string | null;
    downloads: number;
    size: number;
    file: string;
    game_versions: string[];
    installable: boolean;
    external_url: string | null;
}

export interface InstalledPlugin {
    file: string;
    enabled: boolean;
    size: number;
    modified: string | null;
    plugin: {
        source: PluginSourceKey;
        project_id: string;
        name: string;
        version_id: string;
        version_name: string;
        icon: string | null;
        url: string | null;
        installed_at: string;
    } | null;
}

export interface PluginFilters {
    source?: PluginSourceKey;
    software: string;
    version: string | null;
}

const base = (uuid: string) => `/api/client/servers/${uuid}/plugins`;

const params = (filters: PluginFilters) => ({
    source: filters.source,
    software: filters.software,
    version: filters.version || undefined,
});

export const pluginIcon = (uuid: string, url: string) => `${base(uuid)}/icon?u=${encodeURIComponent(url)}`;

export const pluginsAvailable = async (uuid: string): Promise<boolean> => {
    const { data } = await http.get(`${base(uuid)}/available`);

    return !!data.available;
};

export const getPluginOverview = async (uuid: string): Promise<PluginOverview> => {
    const { data } = await http.get(base(uuid));

    return {
        folder: data.folder,
        sources: data.sources,
        software: data.software,
        minecraftVersions: data.minecraft_versions || [],
        defaults: data.defaults,
    };
};

export const searchPlugins = async (
    uuid: string,
    filters: PluginFilters & { q: string; sort: PluginSort; page: number }
): Promise<{ data: PluginProject[]; more: boolean }> => {
    const { data } = await http.get(`${base(uuid)}/search`, {
        params: { ...params(filters), q: filters.q || undefined, sort: filters.sort, page: filters.page },
    });

    return data;
};

export const getPluginVersions = async (
    uuid: string,
    project: string,
    filters: PluginFilters & { source: PluginSourceKey }
): Promise<PluginVersion[]> => {
    const { data } = await http.get(
        `${base(uuid)}/projects/${filters.source}/${encodeURIComponent(project)}/versions`,
        {
            params: params(filters),
        }
    );

    return data.data;
};

export const getInstalledPlugins = async (uuid: string): Promise<InstalledPlugin[]> => {
    const { data } = await http.get(`${base(uuid)}/installed`);

    return data.data;
};

export const getPluginUpdates = async (
    uuid: string,
    filters: PluginFilters
): Promise<Record<string, { version_id: string; version_name: string }>> => {
    const { data } = await http.get(`${base(uuid)}/updates`, { params: params(filters) });

    return data.data || {};
};

export const installPlugin = async (
    uuid: string,
    project: string,
    versionId: string | null,
    filters: PluginFilters & { source: PluginSourceKey }
): Promise<{ file: string; name: string; version_name: string }> => {
    const { data } = await http.post(
        `${base(uuid)}/install`,
        { ...params(filters), project, version_id: versionId },
        { timeout: 400000 }
    );

    return data.data;
};

export const togglePlugin = (uuid: string, file: string, enabled: boolean) =>
    http.post(`${base(uuid)}/toggle`, { file, enabled });

export const deletePlugin = (uuid: string, file: string) => http.post(`${base(uuid)}/delete`, { file });
