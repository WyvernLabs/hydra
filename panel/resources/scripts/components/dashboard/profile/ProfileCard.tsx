import React from 'react';
import Avatar from '@/components/Avatar';

export interface ProfileCardProps {
    uuid: string;
    username: string;
    name: string;
    avatar: string | null;
    banner: string | null;
    bannerStyle: 'gradient' | 'image';
    bannerColor1: string;
    bannerColor2: string;
    accent: string;
    bio: string;
    online?: boolean;
}

/**
 * Hydra: a user's profile card with banner, picture, name and biography.
 * Transparent pictures stay transparent, so the banner shows through.
 */
export default ({ uuid, username, name, avatar, banner, bannerStyle, ...props }: ProfileCardProps) => {
    const bannerBackground =
        bannerStyle === 'image' && banner
            ? `center / cover no-repeat url("${banner}")`
            : `linear-gradient(120deg, ${props.bannerColor1}, ${props.bannerColor2})`;

    return (
        <div className={'hydra-profile-card'}>
            <div className={'hydra-profile-banner'} style={{ background: bannerBackground }} />
            <div className={'hydra-profile-avatar'}>
                {avatar ? <img src={avatar} alt={username} /> : <Avatar name={uuid} size={'100%'} />}
                {props.online && <span className={'hydra-online-dot'} />}
            </div>
            <div className={'hydra-profile-body'}>
                <p className={'hydra-profile-name'}>{name || username}</p>
                <p className={'hydra-profile-handle'}>@{username}</p>
                {props.bio && (
                    <p className={'hydra-profile-bio'} style={{ borderColor: props.accent }}>
                        {props.bio}
                    </p>
                )}
            </div>
        </div>
    );
};
