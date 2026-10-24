import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { File, Folder, Loader2, RotateCcw, Trash2 } from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { Input } from '@/components/elements/inputs';
import { deleteTrash, getTrash, restoreTrash, TrashListing } from '@/api/server/files/trash';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString } from '@/lib/formatters';
import { formatDate, formatDistanceToNow } from '@/lib/date';
import { useIde } from '@/components/server/files/ide/context';
import { dirname } from '@/components/server/files/ide/paths';

// Hydra: trash of the file manager. Files deleted in the panel stay here until
// they expire, unless they are restored or deleted for good earlier.

export default ({
    open,
    onClose,
    onChanged,
}: {
    open: boolean;
    onClose: () => void;
    onChanged: (count: number) => void;
}) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const [listing, setListing] = useState<TrashListing | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [selected, setSelected] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [confirmEmpty, setConfirmEmpty] = useState(false);

    const load = () => {
        setError(null);
        getTrash(ide.uuid)
            .then((data) => {
                setListing(data);
                setSelected((current) => current.filter((id) => data.entries.some((entry) => entry.id === id)));
                onChanged(data.entries.length);
            })
            .catch((error) => setError(httpErrorToHuman(error)));
    };

    useEffect(() => {
        if (open) {
            setListing(null);
            setSelected([]);
            load();
        }
    }, [open]);

    const act = (action: () => Promise<unknown>, after?: () => void) => {
        setBusy(true);
        setError(null);
        action()
            .then(() => {
                after?.();
                load();
            })
            .catch((error) => setError(httpErrorToHuman(error)))
            .then(() => setBusy(false));
    };

    const restore = (ids: string[]) =>
        act(
            () => restoreTrash(ide.uuid, ids),
            () => {
                const folders = (listing?.entries || [])
                    .filter((entry) => ids.includes(entry.id))
                    .map((entry) => dirname(entry.path));
                ide.refresh(...Array.from(new Set(folders)));
            }
        );

    const entries = listing?.entries || [];
    const allSelected = entries.length > 0 && selected.length === entries.length;

    return (
        <>
            <Dialog open={open && !confirmEmpty} onClose={onClose} title={t('trash.title')} wide>
                <p className={'text-sm text-gray-400'}>
                    {listing && !listing.enabled ? t('trash.disabled') : t('trash.help', { count: listing?.days ?? 7 })}
                </p>
                {error && <p className={'mt-3 rounded bg-red-500/10 px-3 py-2 text-sm text-red-300'}>{error}</p>}
                <div className={'mt-3 max-h-[50vh] overflow-y-auto rounded border border-gray-700'}>
                    {!listing ? (
                        <div className={'flex justify-center p-6'}>
                            <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                        </div>
                    ) : entries.length === 0 ? (
                        <p className={'p-6 text-center text-sm text-gray-500'}>{t('trash.empty')}</p>
                    ) : (
                        <table className={'w-full text-left text-sm'}>
                            <thead className={'sticky top-0 bg-gray-700 text-xs text-gray-300'}>
                                <tr>
                                    <th className={'w-8 px-2 py-1.5'}>
                                        <Input.Checkbox
                                            checked={allSelected}
                                            onChange={() =>
                                                setSelected(allSelected ? [] : entries.map((entry) => entry.id))
                                            }
                                            aria-label={t('trash.select_all')}
                                        />
                                    </th>
                                    <th className={'px-2 py-1.5 font-medium'}>{t('trash.name')}</th>
                                    <th className={'hidden px-2 py-1.5 font-medium sm:table-cell'}>
                                        {t('trash.deleted')}
                                    </th>
                                    <th className={'hidden px-2 py-1.5 font-medium md:table-cell'}>
                                        {t('trash.expires')}
                                    </th>
                                    <th className={'px-2 py-1.5 text-right font-medium'}>{t('trash.size')}</th>
                                    <th className={'w-16'} />
                                </tr>
                            </thead>
                            <tbody>
                                {entries.map((entry) => {
                                    const checked = selected.includes(entry.id);
                                    return (
                                        <tr
                                            key={entry.id}
                                            className={classNames(
                                                'border-t border-gray-700',
                                                checked && 'bg-primary-500/10'
                                            )}
                                        >
                                            <td className={'px-2 py-1.5'}>
                                                <Input.Checkbox
                                                    checked={checked}
                                                    onChange={() =>
                                                        setSelected(
                                                            checked
                                                                ? selected.filter((id) => id !== entry.id)
                                                                : [...selected, entry.id]
                                                        )
                                                    }
                                                    aria-label={entry.name}
                                                />
                                            </td>
                                            <td className={'max-w-0 px-2 py-1.5'}>
                                                <div className={'flex items-center gap-2'}>
                                                    {entry.directory ? (
                                                        <Folder className={'h-4 w-4 shrink-0 text-primary-400'} />
                                                    ) : (
                                                        <File className={'h-4 w-4 shrink-0 text-gray-400'} />
                                                    )}
                                                    <div className={'min-w-0'}>
                                                        <p className={'truncate text-gray-100'}>{entry.name}</p>
                                                        <p className={'truncate font-mono text-2xs text-gray-500'}>
                                                            {dirname(entry.path)}
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td
                                                className={
                                                    'hidden whitespace-nowrap px-2 py-1.5 text-xs text-gray-400 sm:table-cell'
                                                }
                                                title={formatDate(entry.deletedAt)}
                                            >
                                                {formatDistanceToNow(entry.deletedAt, { addSuffix: true })}
                                            </td>
                                            <td
                                                className={
                                                    'hidden whitespace-nowrap px-2 py-1.5 text-xs text-gray-400 md:table-cell'
                                                }
                                                title={formatDate(entry.expiresAt)}
                                            >
                                                {formatDistanceToNow(entry.expiresAt, { addSuffix: true })}
                                            </td>
                                            <td
                                                className={
                                                    'whitespace-nowrap px-2 py-1.5 text-right text-xs text-gray-400'
                                                }
                                            >
                                                {bytesToString(entry.size, 1)}
                                            </td>
                                            <td className={'whitespace-nowrap px-2 py-1.5 text-right'}>
                                                {ide.can.create && (
                                                    <button
                                                        type={'button'}
                                                        title={t('trash.restore')}
                                                        disabled={busy}
                                                        onClick={() => restore([entry.id])}
                                                        className={
                                                            'rounded p-1 text-gray-400 hover:bg-gray-600 hover:text-gray-100'
                                                        }
                                                    >
                                                        <RotateCcw className={'h-4 w-4'} />
                                                    </button>
                                                )}
                                                {ide.can.delete && (
                                                    <button
                                                        type={'button'}
                                                        title={t('trash.delete')}
                                                        disabled={busy}
                                                        onClick={() => act(() => deleteTrash(ide.uuid, [entry.id]))}
                                                        className={
                                                            'rounded p-1 text-gray-400 hover:bg-red-500/20 hover:text-red-400'
                                                        }
                                                    >
                                                        <Trash2 className={'h-4 w-4'} />
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    )}
                </div>
                <Dialog.Footer>
                    {ide.can.delete && entries.length > 0 && (
                        <Button.Text onClick={() => setConfirmEmpty(true)} disabled={busy} className={'mr-auto'}>
                            {t('trash.empty_button')}
                        </Button.Text>
                    )}
                    {selected.length > 0 && ide.can.delete && (
                        <Button.Danger
                            disabled={busy}
                            onClick={() =>
                                act(
                                    () => deleteTrash(ide.uuid, selected),
                                    () => setSelected([])
                                )
                            }
                        >
                            {t('trash.delete_selected', { count: selected.length })}
                        </Button.Danger>
                    )}
                    {selected.length > 0 && ide.can.create && (
                        <Button disabled={busy} onClick={() => restore(selected)}>
                            {t('trash.restore_selected', { count: selected.length })}
                        </Button>
                    )}
                    {selected.length === 0 && <Button.Text onClick={onClose}>{t('dialogs.close')}</Button.Text>}
                </Dialog.Footer>
            </Dialog>
            <Dialog.Confirm
                open={open && confirmEmpty}
                onClose={() => setConfirmEmpty(false)}
                title={t('trash.empty_title')}
                confirm={t('trash.empty_button')}
                onConfirmed={() => {
                    setConfirmEmpty(false);
                    act(() => deleteTrash(ide.uuid, 'all'));
                }}
            >
                {t('trash.empty_text', { count: entries.length })}
            </Dialog.Confirm>
        </>
    );
};
