import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import {
    AlertTriangle,
    Archive,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    GitMerge,
    Loader2,
    Trash2,
    X,
} from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import {
    ConfigStash,
    ConfigStashEntry,
    deleteStoredConfig,
    getConfigStash,
    getConfigStashContent,
    storeConfig,
} from '@/api/server/configStash';
import { ConfigSyncError, formatPath, SyncResult, syncConfig } from '@/lib/configSync';
import { basename } from '@/components/server/files/ide/paths';

// Hydra: config sync (addon "config_sync") in the editor of YAML files. The
// storage dialog keeps configs of the server; "Sync" writes the values of a
// stored config into the open one and the report lists what happened.

const FLASH = 'files:config-sync';

interface DialogProps {
    open: boolean;
    uuid: string;
    path: string;
    canSync: boolean;
    // Current content of the editor.
    content: () => string;
    onClose: () => void;
    onSynced: (result: SyncResult, entry: ConfigStashEntry) => void;
}

export const ConfigStashDialog = ({ open, uuid, path, canSync, content, onClose, onSynced }: DialogProps) => {
    const { t } = useTranslation('client/files');
    const { clearFlashes, clearAndAddHttpError, addError } = useFlash();
    const [stash, setStash] = useState<ConfigStash | null>(null);
    const [name, setName] = useState('');
    const [busy, setBusy] = useState<string | null>(null);
    const [confirm, setConfirm] = useState<number | null>(null);

    const load = () =>
        getConfigStash(uuid)
            .then(setStash)
            .catch((error) => clearAndAddHttpError({ key: FLASH, error }));

    useEffect(() => {
        if (!open) return;
        clearFlashes(FLASH);
        setConfirm(null);
        setName(`${basename(path)} (${formatDate(new Date(), 'datetime')})`);
        load();
    }, [open, path]);

    const store = () => {
        clearFlashes(FLASH);
        setBusy('store');
        storeConfig(uuid, { name: name.trim(), path, content: content() })
            .then(() => load())
            .catch((error) => clearAndAddHttpError({ key: FLASH, error }))
            .then(() => setBusy(null));
    };

    const sync = (entry: ConfigStashEntry) => {
        clearFlashes(FLASH);
        setBusy(`sync-${entry.id}`);
        getConfigStashContent(uuid, entry.id)
            .then((old) => {
                try {
                    onSynced(syncConfig(old, content()), entry);
                } catch (error) {
                    if (error instanceof ConfigSyncError) {
                        const reason = error.message === 'not_a_map' ? t('sync.not_a_map') : error.message;
                        addError({
                            key: FLASH,
                            message:
                                error.which === 'old'
                                    ? t('sync.old_invalid', { reason })
                                    : t('sync.new_invalid', { reason }),
                        });
                    } else {
                        addError({ key: FLASH, message: (error as Error).message });
                    }
                }
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH, error }))
            .then(() => setBusy(null));
    };

    const remove = (entry: ConfigStashEntry) => {
        clearFlashes(FLASH);
        setBusy(`delete-${entry.id}`);
        deleteStoredConfig(uuid, entry.id)
            .then(() => {
                setConfirm(null);
                return load();
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH, error }))
            .then(() => setBusy(null));
    };

    // Configs of this file first.
    const entries = stash ? [...stash.entries].sort((a, b) => Number(b.path === path) - Number(a.path === path)) : [];
    const full = !!stash && stash.entries.length >= stash.maxEntries;

    return (
        <Dialog open={open} onClose={onClose} wide title={t('sync.title')} description={t('sync.help')}>
            <FlashMessageRender byKey={FLASH} className={'mb-3'} />

            <div className={'mb-4 rounded-lg border border-gray-600 bg-gray-800 p-3'}>
                <p className={'mb-2 flex items-center gap-1.5 text-sm font-medium text-gray-100'}>
                    <Archive className={'h-4 w-4'} />
                    {t('sync.store_title')}
                </p>
                <div className={'flex flex-wrap gap-2'}>
                    <input
                        value={name}
                        maxLength={64}
                        aria-label={t('sync.store_name')}
                        placeholder={t('sync.store_name')}
                        onChange={(event) => setName(event.currentTarget.value)}
                        className={
                            'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-1.5 text-sm text-gray-100 outline-none focus:border-primary-400'
                        }
                    />
                    <Button size={Button.Sizes.Small} onClick={store} disabled={!!busy || full || !stash}>
                        {busy === 'store' && <Loader2 className={'mr-1.5 h-4 w-4 animate-spin'} />}
                        {t('sync.store')}
                    </Button>
                </div>
                <p className={'mt-1.5 text-2xs text-gray-400'}>
                    {full ? t('sync.full', { max: stash!.maxEntries }) : t('sync.store_help')}
                </p>
            </div>

            {!stash ? (
                <div className={'flex justify-center py-6'}>
                    <Loader2 className={'h-5 w-5 animate-spin text-gray-400'} />
                </div>
            ) : entries.length === 0 ? (
                <p className={'py-6 text-center text-sm text-gray-400'}>{t('sync.empty')}</p>
            ) : (
                <ul className={'max-h-[45vh] space-y-2 overflow-y-auto pr-1'}>
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className={classNames(
                                'flex flex-wrap items-center gap-3 rounded-lg border bg-gray-800 px-3 py-2',
                                entry.path === path ? 'border-primary-500/60' : 'border-gray-700'
                            )}
                        >
                            <div className={'min-w-0 flex-1'}>
                                <p className={'truncate text-sm text-gray-100'} title={entry.name}>
                                    {entry.name}
                                    {entry.path === path && (
                                        <span
                                            className={
                                                'ml-2 rounded bg-primary-500/20 px-1.5 py-px text-2xs text-primary-200'
                                            }
                                        >
                                            {t('sync.same_file')}
                                        </span>
                                    )}
                                </p>
                                <p className={'truncate font-mono text-2xs text-gray-400'} title={entry.path}>
                                    {entry.path} · {bytesToString(entry.size, 1)} · {formatDate(entry.createdAt)}
                                    {entry.createdBy && ` · ${entry.createdBy}`}
                                </p>
                            </div>
                            {confirm === entry.id ? (
                                <div className={'flex items-center gap-2'}>
                                    <span className={'text-xs text-red-300'}>{t('sync.delete_confirm')}</span>
                                    <Button.Text size={Button.Sizes.Small} onClick={() => setConfirm(null)}>
                                        {t('sync.cancel')}
                                    </Button.Text>
                                    <Button.Danger
                                        size={Button.Sizes.Small}
                                        disabled={!!busy}
                                        onClick={() => remove(entry)}
                                    >
                                        {t('sync.delete')}
                                    </Button.Danger>
                                </div>
                            ) : (
                                <div className={'flex items-center gap-1'}>
                                    {canSync && (
                                        <Button size={Button.Sizes.Small} disabled={!!busy} onClick={() => sync(entry)}>
                                            {busy === `sync-${entry.id}` ? (
                                                <Loader2 className={'mr-1.5 h-4 w-4 animate-spin'} />
                                            ) : (
                                                <GitMerge className={'mr-1.5 h-4 w-4'} />
                                            )}
                                            {t('sync.sync')}
                                        </Button>
                                    )}
                                    <button
                                        type={'button'}
                                        title={t('sync.delete')}
                                        aria-label={t('sync.delete')}
                                        onClick={() => setConfirm(entry.id)}
                                        className={'rounded p-1.5 text-gray-400 hover:bg-red-500/20 hover:text-red-400'}
                                    >
                                        <Trash2 className={'h-4 w-4'} />
                                    </button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            {!canSync && <p className={'mt-3 text-xs text-gray-400'}>{t('sync.read_only')}</p>}

            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('sync.close')}</Button.Text>
            </Dialog.Footer>
        </Dialog>
    );
};

const PathList = ({ paths, onPick }: { paths: string[][]; onPick?: (index: number) => void }) => (
    <ul className={'mt-1 max-h-32 overflow-y-auto font-mono text-2xs'}>
        {paths.map((path, index) => (
            <li key={index}>
                {onPick ? (
                    <button type={'button'} onClick={() => onPick(index)} className={'text-left hover:underline'}>
                        {formatPath(path)}
                    </button>
                ) : (
                    formatPath(path)
                )}
            </li>
        ))}
    </ul>
);

interface ReportProps {
    result: SyncResult;
    source: string;
    onJump: (line: number) => void;
    onClose: () => void;
}

// What the last sync did, shown above the editor until it is closed.
export const SyncReport = ({ result, source, onJump, onClose }: ReportProps) => {
    const { t } = useTranslation('client/files');
    const [showReplaced, setShowReplaced] = useState(false);
    const [showAdded, setShowAdded] = useState(false);

    return (
        <div className={'max-h-[40%] shrink-0 overflow-y-auto border-b border-gray-700 bg-gray-800 px-3 py-2 text-xs'}>
            <div className={'flex items-start gap-2'}>
                <CheckCircle2 className={'mt-0.5 h-4 w-4 shrink-0 text-green-400'} />
                <div className={'min-w-0 flex-1 text-gray-200'}>
                    <p>
                        {t('sync.report', {
                            source,
                            replaced: result.replaced.length,
                            unchanged: result.unchanged,
                        })}{' '}
                        {result.replaced.length > 0 ? t('sync.report_save') : t('sync.report_nothing')}
                    </p>
                    {result.rewritten && <p className={'mt-1 text-yellow-300'}>{t('sync.rewritten')}</p>}
                    {result.replaced.length > 0 && (
                        <button
                            type={'button'}
                            onClick={() => setShowReplaced((v) => !v)}
                            className={'mt-1 flex items-center gap-1 text-gray-400 hover:text-gray-200'}
                        >
                            {showReplaced ? (
                                <ChevronDown className={'h-3 w-3'} />
                            ) : (
                                <ChevronRight className={'h-3 w-3'} />
                            )}
                            {t('sync.replaced_list', { count: result.replaced.length })}
                        </button>
                    )}
                    {showReplaced && (
                        <div className={'text-green-300'}>
                            <PathList
                                paths={result.replaced}
                                onPick={(index) => result.lines[index] && onJump(result.lines[index][0])}
                            />
                        </div>
                    )}
                    {result.added.length > 0 && (
                        <button
                            type={'button'}
                            onClick={() => setShowAdded((v) => !v)}
                            className={'mt-1 flex items-center gap-1 text-gray-400 hover:text-gray-200'}
                        >
                            {showAdded ? <ChevronDown className={'h-3 w-3'} /> : <ChevronRight className={'h-3 w-3'} />}
                            {t('sync.added_list', { count: result.added.length })}
                        </button>
                    )}
                    {showAdded && (
                        <div className={'text-gray-300'}>
                            <PathList paths={result.added} />
                        </div>
                    )}
                </div>
                <button
                    type={'button'}
                    title={t('sync.dismiss')}
                    aria-label={t('sync.dismiss')}
                    onClick={onClose}
                    className={'rounded p-1 text-gray-400 hover:bg-gray-700 hover:text-gray-100'}
                >
                    <X className={'h-3.5 w-3.5'} />
                </button>
            </div>
            {result.missing.length > 0 && (
                <div className={'mt-2 rounded border border-red-500/40 bg-red-500/10 px-2.5 py-2 text-red-200'}>
                    <p className={'flex items-center gap-1.5 font-medium'}>
                        <AlertTriangle className={'h-3.5 w-3.5'} />
                        {t('sync.missing', { count: result.missing.length })}
                    </p>
                    <PathList paths={result.missing} />
                </div>
            )}
        </div>
    );
};
