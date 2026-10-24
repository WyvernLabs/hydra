import React, { useState } from 'react';
import {
    faBoxOpen,
    faCloudDownloadAlt,
    faEllipsisH,
    faLock,
    faTrashAlt,
    faUnlock,
} from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import DropdownMenu, { DropdownButtonRow } from '@/components/elements/DropdownMenu';
import getBackupDownloadUrl from '@/api/server/backups/getBackupDownloadUrl';
import useFlash from '@/plugins/useFlash';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import deleteBackup from '@/api/server/backups/deleteBackup';
import Can from '@/components/elements/Can';
import tw from 'twin.macro';
import getServerBackups from '@/api/swr/getServerBackups';
import { ServerBackup } from '@/api/server/types';
import { ServerContext } from '@/state/server';
import Input from '@/components/elements/Input';
import { restoreServerBackup } from '@/api/server/backups';
import http, { httpErrorToHuman } from '@/api/http';
import { Dialog } from '@/components/elements/dialog';
import { useTranslation } from 'react-i18next';

interface Props {
    backup: ServerBackup;
}

export default ({ backup }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const suspended = ServerContext.useStoreState((state) => state.server.data!.status === 'suspended');
    const { t } = useTranslation('client/server');
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);
    const [modal, setModal] = useState('');
    const [loading, setLoading] = useState(false);
    const [truncate, setTruncate] = useState(false);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const { mutate } = getServerBackups();

    const doDownload = () => {
        setLoading(true);
        clearFlashes('backups');
        getBackupDownloadUrl(uuid, backup.uuid)
            .then((url) => {
                // @ts-expect-error this is valid
                window.location = url;
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
            })
            .then(() => setLoading(false));
    };

    const doDeletion = () => {
        setLoading(true);
        clearFlashes('backups');
        deleteBackup(uuid, backup.uuid)
            .then(() =>
                mutate(
                    (data) => ({
                        ...data,
                        items: data.items.filter((b) => b.uuid !== backup.uuid),
                        backupCount: data.backupCount - 1,
                    }),
                    false
                )
            )
            // Hydra: reloads the backup space.
            .then(() => mutate())
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
                setLoading(false);
                setModal('');
            });
    };

    const doRestorationAction = () => {
        setLoading(true);
        clearFlashes('backups');
        restoreServerBackup(uuid, backup.uuid, truncate)
            .then(() =>
                setServerFromState((s) => ({
                    ...s,
                    status: 'restoring_backup',
                }))
            )
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'backups', error });
            })
            .then(() => setLoading(false))
            .then(() => setModal(''));
    };

    const onLockToggle = () => {
        if (backup.isLocked && modal !== 'unlock') {
            return setModal('unlock');
        }

        http.post(`/api/client/servers/${uuid}/backups/${backup.uuid}/lock`)
            .then(() =>
                mutate(
                    (data) => ({
                        ...data,
                        items: data.items.map((b) =>
                            b.uuid !== backup.uuid
                                ? b
                                : {
                                      ...b,
                                      isLocked: !b.isLocked,
                                  }
                        ),
                    }),
                    false
                )
            )
            .catch((error) => alert(httpErrorToHuman(error)))
            .then(() => setModal(''));
    };

    return (
        <>
            <Dialog.Confirm
                open={modal === 'unlock'}
                onClose={() => setModal('')}
                title={t('backups.unlock_title', { name: backup.name })}
                onConfirmed={onLockToggle}
            >
                {t('backups.unlock_text')}
            </Dialog.Confirm>
            <Dialog.Confirm
                open={modal === 'restore'}
                onClose={() => setModal('')}
                confirm={t('backups.restore')}
                title={t('backups.restore_title', { name: backup.name })}
                onConfirmed={() => doRestorationAction()}
            >
                <p>{t('backups.restore_text')}</p>
                <p css={tw`mt-4 -mb-2 bg-gray-700 p-3 rounded`}>
                    <label htmlFor={'restore_truncate'} css={tw`text-base flex items-center cursor-pointer`}>
                        <Input
                            type={'checkbox'}
                            css={tw`text-red-500! w-5! h-5! mr-2`}
                            id={'restore_truncate'}
                            value={'true'}
                            checked={truncate}
                            onChange={() => setTruncate((s) => !s)}
                        />
                        {t('backups.restore_truncate')}
                    </label>
                </p>
            </Dialog.Confirm>
            <Dialog.Confirm
                title={t('backups.delete_title', { name: backup.name })}
                confirm={t('client/common:actions.continue')}
                open={modal === 'delete'}
                onClose={() => setModal('')}
                onConfirmed={doDeletion}
            >
                {t('backups.delete_text')}
            </Dialog.Confirm>
            <SpinnerOverlay visible={loading} fixed />
            {backup.isSuccessful ? (
                <DropdownMenu
                    renderToggle={(onClick) => (
                        <button
                            onClick={onClick}
                            css={tw`text-gray-200 transition-colors duration-150 hover:text-gray-100 p-2`}
                        >
                            <FontAwesomeIcon icon={faEllipsisH} />
                        </button>
                    )}
                >
                    <div css={tw`text-sm`}>
                        <Can action={'backup.download'}>
                            <DropdownButtonRow onClick={doDownload}>
                                <FontAwesomeIcon fixedWidth icon={faCloudDownloadAlt} css={tw`text-xs`} />
                                <span css={tw`ml-2`}>{t('backups.download')}</span>
                            </DropdownButtonRow>
                        </Can>
                        {/* Hydra: a restore would lift the suspension, so it waits until the server is unsuspended. */}
                        {!suspended && (
                            <Can action={'backup.restore'}>
                                <DropdownButtonRow onClick={() => setModal('restore')}>
                                    <FontAwesomeIcon fixedWidth icon={faBoxOpen} css={tw`text-xs`} />
                                    <span css={tw`ml-2`}>{t('backups.restore')}</span>
                                </DropdownButtonRow>
                            </Can>
                        )}
                        <Can action={'backup.delete'}>
                            <>
                                <DropdownButtonRow onClick={onLockToggle}>
                                    <FontAwesomeIcon
                                        fixedWidth
                                        icon={backup.isLocked ? faUnlock : faLock}
                                        css={tw`text-xs mr-2`}
                                    />
                                    {backup.isLocked ? t('backups.unlock') : t('backups.lock')}
                                </DropdownButtonRow>
                                {!backup.isLocked && (
                                    <DropdownButtonRow danger onClick={() => setModal('delete')}>
                                        <FontAwesomeIcon fixedWidth icon={faTrashAlt} css={tw`text-xs`} />
                                        <span css={tw`ml-2`}>{t('backups.delete')}</span>
                                    </DropdownButtonRow>
                                )}
                            </>
                        </Can>
                    </div>
                </DropdownMenu>
            ) : (
                <button
                    onClick={() => setModal('delete')}
                    css={tw`text-gray-200 transition-colors duration-150 hover:text-gray-100 p-2`}
                >
                    <FontAwesomeIcon icon={faTrashAlt} />
                </button>
            )}
        </>
    );
};
