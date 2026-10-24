import React, { useContext, useEffect, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import useFlash from '@/plugins/useFlash';
import Can from '@/components/elements/Can';
import CreateBackupButton from '@/components/server/backups/CreateBackupButton';
import FlashMessageRender from '@/components/FlashMessageRender';
import BackupRow from '@/components/server/backups/BackupRow';
import tw from 'twin.macro';
import getServerBackups, { Context as ServerBackupContext } from '@/api/swr/getServerBackups';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Pagination from '@/components/elements/Pagination';
import { useTranslation } from 'react-i18next';
import { useStoreState } from 'easy-peasy';
import { Button } from '@/components/elements/button/index';
import { bytesToString } from '@/lib/formatters';
import { SftpImportDialog, SftpSettingsDialog } from '@/components/server/backups/SftpBackupDialogs';

// Hydra: how much of the backup space is used.
const StorageBar = ({ used, limit }: { used: number; limit: number }) => {
    const { t } = useTranslation('client/server');
    const percent = limit > 0 ? Math.min(100, (used / limit) * 100) : 0;

    return (
        <div className={'mb-4 rounded-lg border border-gray-700 bg-gray-800 p-4'}>
            <div className={'flex flex-wrap items-baseline justify-between gap-2 text-sm'}>
                <span className={'text-gray-200'}>{t('backups.storage.title')}</span>
                <span className={'text-gray-300'}>
                    {limit > 0
                        ? t('backups.storage.usage', { used: bytesToString(used), limit: bytesToString(limit) })
                        : t('backups.storage.none')}
                </span>
            </div>
            {limit > 0 && (
                <div className={'mt-2 h-2 overflow-hidden rounded-full bg-gray-700'}>
                    <div
                        className={`h-full rounded-full ${percent >= 90 ? 'bg-red-500' : 'bg-accent-500'}`}
                        style={{ width: `${percent}%` }}
                    />
                </div>
            )}
        </div>
    );
};

const BackupContainer = () => {
    const { page, setPage } = useContext(ServerBackupContext);
    const { t } = useTranslation('client/server');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { data: backups, error, isValidating, mutate } = getServerBackups();
    const sftpAddon = useStoreState((state) => state.settings.data!.sftpBackups);
    const [dialog, setDialog] = useState<'sftp' | 'import' | null>(null);

    useEffect(() => {
        if (!error) {
            clearFlashes('backups');

            return;
        }

        clearAndAddHttpError({ error, key: 'backups' });
    }, [error]);

    // Hydra: while a backup is being made the list is reloaded every few
    // seconds, so a finished backup shows up even if the websocket event was missed.
    const running = !!backups && backups.items.some((backup) => backup.completedAt === null);
    useEffect(() => {
        if (!running) return;
        const timer = setInterval(() => mutate(), 3000);
        return () => clearInterval(timer);
    }, [running]);

    if (!backups || (error && isValidating)) {
        return <Spinner size={'large'} centered />;
    }

    const panelSpace = backups.storageLimit > 0;
    const canCreate = (panelSpace && backups.storageUsed < backups.storageLimit) || backups.ownSftp;

    return (
        <ServerContentBlock title={t('backups.title')}>
            <FlashMessageRender byKey={'backups'} css={tw`mb-4`} />
            <StorageBar used={backups.storageUsed} limit={backups.storageLimit} />
            <Pagination data={backups} onPageSelect={setPage}>
                {({ items }) =>
                    !items.length ? (
                        // Don't show any error messages if the server has no backups and the user cannot
                        // create additional ones for the server.
                        !panelSpace && !backups.ownSftp ? null : (
                            <p css={tw`text-center text-sm text-neutral-300`}>
                                {page > 1 ? t('backups.none_on_page') : t('backups.none')}
                            </p>
                        )
                    ) : (
                        items.map((backup, index) => (
                            <BackupRow key={backup.uuid} backup={backup} css={index > 0 ? tw`mt-2` : undefined} />
                        ))
                    )
                }
            </Pagination>
            {!panelSpace && !backups.ownSftp && (
                <p css={tw`text-center text-sm text-neutral-300`}>{t('backups.disabled')}</p>
            )}
            <div className={'mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end'}>
                {sftpAddon && (
                    <>
                        <Can action={'backup.sftp'}>
                            <Button.Text className={'w-full sm:w-auto'} onClick={() => setDialog('sftp')}>
                                {t('backups.sftp.button')}
                            </Button.Text>
                        </Can>
                        {backups.ownSftp && (
                            <Can action={'backup.create'}>
                                <Button.Text className={'w-full sm:w-auto'} onClick={() => setDialog('import')}>
                                    {t('backups.import.open')}
                                </Button.Text>
                            </Can>
                        )}
                    </>
                )}
                <Can action={'backup.create'}>
                    {canCreate && (
                        <CreateBackupButton
                            panelSpace={panelSpace && backups.storageUsed < backups.storageLimit}
                            ownSftp={backups.ownSftp}
                        />
                    )}
                </Can>
            </div>
            <SftpSettingsDialog open={dialog === 'sftp'} onClose={() => setDialog(null)} />
            <SftpImportDialog open={dialog === 'import'} onClose={() => setDialog(null)} />
        </ServerContentBlock>
    );
};

export default () => {
    const [page, setPage] = useState<number>(1);
    return (
        <ServerBackupContext.Provider value={{ page, setPage }}>
            <BackupContainer />
        </ServerBackupContext.Provider>
    );
};
