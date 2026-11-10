import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CheckCircle2, CircleAlert, Folder, Link2, X } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { Input } from '@/components/elements/inputs';
import { httpErrorToHuman } from '@/api/http';
import { cancelPull, FinishedPull, getPulls, RunningPull, startPull } from '@/api/server/files/pull';
import { isPathAllowed, normalizePath } from '@/plugins/useAllowedPaths';

// Hydra: "Download from link" in the file manager (addon "link_download").
// The node fetches the file in the background; while downloads run, their
// progress is polled, also when the dialog is closed.

interface Props {
    open: boolean;
    // Folder the dialog starts with, usually the open one.
    directory: string;
    // Known folders, offered as suggestions.
    folders: string[];
    onClose: () => void;
    // A download ended; the folder of the file is reloaded.
    onFinished: (path: string) => void;
}

const POLL_MS = 1500;

const fileName = (path: string) => path.split('/').filter(Boolean).pop() || path;

export default ({ open, directory, folders, onClose, onFinished }: Props) => {
    const { t } = useTranslation('client/files');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const allowedPaths = ServerContext.useStoreState((state) => state.server.allowedPaths);
    const [url, setUrl] = useState('');
    const [folder, setFolder] = useState(directory);
    const [name, setName] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [running, setRunning] = useState<RunningPull[]>([]);
    const [finished, setFinished] = useState<FinishedPull[]>([]);
    const [watching, setWatching] = useState(false);
    const seen = useRef<Set<string> | null>(null);

    useEffect(() => {
        if (!open) return;
        setFolder(directory);
        setError(null);
        setWatching(true);
    }, [open]);

    // Poll while the dialog is open or downloads are running.
    useEffect(() => {
        if (!watching) return;

        let stopped = false;
        let timer: ReturnType<typeof setTimeout>;

        const poll = () => {
            getPulls(uuid)
                .then((data) => {
                    if (stopped) return;
                    // Results already there when the dialog opened are not new.
                    if (seen.current === null) {
                        seen.current = new Set(data.finished.map((item) => item.identifier));
                    } else {
                        data.finished
                            .filter((item) => !seen.current!.has(item.identifier))
                            .forEach((item) => {
                                seen.current!.add(item.identifier);
                                if (item.success && item.path) onFinished(item.path);
                            });
                    }
                    setRunning(data.downloads);
                    setFinished(data.finished.slice().reverse());
                    if (!open && data.downloads.length === 0) {
                        setWatching(false);
                        return;
                    }
                    timer = setTimeout(poll, POLL_MS);
                })
                .catch(() => {
                    if (!stopped) timer = setTimeout(poll, POLL_MS * 4);
                });
        };
        poll();

        return () => {
            stopped = true;
            clearTimeout(timer);
        };
    }, [watching, open]);

    const target = normalizePath(folder || '/');
    const validUrl = /^https?:\/\/\S+$/i.test(url.trim());
    const validName = !name || (!/[\\/\0]/.test(name) && name !== '.' && name !== '..');
    const allowed = isPathAllowed(allowedPaths, target);

    const submit = () => {
        if (!validUrl || !validName || !allowed) return;

        setBusy(true);
        setError(null);
        startPull(uuid, url.trim(), target, name.trim() || undefined)
            .then(() => {
                setUrl('');
                setName('');
                setWatching(true);
            })
            .catch((e) => setError(httpErrorToHuman(e)))
            .then(() => setBusy(false));
    };

    const errorText = (code: string | null) => {
        if (!code) return t('link.errors.other', { error: '' });
        if (code.startsWith('status:')) return t('link.errors.status', { status: code.slice(7) });
        const known = ['cancelled', 'internal', 'resolve', 'no_length', 'redirects', 'disk', 'timeout'];
        return known.includes(code) ? t(`link.errors.${code}`) : t('link.errors.other', { error: code });
    };

    return (
        <Dialog open={open} onClose={onClose} title={t('link.title')} description={t('link.help')}>
            <form
                className={'space-y-4'}
                onSubmit={(event) => {
                    event.preventDefault();
                    submit();
                }}
            >
                {error && <p className={'rounded bg-red-500/15 px-3 py-2 text-sm text-red-300'}>{error}</p>}
                <div>
                    <label className={'mb-1 block text-xs text-gray-300'} htmlFor={'linkUrl'}>
                        {t('link.url')}
                    </label>
                    <Input.Text
                        id={'linkUrl'}
                        autoFocus
                        value={url}
                        placeholder={'https://example.com/backup.tar.gz'}
                        onChange={(event: React.ChangeEvent<HTMLInputElement>) => setUrl(event.currentTarget.value)}
                        className={'font-mono'}
                    />
                    {url.trim() !== '' && !validUrl && (
                        <p className={'mt-1 text-xs text-red-400'}>{t('link.invalid_url')}</p>
                    )}
                </div>
                <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                    <div>
                        <label className={'mb-1 block text-xs text-gray-300'} htmlFor={'linkFolder'}>
                            {t('link.folder')}
                        </label>
                        <div className={'relative'}>
                            <Folder
                                className={
                                    'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400'
                                }
                            />
                            <Input.Text
                                id={'linkFolder'}
                                list={'linkFolders'}
                                value={folder}
                                onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                                    setFolder(event.currentTarget.value)
                                }
                                className={'!pl-9 font-mono'}
                            />
                            <datalist id={'linkFolders'}>
                                {folders.map((item) => (
                                    <option key={item} value={item} />
                                ))}
                            </datalist>
                        </div>
                        <p className={`mt-1 text-xs ${allowed ? 'text-gray-400' : 'text-red-400'}`}>
                            {allowed ? t('link.folder_help') : t('link.folder_forbidden')}
                        </p>
                    </div>
                    <div>
                        <label className={'mb-1 block text-xs text-gray-300'} htmlFor={'linkName'}>
                            {t('link.name')}
                        </label>
                        <Input.Text
                            id={'linkName'}
                            value={name}
                            placeholder={t('link.name_placeholder')}
                            onChange={(event: React.ChangeEvent<HTMLInputElement>) =>
                                setName(event.currentTarget.value)
                            }
                            className={'font-mono'}
                        />
                        {!validName && <p className={'mt-1 text-xs text-red-400'}>{t('link.invalid_name')}</p>}
                    </div>
                </div>
                {/* Lets Enter submit the form. */}
                <button type={'submit'} className={'hidden'} />
            </form>

            {(running.length > 0 || finished.length > 0) && (
                <div className={'mt-5 space-y-2'}>
                    <p className={'text-xs uppercase tracking-wide text-gray-400'}>{t('link.downloads')}</p>
                    {running.map((item) => (
                        <div key={item.identifier} className={'rounded-md border border-gray-700 bg-gray-900/40 p-3'}>
                            <div className={'mb-2 flex items-center justify-between gap-3 text-sm'}>
                                <span className={'flex items-center gap-2 text-gray-200'}>
                                    <Link2 className={'h-4 w-4 text-accent-400'} />
                                    {t('link.running')}
                                </span>
                                <span className={'flex items-center gap-2'}>
                                    <span className={'font-mono text-xs text-gray-400'}>
                                        {Math.floor(item.progress * 100)} %
                                    </span>
                                    <button
                                        type={'button'}
                                        title={t('link.cancel')}
                                        className={'rounded p-1 text-gray-400 hover:bg-gray-700 hover:text-red-400'}
                                        onClick={() => cancelPull(uuid, item.identifier).catch(() => undefined)}
                                    >
                                        <X className={'h-4 w-4'} />
                                    </button>
                                </span>
                            </div>
                            <div className={'h-2 overflow-hidden rounded-full bg-gray-700'}>
                                <div
                                    className={'h-full rounded-full bg-accent-500 transition-[width] duration-500'}
                                    style={{ width: `${Math.max(2, item.progress * 100)}%` }}
                                />
                            </div>
                        </div>
                    ))}
                    {finished.map((item) => (
                        <div
                            key={item.identifier}
                            className={
                                'flex items-start gap-3 rounded-md border border-gray-700 bg-gray-900/40 px-3 py-2'
                            }
                        >
                            {item.success ? (
                                <CheckCircle2 className={'mt-0.5 h-4 w-4 shrink-0 text-accent-400'} />
                            ) : (
                                <CircleAlert className={'mt-0.5 h-4 w-4 shrink-0 text-yellow-400'} />
                            )}
                            <div className={'min-w-0'}>
                                <p className={'truncate font-mono text-sm text-gray-100'} title={item.path}>
                                    {item.path ? fileName(item.path) : t('link.unknown_file')}
                                </p>
                                <p className={`text-xs ${item.success ? 'text-gray-400' : 'text-yellow-300'}`}>
                                    {item.success ? t('link.done', { path: item.path }) : errorText(item.error)}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('link.close')}</Button.Text>
                <Button onClick={submit} disabled={busy || !validUrl || !validName || !allowed}>
                    {t('link.start')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};
