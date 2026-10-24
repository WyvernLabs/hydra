import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Gamepad2, Trash2 } from 'lucide-react';
import { ServerContext } from '@/state/server';
import useFlash, { useFlashKey } from '@/plugins/useFlash';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Spinner from '@/components/elements/Spinner';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import getServer from '@/api/server/getServer';
import {
    deleteGameProfile,
    GameEgg,
    GameLibrary,
    GameProfile,
    getGameLibrary,
    loadGameProfile,
    switchGame,
} from '@/api/server/games';

// Hydra: game library (addon "games"). Switch the game with one click, keep
// the old files as a profile and load profiles back. Eggs are grouped by
// nest; hovering the button blurs and dims the picture of the egg.

type Action = { type: 'switch'; egg: GameEgg } | { type: 'load'; profile: GameProfile };

const Picture = ({ image, name }: { image: string | null; name: string }) =>
    image ? (
        <img
            src={image}
            alt={''}
            className={
                'absolute inset-0 h-full w-full object-cover transition duration-300 peer-hover:scale-105 peer-hover:blur-sm peer-hover:brightness-50'
            }
        />
    ) : (
        <div
            className={
                'absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-700 to-gray-900 transition duration-300 peer-hover:blur-sm peer-hover:brightness-50'
            }
        >
            <span className={'px-4 text-center font-header text-2xl text-gray-500'}>{name}</span>
        </div>
    );

const Tile = ({
    title,
    subtitle,
    image,
    button,
    disabled,
    onClick,
    children,
}: {
    title: string;
    subtitle?: string;
    image: string | null;
    button: string;
    disabled?: boolean;
    onClick: () => void;
    children?: React.ReactNode;
}) => (
    <div className={'overflow-hidden rounded-lg border border-gray-700 bg-gray-800 shadow'}>
        <div className={'flex items-center gap-2 border-b border-gray-700 px-4 py-3'}>
            <div className={'min-w-0 flex-1 text-center'}>
                <p className={'truncate font-medium text-gray-50'}>{title}</p>
                {subtitle && <p className={'truncate text-xs text-gray-400'}>{subtitle}</p>}
            </div>
            {children}
        </div>
        <div className={'relative m-3 aspect-video overflow-hidden rounded'}>
            {/* The button comes first, so hovering it can blur the picture after it. */}
            <button
                type={'button'}
                disabled={disabled}
                onClick={onClick}
                className={classNames(
                    'peer absolute left-1/2 top-1/2 z-10 -translate-x-1/2 -translate-y-1/2 rounded px-5 py-2 text-sm font-semibold uppercase tracking-wide shadow-lg transition',
                    disabled
                        ? 'cursor-default bg-gray-900/80 text-gray-300'
                        : 'bg-accent-500 text-white hover:bg-accent-400 focus:outline-none focus:ring-2 focus:ring-accent-300'
                )}
            >
                {button}
            </button>
            <Picture image={image} name={title} />
        </div>
    </div>
);

const SwitchDialog = ({
    action,
    library,
    currentName,
    onClose,
    onDone,
}: {
    action: Action | null;
    library: GameLibrary;
    currentName: string;
    onClose: () => void;
    onDone: () => void;
}) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:games');
    const canKeep = library.profiles.length < library.maxProfiles;
    const [keep, setKeep] = useState(canKeep);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        setKeep(canKeep);
        setName(`${currentName} (${formatDate(new Date(), 'date')})`);
    }, [action]);

    const target = action?.type === 'switch' ? action.egg.name : action?.profile.name || '';

    const submit = () => {
        if (!action) return;
        clearFlashes();
        setBusy(true);
        const request =
            action.type === 'switch'
                ? switchGame(uuid, action.egg.id, keep, name)
                : loadGameProfile(uuid, action.profile.id, keep, name);
        request
            .then(() => {
                onClose();
                onDone();
            })
            .catch((error) => {
                onClose();
                clearAndAddHttpError(error);
            })
            .then(() => setBusy(false));
    };

    return (
        <Dialog
            open={!!action}
            onClose={() => !busy && onClose()}
            title={t(action?.type === 'load' ? 'games.load_title' : 'games.switch_title', { name: target })}
        >
            <SpinnerOverlay visible={busy} size={'large'}>
                {t('games.working')}
            </SpinnerOverlay>
            <p className={'text-sm text-gray-300'}>
                {t(action?.type === 'load' ? 'games.load_text' : 'games.switch_text', { current: currentName })}
            </p>
            <div className={'mt-4 space-y-2'}>
                <label
                    className={classNames(
                        'flex cursor-pointer items-start gap-3 rounded border p-3',
                        keep ? 'border-accent-500 bg-accent-500/10' : 'border-gray-600 bg-gray-700',
                        !canKeep && 'cursor-not-allowed opacity-50'
                    )}
                >
                    <input
                        type={'radio'}
                        className={'mt-1'}
                        checked={keep}
                        disabled={!canKeep}
                        onChange={() => setKeep(true)}
                    />
                    <span>
                        <span className={'block text-sm font-medium text-gray-50'}>{t('games.keep')}</span>
                        <span className={'block text-xs text-gray-400'}>
                            {canKeep ? t('games.keep_help') : t('games.keep_limit', { limit: library.maxProfiles })}
                        </span>
                    </span>
                </label>
                {keep && (
                    <div className={'pl-8'}>
                        <Input
                            value={name}
                            maxLength={191}
                            onChange={(e) => setName(e.currentTarget.value)}
                            placeholder={t('games.profile_name')}
                        />
                    </div>
                )}
                <label
                    className={classNames(
                        'flex cursor-pointer items-start gap-3 rounded border p-3',
                        !keep ? 'border-red-500 bg-red-500/10' : 'border-gray-600 bg-gray-700'
                    )}
                >
                    <input type={'radio'} className={'mt-1'} checked={!keep} onChange={() => setKeep(false)} />
                    <span>
                        <span className={'block text-sm font-medium text-gray-50'}>{t('games.discard')}</span>
                        <span className={'block text-xs text-gray-400'}>{t('games.discard_help')}</span>
                    </span>
                </label>
            </div>
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={busy}>
                    {t('client/common:actions.cancel')}
                </Button.Text>
                {keep ? (
                    <Button onClick={submit} disabled={busy}>
                        {t(action?.type === 'load' ? 'games.load_button' : 'games.switch_button')}
                    </Button>
                ) : (
                    <Button.Danger onClick={submit} disabled={busy}>
                        {t(action?.type === 'load' ? 'games.load_button' : 'games.switch_button')}
                    </Button.Danger>
                )}
            </Dialog.Footer>
        </Dialog>
    );
};

