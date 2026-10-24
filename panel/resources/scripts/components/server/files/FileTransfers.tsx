import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Download, FileArchive, PackageOpen, Upload, X } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { FileTransfer, FileTransferKind } from '@/state/server/files';
import { SocketEvent } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { bytesToString } from '@/lib/formatters';
import Portal from '@/components/elements/Portal';
import useRemainingTime, { formatRemaining } from '@/plugins/useRemainingTime';

// Hydra: list of running uploads, downloads, archives and extractions in the
// bottom right corner, with a progress bar and the time that is left.

const icons: Record<FileTransferKind, React.ComponentType<{ className?: string }>> = {
    upload: Upload,
    download: Download,
    archive: FileArchive,
    unarchive: PackageOpen,
};

// How long finished entries stay visible, in milliseconds.
const KEEP_DONE = 4000;
const KEEP_FAILED = 10000;
// A download the daemon reports nothing about (e.g. an older daemon, or the
// websocket is not connected) is dropped after this time.
const STALE_DOWNLOAD = 30000;
const MAX_VISIBLE = 4;

interface ProgressEvent {
    id: string;
    done: number;
    total: number;
    finished: boolean;
    failed: boolean;
}

const TransferRow = ({ transfer }: { transfer: FileTransfer }) => {
    const { t } = useTranslation('client/server');
    const cancelTransfer = ServerContext.useStoreActions((actions) => actions.files.cancelTransfer);
    const removeTransfer = ServerContext.useStoreActions((actions) => actions.files.removeTransfer);

    const Icon = icons[transfer.kind];
    const percent = transfer.total > 0 ? Math.min(100, Math.floor((transfer.loaded / transfer.total) * 100)) : 0;
    const running = transfer.status === 'running';
    const indeterminate = running && !transfer.started;
    // Archives and extractions count bytes the user never sees, only the percentage is shown.
    const showBytes = transfer.kind === 'upload' || transfer.kind === 'download';
    const seconds = useRemainingTime(transfer.loaded, transfer.total, running && transfer.started);

    let status: string;
    if (transfer.status === 'done') {
        status = t('files.transfers.done');
    } else if (transfer.status === 'failed') {
        status = t('files.transfers.failed');
    } else {
        status = formatRemaining(t, seconds);
    }

    // Uploads can be cancelled, the browser handles downloads and the daemon
    // cannot stop archives once started.
    const onClose = transfer.kind === 'upload' && running ? () => cancelTransfer(transfer.id) : undefined;

    return (
        <div className={'rounded-lg border border-gray-700 bg-gray-800/95 p-3 shadow-lg backdrop-blur'}>
            <div className={'flex items-center gap-2'}>
                <Icon
                    className={classNames('h-4 w-4 shrink-0', {
                        'text-accent-400': running,
                        'text-green-400': transfer.status === 'done',
                        'text-red-400': transfer.status === 'failed',
                    })}
                />
                <div className={'min-w-0 flex-1'}>
                    <p className={'truncate text-sm font-medium text-gray-50'} title={transfer.name}>
                        {transfer.name}
                    </p>
                    <p className={'text-xs text-gray-400'}>
                        {t(`files.transfers.${transfer.kind}`)}
                        {running && !indeterminate && ` · ${percent} %`}
                    </p>
                </div>
                {(onClose || !running) && (
                    <button
                        type={'button'}
                        className={'shrink-0 rounded p-1 text-gray-400 transition-colors hover:text-gray-100'}
                        title={onClose ? t('files.transfers.cancel') : t('files.transfers.dismiss')}
                        aria-label={onClose ? t('files.transfers.cancel') : t('files.transfers.dismiss')}
                        onClick={onClose || (() => removeTransfer(transfer.id))}
                    >
                        <X className={'h-4 w-4'} />
                    </button>
                )}
            </div>
            <div className={'mt-2 h-1.5 overflow-hidden rounded-full bg-gray-700'}>
                <div
                    className={classNames('h-full rounded-full transition-[width] duration-500', {
                        'bg-accent-500': running,
                        'animate-pulse': indeterminate,
                        'bg-green-500': transfer.status === 'done',
                        'bg-red-500': transfer.status === 'failed',
                    })}
                    style={{ width: indeterminate || !running ? '100%' : `${percent}%` }}
                />
            </div>
            <div className={'mt-1.5 flex items-center justify-between gap-2 text-xs text-gray-400'}>
                <span className={'truncate'}>
                    {showBytes && running && transfer.total > 0
                        ? `${bytesToString(transfer.loaded)} / ${bytesToString(transfer.total)}`
                        : ''}
                </span>
                <span
                    className={classNames('shrink-0', {
                        'text-green-400': transfer.status === 'done',
                        'text-red-400': transfer.status === 'failed',
                    })}
                >
                    {status}
                </span>
            </div>
        </div>
    );
};

export default () => {
    const { t } = useTranslation('client/server');
    const transfers = ServerContext.useStoreState((state) => Object.values(state.files.transfers));
    const { setTransferProgress, finishTransfer, removeTransfer } = ServerContext.useStoreActions(
        (actions) => actions.files
    );
    const [, setTick] = useState(0);

    useWebsocketEvent(SocketEvent.HYDRA_FILE_PROGRESS, (data: string) => {
        let event: ProgressEvent;
        try {
            event = JSON.parse(data);
        } catch (error) {
            return;
        }

        if (event.finished) {
            finishTransfer({ id: event.id, failed: event.failed });
        } else {
            setTransferProgress({ id: event.id, loaded: event.done, total: event.total });
        }
    });

    // Removes finished entries, checked every second.
    const now = Date.now();
    const active = transfers.length > 0;
    useEffect(() => {
        if (!active) return;

        const interval = setInterval(() => setTick((value) => value + 1), 1000);
        return () => clearInterval(interval);
    }, [active]);

    useEffect(() => {
        transfers.forEach((transfer) => {
            const age = now - transfer.updatedAt;
            if (
                (transfer.status === 'done' && age > KEEP_DONE) ||
                (transfer.status === 'failed' && age > KEEP_FAILED) ||
                (transfer.kind === 'download' && transfer.status === 'running' && age > STALE_DOWNLOAD)
            ) {
                removeTransfer(transfer.id);
            }
        });
    });

    if (!active) return null;

    const visible = transfers.slice(0, MAX_VISIBLE);

    return (
        <Portal>
            <div
                className={
                    'pointer-events-none fixed bottom-4 left-4 right-4 z-[60] flex flex-col items-stretch gap-2 sm:left-auto sm:w-80'
                }
            >
                {visible.map((transfer) => (
                    <div key={transfer.id} className={'pointer-events-auto'}>
                        <TransferRow transfer={transfer} />
                    </div>
                ))}
                {transfers.length > visible.length && (
                    <p
                        className={
                            'pointer-events-auto rounded-lg border border-gray-700 bg-gray-800/95 px-3 py-1.5 text-center text-xs text-gray-300'
                        }
                    >
                        {t('files.transfers.more', { count: transfers.length - visible.length })}
                    </p>
                )}
            </div>
        </Portal>
    );
};
