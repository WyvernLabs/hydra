import React from 'react';
import { ServerContext } from '@/state/server';
import ScreenBlock from '@/components/elements/ScreenBlock';
import ServerInstallSvg from '@/assets/images/server_installing.svg';
import ServerErrorSvg from '@/assets/images/server_error.svg';
import ServerRestoreSvg from '@/assets/images/server_restore.svg';
import { useTranslation } from 'react-i18next';

export default () => {
    const { t } = useTranslation('client/server');
    const status = ServerContext.useStoreState((state) => state.server.data?.status || null);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data?.isTransferring || false);
    const isNodeUnderMaintenance = ServerContext.useStoreState(
        (state) => state.server.data?.isNodeUnderMaintenance || false
    );

    return status === 'installing' || status === 'install_failed' || status === 'reinstall_failed' ? (
        <ScreenBlock
            title={t('conflict.installing_title')}
            image={ServerInstallSvg}
            message={t('conflict.installing')}
        />
    ) : status === 'suspended' ? (
        <ScreenBlock title={t('conflict.suspended_title')} image={ServerErrorSvg} message={t('conflict.suspended')} />
    ) : isNodeUnderMaintenance ? (
        <ScreenBlock
            title={t('conflict.maintenance_title')}
            image={ServerErrorSvg}
            message={t('conflict.maintenance')}
        />
    ) : (
        <ScreenBlock
            title={isTransferring ? t('conflict.transferring_title') : t('conflict.restoring_title')}
            image={ServerRestoreSvg}
            message={isTransferring ? t('conflict.transferring') : t('conflict.restoring')}
        />
    );
};