export default () => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const eggName = ServerContext.useStoreState((state) => state.server.data!.eggName || '');
    const setServer = ServerContext.useStoreActions((actions) => actions.server.setServer);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:games');
    const { addFlash } = useFlash();
    const [library, setLibrary] = useState<GameLibrary | null>(null);
    const [action, setAction] = useState<Action | null>(null);
    const [removing, setRemoving] = useState<GameProfile | null>(null);
    const [busy, setBusy] = useState(false);

    const load = () =>
        getGameLibrary(uuid)
            .then(setLibrary)
            .catch((error) => clearAndAddHttpError(error));

    useEffect(() => {
        load();
    }, []);

    const done = () => {
        addFlash({
            key: 'server:games',
            type: 'success',
            message: t(action?.type === 'load' ? 'games.loaded' : 'games.switched'),
        });
        load();
        getServer(uuid)
            .then(([server]) => setServer(server))
            .catch(() => undefined);
    };

    const remove = () => {
        if (!removing) return;
        clearFlashes();
        setBusy(true);
        deleteGameProfile(uuid, removing.id)
            .then(() => load())
            .catch((error) => clearAndAddHttpError(error))
            .then(() => {
                setBusy(false);
                setRemoving(null);
            });
    };

    return (
        <ServerContentBlock title={t('games.title')} showFlashKey={'server:games'}>
            {!library ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    <SwitchDialog
                        action={action}
                        library={library}
                        currentName={eggName}
                        onClose={() => setAction(null)}
                        onDone={done}
                    />
                    <Dialog.Confirm
                        open={!!removing}
                        onClose={() => !busy && setRemoving(null)}
                        title={t('games.delete_title', { name: removing?.name })}
                        confirm={t('games.delete_button')}
                        onConfirmed={remove}
                    >
                        {t('games.delete_text')}
                    </Dialog.Confirm>

                    {library.profiles.length > 0 && (
                        <section className={'mb-10'}>
                            <h2
                                className={
                                    'mb-4 border-b border-gray-600 pb-2 text-center font-header text-lg text-gray-200'
                                }
                            >
                                {t('games.profiles', { count: library.profiles.length, limit: library.maxProfiles })}
                            </h2>
                            <div className={'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3'}>
                                {library.profiles.map((profile) => (
                                    <Tile
                                        key={profile.id}
                                        title={profile.name}
                                        subtitle={`${profile.egg || t('games.unknown_game')} · ${bytesToString(
                                            profile.size
                                        )} · ${formatDate(profile.createdAt, 'date')}`}
                                        image={profile.image}
                                        button={t('games.load')}
                                        disabled={!profile.egg}
                                        onClick={() => setAction({ type: 'load', profile })}
                                    >
                                        <button
                                            type={'button'}
                                            title={t('games.delete_button')}
                                            className={'p-1 text-gray-400 hover:text-red-400'}
                                            onClick={() => setRemoving(profile)}
                                        >
                                            <Trash2 className={'h-4 w-4'} />
                                        </button>
                                    </Tile>
                                ))}
                            </div>
                        </section>
                    )}

                    {library.nests.length === 0 && (
                        <p className={'text-center text-sm text-gray-400'}>{t('games.none')}</p>
                    )}
                    {library.nests.map((nest) => (
                        <section key={nest.id} className={'mb-10'}>
                            <h2
                                className={
                                    'mb-4 border-b border-gray-600 pb-2 text-center font-header text-lg text-gray-200'
                                }
                            >
                                {nest.name}
                            </h2>
                            <div className={'grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3'}>
                                {nest.eggs.map((egg) => {
                                    const current = egg.id === library.current;
                                    return (
                                        <Tile
                                            key={egg.id}
                                            title={egg.name}
                                            image={egg.image}
                                            button={current ? t('games.current') : t('games.install')}
                                            disabled={current}
                                            onClick={() => setAction({ type: 'switch', egg })}
                                        >
                                            {current && <Gamepad2 className={'h-4 w-4 text-accent-400'} />}
                                        </Tile>
                                    );
                                })}
                            </div>
                        </section>
                    ))}
                </>
            )}
        </ServerContentBlock>
    );
};
