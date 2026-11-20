import http from '@/api/http';

// Hydra: mod manager (addon "mods"). Modrinth and CurseForge answer in the same
// shape, see ModSource on the panel.

export type ModSourceKey = 'modrinth' | 'curseforge';
export type ModSort = 'relevance' | 'downloads' | 'updated' | 'newest';
export type ModLoader = 'fabric' | 'forge' | 'neoforge' | 'quilt';

export interface ModCategory {
    key: string;
    slug: string;
    name: string;
    parent: string | null;
}

export interface ModSourceInfo {
    key: ModSourceKey;
    name: string;
    categories: ModCategory[];
    error: string | null;
}

export interface ModOverview {
    folder: string | null;
    sources: ModSourceInfo[];
    loaders: ModLoader[];
    minecraftVersions: string[];
    defaults: { loader: ModLoader; version: string | null };
}

export interface ModProject {
    id: string;
    slug: string;
    name: string;
    author: string;
    description: string;
    icon: string | null;
    downloads: number;
    updated: string | null;
    url: string;
    categories: string[];
    installable: boolean;
    reason: 'external' | null;
}

export interface ModVersion {
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

export interface InstalledMod {
    file: string;
    enabled: boolean;
    size: number;
    modified: string | null;
    mod: {
        source: ModSourceKey;
        project_id: string;
        name: string;
        version_id: string;
        version_name: string;
        icon: string | null;
        url: string | null;
        dependency: boolean;
        installed_at: string;
    } | null;
}

export interface ModFilters {
    source?: ModSourceKey;
    loader: ModLoader;
    version: string | null;
}

const base = (uuid: string) => `/api/client/servers/${uuid}/mods`;

const params = (filters: ModFilters) => ({
    source: filters.source,
    loader: filters.loader,
    version: filters.version || undefined,
});

export const modIcon = (uuid: string, url: string) => `${base(uuid)}/icon?u=${encodeURIComponent(url)}`;

export const modsAvailable = async (uuid: string): Promise<boolean> => {
    const { data } = await http.get(`${base(uuid)}/available`);

    return !!data.available;
};

export const getModOverview = async (uuid: string): Promise<ModOverview> => {
    const { data } = await http.get(base(uuid));

    return {
        folder: data.folder,
        sources: data.sources,
        loaders: data.loaders,
        minecraftVersions: data.minecraft_versions || [],
        defaults: data.defaults,
    };
};

export const searchMods = async (
    uuid: string,
    filters: ModFilters & { q: string; category: string | null; sort: ModSort; page: number }
): Promise<{ data: ModProject[]; more: boolean }> => {
    const { data } = await http.get(`${base(uuid)}/search`, {
        params: {
            ...params(filters),
            q: filters.q || undefined,
            category: filters.category || undefined,
            sort: filters.sort,
            page: filters.page,
        },
    });

    return data;
};

export const getModVersions = async (
    uuid: string,
    project: string,
    filters: ModFilters & { source: ModSourceKey }
): Promise<ModVersion[]> => {
    const { data } = await http.get(
        `${base(uuid)}/projects/${filters.source}/${encodeURIComponent(project)}/versions`,
        {
            params: params(filters),
        }
    );

    return data.data;
};

export const getInstalledMods = async (uuid: string): Promise<InstalledMod[]> => {
    const { data } = await http.get(`${base(uuid)}/installed`);

    return data.data;
};

export const getModUpdates = async (
    uuid: string,
    filters: ModFilters
): Promise<Record<string, { version_id: string; version_name: string }>> => {
    const { data } = await http.get(`${base(uuid)}/updates`, { params: params(filters) });

    return data.data || {};
};

export const installMod = async (
    uuid: string,
    project: string,
    versionId: string | null,
    filters: ModFilters & { source: ModSourceKey }
): Promise<{ file: string; name: string; version_name: string; dependencies: string[] }> => {
    const { data } = await http.post(
        `${base(uuid)}/install`,
        { ...params(filters), project, version_id: versionId },
        { timeout: 900000 }
    );

    return data.data;
};

export const toggleMod = (uuid: string, file: string, enabled: boolean) =>
    http.post(`${base(uuid)}/toggle`, { file, enabled });

export const deleteMod = (uuid: string, file: string) => http.post(`${base(uuid)}/delete`, { file });
