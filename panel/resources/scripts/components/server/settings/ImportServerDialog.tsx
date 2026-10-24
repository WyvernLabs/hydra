import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { CheckCircle2, FolderOpen, KeyRound, XCircle } from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useFlashKey } from '@/plugins/useFlash';
import { useStoreState } from '@/state/hooks';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import useRemainingTime, { formatRemaining } from '@/plugins/useRemainingTime';
import { bytesToString } from '@/lib/formatters';
import {
    cancelImport,
    getImportStatus,
    ImportLogin,
    ImportMode,
    ImportStatus,
    ImportTest,
    startImport,
    testImport,
    toImportStatus,
} from '@/api/server/import';

// Hydra: copies the files of another server over SFTP onto this one (addon
// "importer"): login, test, transfer with progress and a log of the files.

type Phase = 'form' | 'running' | 'done' | 'failed';

const MAX_LINES = 500;

// Errors of the node are English; known ones are shown in the panel language.
const ERRORS: [RegExp, string][] = [
    [/could not log in/i, 'login'],
    [/host key/i, 'host_key'],
    [/could not be found/i, 'not_found'],
    [/could not connect/i, 'connect'],
    [/private network/i, 'internal'],
    [/private key/i, 'key'],
    [/source folder could not be read/i, 'source'],
    [/no folder/i, 'source'],
    [/disk space/i, 'disk'],
    [/too many files/i, 'too_many'],
    [/cancelled/i, 'cancelled'],
    [/running already/i, 'running'],
];

const errorText = (t: (key: string, options?: any) => string, message: string): string => {
    const known = ERRORS.find(([pattern]) => pattern.test(message));
    return known ? t(`errors.${known[1]}`) : t('errors.other', { error: message });
};

// A circle that draws itself, then the tick inside it.
const AnimatedCheck = () => (
    <svg viewBox={'0 0 52 52'} className={'hydra-import-check h-20 w-20'} aria-hidden>
        <circle cx={26} cy={26} r={24} fill={'none'} />
        <path fill={'none'} d={'M14 27 l8 8 l16 -17'} />
    </svg>
);

