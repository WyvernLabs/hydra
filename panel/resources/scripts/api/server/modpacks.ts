import http from '@/api/http';
import { ModCategory, ModLoader, ModSort } from '@/api/server/mods';

// Hydra: modpack installer (addon "modpacks").

export type ModpackSourceKey = 'modrinth' | 'curseforge';
export type ModpackMode = 'wipe' | 'profile' | 'keep';

export interface ModpackSourceInfo {
    key: ModpackSourceKey;
    name: string;
    categories: ModCategory[];
    error: string | null;
}

export interface ModpackProject {
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
    loaders: ModLoader[];
    game_version: string | null;
    installable: boolean;
    reason: 'external' | null;
}

export interface ModpackVersion {
    id: string;
    name: string;
    channel: 'release' | 'beta' | 'alpha';
    date: string | null;
    downloads: number;
    size: number;
    file: string;
    game_versions: string[];
    loaders: ModLoader[];
    installable: boolean;
    external_url: string | null;
}

export interface InstalledModpack {
    source: ModpackSourceKey;
    project_id: string;
    version_id: string;
    name: string;
    version_name: string | null;
    icon: string | null;
    url: string | null;
    installed_at: string;
}

export interface ModpackInstall {
    id: number;
    source: ModpackSourceKey;
    project_id: string;
    version_id: string;
    name: string;
    version_name: string | null;
    icon: string | null;
    url: string | null;
    mode: ModpackMode;
    status: 'queued' | 'running' | 'done' | 'failed';
    step: 'prepare' | 'clear' | 'server' | 'overrides' | 'mods' | 'finish' | null;
    progress: number;
    total: number;
    result: {
        minecraft?: string;
        loader?: ModLoader | null;
        loader_version?: string | null;
        server_build?: string;
        exact_build?: boolean;
        skipped?: string[];
        manual?: { name: string; url: string | null }[];
        failed?: string[];
    };
    error: string | null;
    created_at: string;
    updated_at: string;
}

export interface ModpackOverview {
    sources: ModpackSourceInfo[];
    loaders: ModLoader[];
    minecraftVersions: string[];
    profiles: boolean;
    current: InstalledModpack | null;
    install: ModpackInstall | null;
}

const base = (uuid: string) => `/api/client/servers/${uuid}/modpacks`;

export const modpackIcon = (uuid: string, url: string) => `${base(uuid)}/icon?u=${encodeURIComponent(url)}`;

export const getModpackOverview = async (uuid: string): Promise<ModpackOverview> => {
    const { data } = await http.get(base(uuid));

    return {
        sources: data.sources,
        loaders: data.loaders,
        minecraftVersions: data.minecraft_versions || [],
        profiles: !!data.profiles,
        current: data.current || null,
        install: data.install || null,
    };
};

export const searchModpacks = async (
    uuid: string,
    filters: {
        source: ModpackSourceKey;
        loader: ModLoader | null;
        version: string | null;
        category: string | null;
        q: string;
        sort: ModSort;
        page: number;
    }
): Promise<{ data: ModpackProject[]; more: boolean }> => {
    const { data } = await http.get(`${base(uuid)}/search`, {
        params: {
            source: filters.source,
            loader: filters.loader || undefined,
            version: filters.version || undefined,
            category: filters.category || undefined,
            q: filters.q || undefined,
            sort: filters.sort,
            page: filters.page,
        },
    });

    return data;
};

export const getModpackVersions = async (
    uuid: string,
    source: ModpackSourceKey,
    project: string
): Promise<ModpackVersion[]> => {
    const { data } = await http.get(`${base(uuid)}/projects/${source}/${encodeURIComponent(project)}/versions`);

    return data.data;
};

export const installModpack = async (
    uuid: string,
    body: { source: ModpackSourceKey; project: string; version_id: string; mode: ModpackMode; eula: boolean }
): Promise<ModpackInstall> => {
    const { data } = await http.post(`${base(uuid)}/install`, body, { timeout: 120000 });

    return data.data;
};

export const getModpackStatus = async (uuid: string): Promise<ModpackInstall | null> => {
    const { data } = await http.get(`${base(uuid)}/status`);

    return data.data || null;
};
