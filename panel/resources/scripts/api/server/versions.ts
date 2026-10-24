import http from '@/api/http';

// Hydra: Minecraft version changer (addon "versions"), data of mcjars.app.

export interface SoftwareType {
    key: string;
    name: string;
    description: string;
    deprecated: boolean;
    experimental: boolean;
    builds: number;
    minecraftVersions: number;
    projectVersions: number;
}

export interface SoftwareCategory {
    id: string;
    types: SoftwareType[];
}

export interface InstalledSoftware {
    type: string;
    version: string;
    build: string;
    build_id: number;
    installed_at: string;
}

export interface SoftwareVersion {
    id: string;
    type: 'RELEASE' | 'SNAPSHOT' | string;
    supported: boolean;
    java: number | null;
    builds: number;
    created: string | null;
}

export interface SoftwareBuild {
    id: number;
    name: string;
    experimental: boolean;
    size: number;
    changes: string[];
    created: string | null;
}

export interface InstallResult {
    type: string;
    version: string;
    build: string;
    java: number | null;
    image_changed: boolean;
}

export const getSoftware = async (
    uuid: string
): Promise<{ current: InstalledSoftware | null; categories: SoftwareCategory[] }> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions`);

    return {
        current: data.current || null,
        categories: (data.categories || []).map((category: any) => ({
            id: category.id,
            types: category.types.map((type: any) => ({
                key: type.key,
                name: type.name,
                description: type.description,
                deprecated: type.deprecated,
                experimental: type.experimental,
                builds: type.builds,
                minecraftVersions: type.minecraft_versions,
                projectVersions: type.project_versions,
            })),
        })),
    };
};

export const getVersions = async (uuid: string, type: string): Promise<SoftwareVersion[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions/${type}`);

    return data.data || [];
};

export const getBuilds = async (uuid: string, type: string, version: string): Promise<SoftwareBuild[]> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/versions/${type}/${encodeURIComponent(version)}`);

    return data.data || [];
};

export const installBuild = async (
    uuid: string,
    build: number,
    options: { wipe: boolean; eula: boolean }
): Promise<InstallResult> => {
    const { data } = await http.post(
        `/api/client/servers/${uuid}/versions/install`,
        { build, ...options },
        { timeout: 900000 }
    );

    return data;
};

export const softwareIcon = (uuid: string, type: string) => `/api/client/servers/${uuid}/versions/icons/${type}`;
