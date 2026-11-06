import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import { useTranslation } from 'react-i18next';
import { useStoreState } from '@/state/hooks';
import { ServerAnnouncements } from '@/components/hydra/Announcements';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const { t } = useTranslation('client/server');
    // Hydra: on the dashboard of the server while that addon is on.
    const dashboardEnabled = useStoreState((state) => state.settings.data!.dashboard.enabled);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    return (
        <ServerContentBlock title={t('console.title')}>
            {!dashboardEnabled && <ServerAnnouncements />}
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isNodeUnderMaintenance
                        ? t('console.maintenance')
                        : isInstalling
                        ? t('console.installing')
                        : t('console.transferring')}
                </Alert>
            )}
            <div className={'grid grid-cols-4 gap-2 sm:gap-4 mb-4'}>
                <div className={'flex col-span-4 lg:col-span-3'}>
                    <Spinner.Suspense>
                        <Console />
                    </Spinner.Suspense>
                </div>
                <ServerDetailsBlock className={'col-span-4 lg:col-span-1 order-last lg:order-none'} />
            </div>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
