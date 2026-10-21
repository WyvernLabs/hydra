import { Action, action, Thunk, thunk } from 'easy-peasy';
import updateAccountEmail from '@/api/account/updateAccountEmail';

export interface UserData {
    uuid: string;
    username: string;
    nameFirst: string;
    email: string;
    language: string;
    rootAdmin: boolean;
    // Hydra: may open the admin area, which admin permissions they hold, and
    // whether they may open every server like a root admin.
    adminAccess: boolean;
    adminPermissions: string[];
    serverAccess: boolean;
    useTotp: boolean;
    createdAt: Date;
    updatedAt: Date;
    // Hydra: profile.
    nameLast: string;
    profile: UserProfile;
    // Hydra: warnings from the admins the user has not confirmed yet.
    warnings: UserWarning[];
}

export interface UserWarning {
    id: number;
    reason: string;
    createdAt: Date;
}

export interface UserProfile {
    avatarUrl: string | null;
    bannerUrl: string | null;
    bannerStyle: 'gradient' | 'image';
    bannerColor1: string | null;
    bannerColor2: string | null;
    profileAccent: string | null;
    bio: string | null;
    showOnline: boolean;
}

// eslint-disable-next-line @typescript-eslint/no-explicit-any
export const rawDataToUserProfile = (data: any): UserProfile => ({
    avatarUrl: data.avatar_url || null,
    bannerUrl: data.banner_url || null,
    bannerStyle: data.banner_style === 'image' ? 'image' : 'gradient',
    bannerColor1: data.banner_color_1 || null,
    bannerColor2: data.banner_color_2 || null,
    profileAccent: data.profile_accent || null,
    bio: data.bio || null,
    showOnline: data.show_online !== false,
});

export interface UserStore {
    data?: UserData;
    setUserData: Action<UserStore, UserData>;
    updateUserData: Action<UserStore, Partial<UserData>>;
    updateUserEmail: Thunk<UserStore, { email: string; password: string }, any, UserStore, Promise<void>>;
}

const user: UserStore = {
    data: undefined,
    setUserData: action((state, payload) => {
        state.data = payload;
    }),

    updateUserData: action((state, payload) => {
        // @ts-expect-error limitation of Typescript, can't do much about that currently unfortunately.
        state.data = { ...state.data, ...payload };
    }),

    updateUserEmail: thunk(async (actions, payload) => {
        await updateAccountEmail(payload.email, payload.password);

        actions.updateUserData({ email: payload.email });
    }),
};

export default user;
