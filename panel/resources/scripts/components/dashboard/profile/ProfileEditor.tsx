import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useStoreActions, useStoreState } from '@/state/hooks';
import { Button } from '@/components/elements/button/index';
import Input, { Textarea } from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import updateProfile, { ProfileValues } from '@/api/account/updateProfile';
import ProfileCard from '@/components/dashboard/profile/ProfileCard';
import ColorInput from '@/components/dashboard/profile/ColorInput';
import { SwitchVerticalIcon } from '@heroicons/react/outline';

const BIO_LIMIT = 240;
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
const DEFAULT_BANNER_COLOR = '#1f1f1f';

const isColor = (value: string) => /^#[0-9a-fA-F]{6}$/.test(value);

/**
 * Hydra: the profile editor on the account page with a live preview.
 */
export default () => {
    const { t } = useTranslation('client/account');
    const user = useStoreState((state) => state.user.data!);
    const accent = useStoreState((state) => state.settings.data!.branding.accent);
    const uploadLimit = useStoreState((state) => state.settings.data!.profileUploadLimit || 4);
    const updateUserData = useStoreActions((actions) => actions.user.updateUserData);
    const { clearFlashes, clearAndAddHttpError, addError } = useFlashKey('account:profile');
    const { addFlash } = useFlash();

    const initial = useMemo<ProfileValues>(
        () => ({
            username: user.username,
            nameFirst: user.nameFirst || '',
            nameLast: user.nameLast || '',
            bio: user.profile.bio || '',
            profileAccent: user.profile.profileAccent || '',
            bannerStyle: user.profile.bannerStyle,
            bannerColor1: user.profile.bannerColor1 || '',
            bannerColor2: user.profile.bannerColor2 || '',
            showOnline: user.profile.showOnline,
            avatar: null,
            banner: null,
            removeAvatar: false,
            removeBanner: false,
        }),
        [user]
    );

    const [values, setValues] = useState<ProfileValues>(initial);
    const [submitting, setSubmitting] = useState(false);
    const avatarInput = useRef<HTMLInputElement>(null);
    const bannerInput = useRef<HTMLInputElement>(null);

    const set = <K extends keyof ProfileValues>(key: K, value: ProfileValues[K]) =>
        setValues((current) => ({ ...current, [key]: value }));

    // Previews of files that are not uploaded yet.
    const avatarPreview = useMemo(() => (values.avatar ? URL.createObjectURL(values.avatar) : null), [values.avatar]);
    const bannerPreview = useMemo(() => (values.banner ? URL.createObjectURL(values.banner) : null), [values.banner]);
    useEffect(() => () => avatarPreview ? URL.revokeObjectURL(avatarPreview) : undefined, [avatarPreview]);
    useEffect(() => () => bannerPreview ? URL.revokeObjectURL(bannerPreview) : undefined, [bannerPreview]);

    useEffect(() => () => clearFlashes(), []);

    const avatar = avatarPreview || (values.removeAvatar ? null : user.profile.avatarUrl);
    const banner = bannerPreview || (values.removeBanner ? null : user.profile.bannerUrl);
    const profileAccent = isColor(values.profileAccent) ? values.profileAccent : accent;
    const bannerColor1 = isColor(values.bannerColor1) ? values.bannerColor1 : DEFAULT_BANNER_COLOR;
    const bannerColor2 = isColor(values.bannerColor2) ? values.bannerColor2 : profileAccent;

    const pickFile = (type: 'avatar' | 'banner', file?: File) => {
        if (!file) return;
        clearFlashes();
        if (!IMAGE_TYPES.includes(file.type)) {
            addError(t('profile.invalid_type'));
            return;
        }
        if (file.size > uploadLimit * 1024 * 1024) {
            addError(t('profile.too_large', { limit: uploadLimit }));
            return;
        }
        if (type === 'avatar') {
            setValues((current) => ({ ...current, avatar: file, removeAvatar: false }));
        } else {
            setValues((current) => ({ ...current, banner: file, removeBanner: false, bannerStyle: 'image' }));
        }
    };

    const submit = () => {
        clearFlashes();
        setSubmitting(true);
        updateProfile(values)
            .then((response) => {
                updateUserData({
                    username: response.username,
                    nameFirst: response.nameFirst,
                    nameLast: response.nameLast,
                    profile: response.profile,
                });
                setValues((current) => ({
                    ...current,
                    avatar: null,
                    banner: null,
                    removeAvatar: false,
                    removeBanner: false,
                }));
                addFlash({ type: 'success', key: 'account:profile', message: t('profile.saved') });
            })
            .catch(clearAndAddHttpError)
            .then(() => setSubmitting(false));
    };

    const changed = JSON.stringify(values) !== JSON.stringify(initial);

    return (
        <>
            <h2 className={'hydra-section-title'}>{t('profile.title')}</h2>
            <FlashMessageRender byKey={'account:profile'} className={'mb-4'} />
            <div className={'hydra-profile-grid'}>
                <ProfileCard
                    uuid={user.uuid}
                    username={values.username || user.username}
                    name={`${values.nameFirst} ${values.nameLast}`.trim()}
                    avatar={avatar}
                    banner={banner}
                    bannerStyle={values.bannerStyle}
                    bannerColor1={bannerColor1}
                    bannerColor2={bannerColor2}
                    accent={profileAccent}
                    bio={values.bio}
                    online={values.showOnline}
                />
                <div className={'hydra-profile-stack'}>
                    <section className={'hydra-box'}>
                        <h3 className={'hydra-box-header'}>{t('profile.appearance')}</h3>
                        <div className={'hydra-box-body hydra-form-grid'}>
                            <div>
                                <Label>{t('profile.picture')}</Label>
                                <div className={'hydra-upload-row'}>
                                    <Button size={Button.Sizes.Small} onClick={() => avatarInput.current?.click()}>
                                        {avatar ? t('profile.replace_picture') : t('profile.upload_picture')}
                                    </Button>
                                    {avatar && (
                                        <Button.Text
                                            size={Button.Sizes.Small}
                                            onClick={() =>
                                                setValues((current) => ({
                                                    ...current,
                                                    avatar: null,
                                                    removeAvatar: true,
                                                }))
                                            }
                                        >
                                            {t('profile.remove_upload')}
                                        </Button.Text>
                                    )}
                                </div>
                                <p className={'hydra-help'}>{t('profile.upload_help', { limit: uploadLimit })}</p>
                                <input
                                    ref={avatarInput}
                                    type={'file'}
                                    accept={IMAGE_TYPES.join(',')}
                                    hidden
                                    onChange={(e) => {
                                        pickFile('avatar', e.currentTarget.files?.[0]);
                                        e.currentTarget.value = '';
                                    }}
                                />
                            </div>
                            <div>
                                <Label htmlFor={'profile_accent'}>{t('profile.accent')}</Label>
                                <ColorInput
                                    id={'profile_accent'}
                                    value={values.profileAccent}
                                    placeholder={accent}
                                    onChange={(value) => set('profileAccent', value)}
                                />
                            </div>
                        </div>
                    </section>
                    <section className={'hydra-box'}>
                        <h3 className={'hydra-box-header'}>{t('profile.visibility')}</h3>
                        <div className={'hydra-box-body'}>
                            <label className={'hydra-check'}>
                                <input
                                    type={'checkbox'}
                                    checked={values.showOnline}
                                    onChange={(e) => set('showOnline', e.currentTarget.checked)}
                                />
                                <span>
                                    {t('profile.show_online')}
                                    <small>{t('profile.show_online_help')}</small>
                                </span>
                            </label>
                        </div>
                    </section>
                </div>
            </div>
            <div className={'hydra-profile-columns'}>
                <section className={'hydra-box'}>
                    <h3 className={'hydra-box-header'}>{t('profile.banner')}</h3>
                    <div className={'hydra-box-body'}>
                        <Label htmlFor={'banner_style'}>{t('profile.banner_style')}</Label>
                        <Select
                            id={'banner_style'}
                            value={values.bannerStyle}
                            onChange={(e) => set('bannerStyle', e.currentTarget.value as 'gradient' | 'image')}
                        >
                            <option value={'gradient'}>{t('profile.banner_gradient')}</option>
                            <option value={'image'}>{t('profile.banner_image')}</option>
                        </Select>
                        {values.bannerStyle === 'gradient' ? (
                            <div className={'hydra-gradient-row'}>
                                <div>
                                    <Label htmlFor={'banner_color_1'}>{t('profile.first_color')}</Label>
                                    <ColorInput
                                        id={'banner_color_1'}
                                        value={values.bannerColor1}
                                        placeholder={DEFAULT_BANNER_COLOR}
                                        onChange={(value) => set('bannerColor1', value)}
                                    />
                                </div>
                                <button
                                    type={'button'}
                                    className={'hydra-icon-btn'}
                                    title={t('profile.swap_colors')}
                                    onClick={() =>
                                        setValues((current) => ({
                                            ...current,
                                            bannerColor1: current.bannerColor2,
                                            bannerColor2: current.bannerColor1,
                                        }))
                                    }
                                >
                                    <SwitchVerticalIcon
                                        className={'hydra-nav-icon'}
                                        style={{ transform: 'rotate(90deg)' }}
                                    />
                                </button>
                                <div>
                                    <Label htmlFor={'banner_color_2'}>{t('profile.second_color')}</Label>
                                    <ColorInput
                                        id={'banner_color_2'}
                                        value={values.bannerColor2}
                                        placeholder={profileAccent}
                                        onChange={(value) => set('bannerColor2', value)}
                                    />
                                </div>
                            </div>
                        ) : (
                            <div className={'mt-4'}>
                                <div className={'hydra-upload-row'}>
                                    <Button size={Button.Sizes.Small} onClick={() => bannerInput.current?.click()}>
                                        {banner ? t('profile.replace_banner') : t('profile.upload_banner')}
                                    </Button>
                                    {banner && (
                                        <Button.Text
                                            size={Button.Sizes.Small}
                                            onClick={() =>
                                                setValues((current) => ({
                                                    ...current,
                                                    banner: null,
                                                    removeBanner: true,
                                                }))
                                            }
                                        >
                                            {t('profile.remove_upload')}
                                        </Button.Text>
                                    )}
                                </div>
                                <p className={'hydra-help'}>{t('profile.banner_help', { limit: uploadLimit })}</p>
                                <input
                                    ref={bannerInput}
                                    type={'file'}
                                    accept={IMAGE_TYPES.join(',')}
                                    hidden
                                    onChange={(e) => {
                                        pickFile('banner', e.currentTarget.files?.[0]);
                                        e.currentTarget.value = '';
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </section>
                <section className={'hydra-box'}>
                    <h3 className={'hydra-box-header'}>{t('profile.about')}</h3>
                    <div className={'hydra-box-body'}>
                        <Label htmlFor={'bio'}>{t('profile.bio')}</Label>
                        <Textarea
                            id={'bio'}
                            rows={5}
                            maxLength={BIO_LIMIT}
                            value={values.bio}
                            placeholder={t('profile.bio_placeholder')}
                            onChange={(e) => set('bio', e.currentTarget.value)}
                        />
                        <p className={'hydra-help text-right'}>
                            {values.bio.length} / {BIO_LIMIT}
                        </p>
                    </div>
                </section>
            </div>
            <section className={'hydra-box mt-4'}>
                <h3 className={'hydra-box-header'}>{t('profile.details')}</h3>
                <div className={'hydra-box-body hydra-form-grid hydra-form-grid--3'}>
                    <div>
                        <Label htmlFor={'username'}>{t('profile.username')}</Label>
                        <Input
                            id={'username'}
                            value={values.username}
                            maxLength={191}
                            autoComplete={'off'}
                            onChange={(e) => set('username', e.currentTarget.value)}
                        />
                        <p className={'hydra-help'}>{t('profile.username_help')}</p>
                    </div>
                    <div>
                        <Label htmlFor={'name_first'}>{t('profile.first_name')}</Label>
                        <Input
                            id={'name_first'}
                            value={values.nameFirst}
                            maxLength={191}
                            onChange={(e) => set('nameFirst', e.currentTarget.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor={'name_last'}>{t('profile.last_name')}</Label>
                        <Input
                            id={'name_last'}
                            value={values.nameLast}
                            maxLength={191}
                            onChange={(e) => set('nameLast', e.currentTarget.value)}
                        />
                    </div>
                </div>
            </section>
            <div className={'hydra-save-bar'}>
                <SpinnerOverlay visible={submitting} />
                <Button.Text disabled={!changed || submitting} onClick={() => setValues(initial)}>
                    {t('profile.discard')}
                </Button.Text>
                <Button disabled={!changed || submitting} onClick={submit}>
                    {t('profile.save')}
                </Button>
            </div>
        </>
    );
};