export default ({ open, onClose }: { open: boolean; onClose: () => void }) => {
    const { t } = useTranslation('client/import');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const profiles = useStoreState((state) => state.settings.data!.importer.profiles);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:import');

    const [host, setHost] = useState('');
    const [port, setPort] = useState(22);
    const [username, setUsername] = useState('');
    const [auth, setAuth] = useState<'password' | 'key'>('password');
    const [password, setPassword] = useState('');
    const [privateKey, setPrivateKey] = useState('');
    const [source, setSource] = useState('');
    const [target, setTarget] = useState('/');
    const [mode, setMode] = useState<ImportMode>('keep');
    const [profileName, setProfileName] = useState('');

    const [phase, setPhase] = useState<Phase>('form');
    const [busy, setBusy] = useState(false);
    const [tested, setTested] = useState<ImportTest | null>(null);
    const [status, setStatus] = useState<ImportStatus | null>(null);
    const [lines, setLines] = useState<string[]>([]);
    const log = useRef<HTMLDivElement>(null);

    const login: ImportLogin = {
        host: host.trim(),
        port,
        username: username.trim(),
        password: auth === 'password' ? password : undefined,
        privateKey: auth === 'key' ? privateKey : undefined,
        source: source.trim(),
    };

    // Another login has to be tested again.
    useEffect(() => setTested(null), [host, port, username, auth, password, privateKey, source]);

    // A running import continues while the window is closed; it shows up again.
    useEffect(() => {
        if (!open) return;
        clearFlashes();
        getImportStatus(uuid)
            .then((current) => {
                if (current.state === 'listing' || current.state === 'copying') {
                    setStatus(current);
                    setPhase('running');
                } else if (phase !== 'form') {
                    setPhase('form');
                }
            })
            .catch(() => undefined);
    }, [open]);

    useWebsocketEvent(SocketEvent.HYDRA_IMPORT_PROGRESS, (data) => {
        try {
            const parsed = JSON.parse(data);
            const next = toImportStatus(parsed.status || {});
            setStatus(next);
            if (parsed.lines?.length || parsed.skipped) {
                setLines((current) => {
                    const added = [
                        ...(parsed.skipped ? [t('log_skipped', { count: parsed.skipped })] : []),
                        ...(parsed.lines as string[]),
                    ];
                    return [...current, ...added].slice(-MAX_LINES);
                });
            }
            if (next.state === 'done') setPhase('done');
            if (next.state === 'failed') setPhase('failed');
            if (next.state === 'listing' || next.state === 'copying') setPhase('running');
        } catch (error) {
            console.warn(error);
        }
    });

    // The log follows the newest file.
    useEffect(() => {
        if (log.current) log.current.scrollTop = log.current.scrollHeight;
    }, [lines]);

    const remaining = useRemainingTime(status?.done || 0, status?.total || 0, phase === 'running');

    const test = () => {
        clearFlashes();
        setBusy(true);
        testImport(uuid, login)
            .then((result) => setTested(result))
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    const start = () => {
        if (!tested) return;
        clearFlashes();
        setBusy(true);
        setLines([]);
        setStatus(null);
        startImport(uuid, login, { hostKey: tested.fingerprint, target, mode, profileName })
            .then(() => setPhase((current) => (current === 'form' ? 'running' : current)))
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    const cancel = () => {
        cancelImport(uuid).catch((error) => clearAndAddHttpError(error));
    };

    const percent = status && status.total > 0 ? Math.min(100, (status.done / status.total) * 100) : null;

    const field = (id: string, label: string, input: React.ReactNode, help?: string) => (
        <div>
            <Label htmlFor={id}>{label}</Label>
            {input}
            {help && <p className={'mt-1 text-xs text-gray-400'}>{help}</p>}
        </div>
    );

    return (
        <Dialog open={open} onClose={() => !busy && onClose()} preventExternalClose={busy} wide title={t('title')}>
            <FlashMessageRender byKey={'server:import'} className={'mb-3'} />

            {phase === 'form' && (
                <div className={'space-y-4'}>
                    <p className={'text-sm text-gray-400'}>{t('intro')}</p>
                    <div className={'grid grid-cols-1 gap-4 sm:grid-cols-4'}>
                        <div className={'sm:col-span-3'}>
                            {field(
                                'importHost',
                                t('host'),
                                <Input
                                    id={'importHost'}
                                    value={host}
                                    onChange={(event) => setHost(event.currentTarget.value)}
                                    placeholder={'node.example.com'}
                                />
                            )}
                        </div>
                        {field(
                            'importPort',
                            t('port'),
                            <Input
                                id={'importPort'}
                                type={'number'}
                                min={1}
                                max={65535}
                                value={port}
                                onChange={(event) => setPort(Number(event.currentTarget.value) || 22)}
                            />
                        )}
                    </div>
                    <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                        {field(
                            'importUser',
                            t('username'),
                            <Input
                                id={'importUser'}
                                value={username}
                                onChange={(event) => setUsername(event.currentTarget.value)}
                                autoComplete={'off'}
                            />
                        )}
                        <div>
                            <div className={'flex items-center justify-between'}>
                                <Label htmlFor={'importSecret'}>
                                    {auth === 'password' ? t('password') : t('private_key')}
                                </Label>
                                <button
                                    type={'button'}
                                    className={
                                        'mb-1 inline-flex items-center gap-1 text-xs text-primary-400 hover:text-primary-300'
                                    }
                                    onClick={() => setAuth(auth === 'password' ? 'key' : 'password')}
                                >
                                    <KeyRound className={'h-3 w-3'} />
                                    {auth === 'password' ? t('use_key') : t('use_password')}
                                </button>
                            </div>
                            {auth === 'password' ? (
                                <Input
                                    id={'importSecret'}
                                    type={'password'}
                                    value={password}
                                    onChange={(event) => setPassword(event.currentTarget.value)}
                                    autoComplete={'new-password'}
                                />
                            ) : (
                                <textarea
                                    id={'importSecret'}
                                    value={privateKey}
                                    onChange={(event) => setPrivateKey(event.currentTarget.value)}
                                    rows={3}
                                    spellCheck={false}
                                    placeholder={'-----BEGIN OPENSSH PRIVATE KEY-----'}
                                    className={
                                        'w-full rounded border border-gray-600 bg-gray-700 p-2 font-mono text-xs text-gray-100 outline-none focus:border-primary-400'
                                    }
                                />
                            )}
                        </div>
                    </div>
                    <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                        {field(
                            'importSource',
                            t('source'),
                            <Input
                                id={'importSource'}
                                value={source}
                                onChange={(event) => setSource(event.currentTarget.value)}
                                placeholder={t('source_placeholder')}
                            />,
                            t('source_help')
                        )}
                        {field(
                            'importTarget',
                            t('target'),
                            <Input
                                id={'importTarget'}
                                value={target}
                                onChange={(event) => setTarget(event.currentTarget.value)}
                                placeholder={'/'}
                            />,
                            t('target_help')
                        )}
                    </div>

                    <div>
                        <Label>{t('mode')}</Label>
                        <div className={'grid grid-cols-1 gap-2 sm:grid-cols-3'}>
                            {(['keep', 'wipe', ...(profiles ? ['profile'] : [])] as ImportMode[]).map((item) => (
                                <label
                                    key={item}
                                    className={classNames(
                                        'cursor-pointer rounded-lg border p-3 text-sm transition-colors',
                                        mode === item
                                            ? item === 'wipe'
                                                ? 'border-red-500 bg-red-500/10'
                                                : 'border-primary-500 bg-primary-500/10'
                                            : 'border-gray-700 bg-gray-900/40 hover:border-gray-500'
                                    )}
                                >
                                    <input
                                        type={'radio'}
                                        name={'importMode'}
                                        className={'sr-only'}
                                        checked={mode === item}
                                        onChange={() => setMode(item)}
                                    />
                                    <span className={'block font-medium text-gray-100'}>{t(`modes.${item}`)}</span>
                                    <span className={'mt-0.5 block text-xs text-gray-400'}>
                                        {t(`modes.${item}_help`)}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {mode === 'profile' && (
                            <div className={'mt-3'}>
                                {field(
                                    'importProfile',
                                    t('profile_name'),
                                    <Input
                                        id={'importProfile'}
                                        value={profileName}
                                        maxLength={60}
                                        onChange={(event) => setProfileName(event.currentTarget.value)}
                                        placeholder={t('profile_placeholder')}
                                    />
                                )}
                            </div>
                        )}
                    </div>

                    {tested && (
                        <div className={'rounded-lg border border-green-600/50 bg-green-600/10 p-3 text-sm'}>
                            <p className={'flex items-center gap-2 font-medium text-green-300'}>
                                <CheckCircle2 className={'h-4 w-4'} />
                                {t('test_ok', { folder: tested.folder, count: tested.entries })}
                            </p>
                            {tested.names.length > 0 && (
                                <p className={'mt-2 flex flex-wrap gap-1'}>
                                    {tested.names.map((name) => (
                                        <span
                                            key={name}
                                            className={
                                                'inline-flex items-center gap-1 rounded bg-gray-900/60 px-1.5 py-0.5 font-mono text-2xs text-gray-300'
                                            }
                                        >
                                            {name.endsWith('/') && <FolderOpen className={'h-3 w-3'} />}
                                            {name}
                                        </span>
                                    ))}
                                    {tested.entries > tested.names.length && (
                                        <span className={'px-1 text-2xs text-gray-400'}>
                                            {t('test_more', { count: tested.entries - tested.names.length })}
                                        </span>
                                    )}
                                </p>
                            )}
                            <p className={'mt-2 break-all font-mono text-2xs text-gray-400'}>
                                {t('host_key', { key: tested.fingerprint })}
                            </p>
                        </div>
                    )}
                </div>
            )}

            {phase === 'running' && (
                <div className={'space-y-4'}>
                    <div>
                        <div className={'mb-2 flex items-baseline justify-between gap-3 text-sm'}>
                            <span className={'font-medium text-gray-100'}>
                                {status?.state === 'copying' ? t('copying') : t('listing')}
                            </span>
                            <span className={'text-gray-400'}>
                                {percent !== null ? `${Math.floor(percent)} %` : ''}
                            </span>
                        </div>
                        <div className={'relative h-2.5 overflow-hidden rounded-full bg-gray-700'}>
                            {percent !== null ? (
                                <div
                                    className={'h-full rounded-full bg-primary-500 transition-[width] duration-300'}
                                    style={{ width: `${percent}%` }}
                                />
                            ) : (
                                <div
                                    className={'hydra-import-indeterminate h-full w-1/3 rounded-full bg-primary-500'}
                                />
                            )}
                        </div>
                        {status && status.state === 'copying' && (
                            <p className={'mt-2 flex flex-wrap justify-between gap-2 text-xs text-gray-400'}>
                                <span>
                                    {t('files_progress', { done: status.files, total: status.filesTotal })} ·{' '}
                                    {bytesToString(status.done)} / {bytesToString(status.total)}
                                </span>
                                <span>{formatRemaining(t, remaining)}</span>
                            </p>
                        )}
                    </div>
                    <div
                        ref={log}
                        className={
                            'h-48 overflow-y-auto rounded-lg border border-gray-700 bg-black/60 p-3 font-mono text-2xs leading-relaxed text-gray-300'
                        }
                        aria-live={'off'}
                    >
                        {lines.length === 0 ? (
                            <span className={'text-gray-500'}>{t('log_waiting')}</span>
                        ) : (
                            lines.map((line, index) => (
                                <div key={index} className={'truncate'}>
                                    <span className={'text-primary-400'}>+</span> {line}
                                </div>
                            ))
                        )}
                    </div>
                    <p className={'text-xs text-gray-400'}>{t('background')}</p>
                </div>
            )}

            {phase === 'done' && (
                <div className={'flex flex-col items-center py-6 text-center'}>
                    <AnimatedCheck />
                    <h3 className={'mt-4 text-lg font-semibold text-gray-50'}>{t('done_title')}</h3>
                    <p className={'mt-1 text-sm text-gray-400'}>
                        {t('done_text', {
                            count: status?.filesTotal || 0,
                            size: bytesToString(status?.total || 0),
                        })}
                    </p>
                </div>
            )}

            {phase === 'failed' && (
                <div className={'flex flex-col items-center py-6 text-center'}>
                    <XCircle className={'h-16 w-16 text-red-400'} />
                    <h3 className={'mt-4 text-lg font-semibold text-gray-50'}>{t('failed_title')}</h3>
                    <p className={'mt-1 max-w-md text-sm text-gray-400'}>{errorText(t, status?.error || '')}</p>
                    {status && status.files > 0 && (
                        <p className={'mt-1 text-xs text-gray-500'}>
                            {t('failed_partial', { done: status.files, total: status.filesTotal })}
                        </p>
                    )}
                </div>
            )}

            <Dialog.Footer>
                {phase === 'form' && (
                    <>
                        <Button.Text onClick={onClose} disabled={busy}>
                            {t('cancel')}
                        </Button.Text>
                        <Button.Text
                            onClick={test}
                            disabled={busy || !login.host || !login.username || !(password || privateKey)}
                        >
                            {busy && !tested ? t('testing') : t('test')}
                        </Button.Text>
                        {mode === 'wipe' ? (
                            <Button.Danger onClick={start} disabled={busy || !tested}>
                                {t('start')}
                            </Button.Danger>
                        ) : (
                            <Button onClick={start} disabled={busy || !tested}>
                                {busy && tested ? t('starting') : t('start')}
                            </Button>
                        )}
                    </>
                )}
                {phase === 'running' && (
                    <>
                        <Button.Text className={'!text-red-400 hover:!text-red-300'} onClick={cancel}>
                            {t('stop')}
                        </Button.Text>
                        <Button.Text onClick={onClose}>{t('hide')}</Button.Text>
                    </>
                )}
                {phase === 'failed' && (
                    <>
                        <Button.Text onClick={onClose}>{t('close')}</Button.Text>
                        <Button onClick={() => setPhase('form')}>{t('back')}</Button>
                    </>
                )}
                {phase === 'done' && (
                    <Button
                        onClick={() => {
                            setPhase('form');
                            onClose();
                        }}
                    >
                        {t('close')}
                    </Button>
                )}
            </Dialog.Footer>
        </Dialog>
    );
};
