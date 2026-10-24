import http from '@/api/http';

// Hydra: game library (addon "games").

export interface GameEgg {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
}

export interface GameNest {
    id: number;
    name: string;
    eggs: GameEgg[];
}

export interface GameProfile {
    id: number;
    name: string;
    egg: string | null;
    image: string | null;
    size: number;
    createdAt: Date;
}

export interface GameLibrary {
    current: number;
    maxProfiles: number;
    nests: GameNest[];
    profiles: GameProfile[];
}

export const getGameLibrary = async (uuid: string): Promise<GameLibrary> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/games`);

    return {
        current: data.current,
        maxProfiles: data.max_profiles,
        nests: data.nests,
        profiles: (data.profiles || []).map((p: any) => ({ ...p, createdAt: new Date(p.created_at) })),
    };
};

// Stopping the server and moving its files can take a while.
const timeout = 180000;

export const switchGame = async (uuid: string, egg: number, keep: boolean, name: string): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/games/switch`, { egg, keep, name: name || null }, { timeout });
};

export const loadGameProfile = async (uuid: string, profile: number, keep: boolean, name: string): Promise<void> => {
    await http.post(
        `/api/client/servers/${uuid}/games/profiles/${profile}/load`,
        { keep, name: name || null },
        { timeout }
    );
};

export const deleteGameProfile = async (uuid: string, profile: number): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/games/profiles/${profile}`, { timeout });
};
