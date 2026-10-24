import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faArchive, faEllipsisH, faLock } from '@fortawesome/free-solid-svg-icons';
import { formatDate, formatDistanceToNow } from '@/lib/date';
import Spinner from '@/components/elements/Spinner';
import { bytesToString } from '@/lib/formatters';
import Can from '@/components/elements/Can';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import BackupContextMenu from '@/components/server/backups/BackupContextMenu';
import tw from 'twin.macro';
import GreyRowBox from '@/components/elements/GreyRowBox';
import getServerBackups from '@/api/swr/getServerBackups';
import { ServerBackup } from '@/api/server/types';
import { SocketEvent } from '@/components/server/events';
import { useTranslation } from 'react-i18next';
import useRemainingTime, { formatRemaining } from '@/plugins/useRemainingTime';

interface Props {
    backup: ServerBackup;
    className?: string;
}

// Hydra: progress of a running backup as reported by the daemon.
interface Progress {
    phase: 'archive' | 'upload';
    done: number;
    total: number;
}

const BackupProgress = ({ progress }: { progress: Progress | null }) => {
    const { t } = useTranslation('client/server');
    const seconds = useRemainingTime(progress?.done || 0, progress?.total || 0, !!progress);
    const percent =
        progress && progress.total > 0 ? Math.min(100, Math.floor((progress.done / progress.total) * 100)) : 0;

    return (
        <div className={'mt-2 w-full'}>
            <div className={'h-1.5 overflow-hidden rounded-full bg-gray-700'}>
                <div
                    className={`h-full rounded-full bg-accent-500 transition-[width] duration-500 ${
                        progress ? '' : 'animate-pulse'
                    }`}
                    style={{ width: progress ? `${percent}%` : '100%' }}
                />
            </div>
            <div className={'mt-1 flex flex-wrap items-center justify-between gap-x-3 text-xs text-gray-400'}>
                <span>
                    {progress
                        ? `${t(`backups.progress.${progress.phase}`)} · ${percent} %`
                        : t('backups.progress.preparing')}
                </span>
                {progress && <span>{formatRemaining(t, seconds)}</span>}
            </div>
        </div>
    );
};

export default ({ backup, className }: Props) => {
    const { mutate } = getServerBackups();
    const { t } = useTranslation('client/server');
    const [progress, setProgress] = useState<Progress | null>(null);

    useWebsocketEvent(`${SocketEvent.BACKUP_COMPLETED}:${backup.uuid}` as SocketEvent, (data) => {
        try {
            const parsed = JSON.parse(data);

            mutate(
                (data) => ({
                    ...data,
                    items: data.items.map((b) =>
                        b.uuid !== backup.uuid
                            ? b
                            : {
                                  ...b,
                                  isSuccessful: parsed.is_successful,
                                  checksum: (parsed.checksum_type || '') + ':' + (parsed.checksum || ''),
                                  bytes: parsed.file_size || 0,
                                  completedAt: new Date(),
                              }
                    ),
                }),
                false
            );
            // Hydra: reloads the backup space and the reason of a failure.
            mutate();
        } catch (e) {
            console.warn(e);
        }
    });

    useWebsocketEvent(SocketEvent.HYDRA_BACKUP_PROGRESS, (data) => {
        if (backup.completedAt !== null) return;
        try {
            const parsed = JSON.parse(data);
            if (parsed.uuid === backup.uuid) {
                setProgress({ phase: parsed.phase, done: parsed.done, total: parsed.total });
            }
        } catch (e) {
            console.warn(e);
        }
    });

    const failure = (() => {
        if (!backup.error) return t('backups.errors.error');
        if (backup.error === 'storage_full') return t('backups.errors.storage_full');
        if (backup.error.startsWith('sftp: ')) return t('backups.errors.sftp', { message: backup.error.slice(6) });

        return t('backups.errors.error');
    })();

    return (
        <GreyRowBox css={tw`flex-wrap md:flex-nowrap items-center`} className={className}>
            <div css={tw`flex items-center truncate w-full md:flex-1`}>
                <div css={tw`mr-4`}>
                    {backup.completedAt !== null ? (
                        backup.isLocked ? (
                            <FontAwesomeIcon icon={faLock} css={tw`text-yellow-500`} />
                        ) : (
                            <FontAwesomeIcon icon={faArchive} css={tw`text-neutral-300`} />
                        )
                    ) : (
                        <Spinner size={'small'} />
                    )}
                </div>
                <div css={tw`flex flex-col truncate w-full`}>
                    <div css={tw`flex items-center text-sm mb-1`}>
                        {backup.completedAt !== null && !backup.isSuccessful && (
                            <span
                                css={tw`bg-red-500 py-px px-2 rounded-full text-white text-xs uppercase border border-red-600 mr-2`}
                            >
                                {t('backups.failed')}
                            </span>
                        )}
                        {backup.location === 'own_sftp' && (
                            <span
                                className={
                                    'mr-2 shrink-0 rounded-full border border-accent-600 bg-accent-500/20 px-2 py-px text-xs text-accent-200'
                                }
                                title={t('backups.location.own_sftp_help')}
                            >
                                {t('backups.location.own_sftp')}
                            </span>
                        )}
                        <p css={tw`break-words truncate`}>{backup.name}</p>
                        {backup.completedAt !== null && backup.isSuccessful && (
                            <span css={tw`ml-3 text-neutral-300 text-xs font-extralight hidden sm:inline`}>
                                {bytesToString(backup.bytes)}
                            </span>
                        )}
                    </div>
                    {backup.completedAt === null ? (
                        <BackupProgress progress={progress} />
                    ) : !backup.isSuccessful ? (
                        <p className={'mt-1 whitespace-normal text-xs text-red-300 md:mt-0'}>{failure}</p>
                    ) : (
                        <p css={tw`mt-1 md:mt-0 text-xs text-neutral-400 font-mono truncate`}>{backup.checksum}</p>
                    )}
                </div>
            </div>
            <div css={tw`flex-1 md:flex-none md:w-48 mt-4 md:mt-0 md:ml-8 md:text-center`}>
                <p title={formatDate(backup.createdAt, 'datetimeSeconds')} css={tw`text-sm`}>
                    {formatDistanceToNow(backup.createdAt, { includeSeconds: true, addSuffix: true })}
                </p>
                <p css={tw`text-2xs text-neutral-500 uppercase mt-1`}>{t('backups.created')}</p>
            </div>
            <Can action={['backup.download', 'backup.restore', 'backup.delete']} matchAny>
                <div css={tw`mt-4 md:mt-0 ml-6`} style={{ marginRight: '-0.5rem' }}>
                    {!backup.completedAt ? (
                        <div css={tw`p-2 invisible`}>
                            <FontAwesomeIcon icon={faEllipsisH} />
                        </div>
                    ) : (
                        <BackupContextMenu backup={backup} />
                    )}
                </div>
            </Can>
        </GreyRowBox>
    );
};
