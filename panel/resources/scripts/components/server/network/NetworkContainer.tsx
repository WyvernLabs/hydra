import React, { useEffect, useState } from 'react';
import Spinner from '@/components/elements/Spinner';
import { useFlashKey } from '@/plugins/useFlash';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import AllocationRow from '@/components/server/network/AllocationRow';
import Button from '@/components/elements/Button';
import createServerAllocation from '@/api/server/network/createServerAllocation';
import tw from 'twin.macro';
import Can from '@/components/elements/Can';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import getServerAllocations from '@/api/swr/getServerAllocations';
import isEqual from 'react-fast-compare';
import { useDeepCompareEffect } from '@/plugins/useDeepCompareEffect';
import { useTranslation } from 'react-i18next';
import { getNetworkExtras, NetworkExtras } from '@/api/server/network/hydra';
import { DedicatedPortForm, ProxiesCard, SubdomainsCard } from '@/components/server/network/NetworkExtras';
import { Allocation } from '@/api/server/getServer';

const NetworkContainer = () => {
    const [loading, setLoading] = useState(false);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { t } = useTranslation('client/server');
    const allocationLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.allocations);
    const allocations = ServerContext.useStoreState((state) => state.server.data!.allocations, isEqual);
    const setServerFromState = ServerContext.useStoreActions((actions) => actions.server.setServerFromState);

    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const { data, error, mutate } = getServerAllocations();
    // Hydra: dedicated node, subdomains and reverse proxies.
    const [extras, setExtras] = useState<NetworkExtras | null>(null);

    const loadExtras = () => {
        getNetworkExtras(uuid)
            .then(setExtras)
            .catch((error) => clearAndAddHttpError(error));
    };

    useEffect(() => {
        mutate(allocations);
        loadExtras();
    }, []);

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    useDeepCompareEffect(() => {
        if (!data) return;

        setServerFromState((state) => ({ ...state, allocations: data }));
        // Removing a port also removes its subdomains and proxies.
        loadExtras();
    }, [data]);

    const onAllocationAdded = (allocation: Allocation) => {
        setServerFromState((s) => ({ ...s, allocations: s.allocations.concat(allocation) }));
        return mutate(data?.concat(allocation), false);
    };

    const onCreateAllocation = () => {
        clearFlashes();

        setLoading(true);
        createServerAllocation(uuid)
            .then(onAllocationAdded)
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setLoading(false));
    };

    const dedicated = !!extras?.dedicated.enabled;

    return (
        <ServerContentBlock showFlashKey={'server:network'} title={t('network.title')}>
            {!data ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    {data.map((allocation) => (
                        <AllocationRow key={`${allocation.ip}:${allocation.port}`} allocation={allocation} />
                    ))}
                    {allocationLimit > 0 && (
                        <Can action={'allocation.create'}>
                            <SpinnerOverlay visible={loading} />
                            <div css={tw`mt-6 sm:flex items-center justify-end`}>
                                <p css={tw`text-sm text-neutral-300 mb-4 sm:mr-6 sm:mb-0`}>
                                    {t('network.usage', { count: data.length, limit: allocationLimit })}
                                </p>
                                {allocationLimit > data.length && !dedicated && (
                                    <Button css={tw`w-full sm:w-auto`} color={'primary'} onClick={onCreateAllocation}>
                                        {t('network.create')}
                                    </Button>
                                )}
                            </div>
                        </Can>
                    )}
                    {extras && dedicated && (allocationLimit === 0 || allocationLimit > data.length) && (
                        <DedicatedPortForm extras={extras} onAllocationAdded={onAllocationAdded} />
                    )}
                    {extras?.subdomains.enabled && (
                        <SubdomainsCard
                            extras={extras}
                            allocations={data}
                            onChange={loadExtras}
                            onAllocationAdded={onAllocationAdded}
                        />
                    )}
                    {extras?.proxies.enabled && (
                        <ProxiesCard
                            extras={extras}
                            allocations={data}
                            onChange={loadExtras}
                            onAllocationAdded={onAllocationAdded}
                        />
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};

export default NetworkContainer;
