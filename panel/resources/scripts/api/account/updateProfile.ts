import http from '@/api/http';
import { rawDataToUserProfile, UserProfile } from '@/state/user';

export interface ProfileValues {
    username: string;
    nameFirst: string;
    nameLast: string;
    bio: string;
    profileAccent: string;
    bannerStyle: 'gradient' | 'image';
    bannerColor1: string;
    bannerColor2: string;
    showOnline: boolean;
    avatar: File | null;
    banner: File | null;
    removeAvatar: boolean;
    removeBanner: boolean;
}

export interface ProfileResponse {
    username: string;
    nameFirst: string;
    nameLast: string;
    profile: UserProfile;
}

/**
 * Hydra: saves the profile of the logged in user, including uploads.
 */
export default async (values: ProfileValues): Promise<ProfileResponse> => {
    const form = new FormData();
    form.append('username', values.username);
    form.append('name_first', values.nameFirst);
    form.append('name_last', values.nameLast);
    form.append('bio', values.bio);
    form.append('profile_accent', values.profileAccent);
    form.append('banner_style', values.bannerStyle);
    form.append('banner_color_1', values.bannerColor1);
    form.append('banner_color_2', values.bannerColor2);
    form.append('show_online', values.showOnline ? '1' : '0');
    if (values.avatar) form.append('avatar', values.avatar);
    if (values.banner) form.append('banner', values.banner);
    if (values.removeAvatar) form.append('remove_avatar', '1');
    if (values.removeBanner) form.append('remove_banner', '1');

    const { data } = await http.post('/api/client/account/profile', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return {
        username: data.username,
        nameFirst: data.name_first,
        nameLast: data.name_last,
        profile: rawDataToUserProfile(data),
    };
};
