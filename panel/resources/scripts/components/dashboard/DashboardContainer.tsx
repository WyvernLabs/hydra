import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { getResourcePool, ResourcePool } from '@/api/account/pool';
import ResourcePoolCard from '@/components/dashboard/pool/ResourcePoolCard';

// Hydra: the greeting depends on the time of day.
const greetingKey = (): string => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'greeting.morning';
    if (hour >= 12 && hour < 18) return 'greeting.afternoon';
    if (hour >= 18 && hour < 23) return 'greeting.evening';
    return 'greeting.night';
};

// Hydra: other people's servers arrive sorted by owner and are shown in one
// group per owner.
const groupByOwner = (servers: Server[]): { key: string; title: string; servers: Server[] }[] =>
    servers.reduce((groups, server) => {
        const key = server.owner?.username || '';
        const last = groups[groups.length - 1];
        if (last && last.key === key) {
            last.servers.push(server);
        } else {
            const title = server.owner ? `${server.owner.name} (${server.owner.username})` : '';
            groups.push({ key, title, servers: [server] });
        }
        return groups;
    }, [] as { key: string; title: string; servers: Server[] }[]);

export default () => {
    const { search } = useLocation();
    const { t } = useTranslation('client/dashboard');
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const name = useStoreState((state) => state.user.data!.nameFirst || state.user.data!.username);
    const rootAdmin = useStoreState((state) => state.user.data!.serverAccess);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);
    const showOthers = showOnlyAdmin && rootAdmin;

    const {
        data: servers,
        error,
        mutate,
    } = useSWR<PaginatedResult<Server>>(['/api/client/servers', showOthers, page], () =>
        getServers({ page, type: showOthers ? 'admin' : undefined })
    );

    // Hydra: resource pool of the user (addon), next to the own servers.
    const poolEnabled = useStoreState((state) => state.settings.data!.resourcePool);
    const { data: pool, mutate: mutatePool } = useSWR<ResourcePool | null>(
        poolEnabled ? '/api/client/account/pool' : null,
        getResourcePool,
        { revalidateOnFocus: false }
    );
    const showPool = !!pool && !showOthers;

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    const total = servers?.pagination.total;

    return (
        <PageContentBlock title={t('title')} showFlashKey={'dashboard'}>
            {/* Hydra: the resource pool sits right of the greeting. */}
            <div className={'hydra-dashboard-head'}>
                <div className={'hydra-dashboard-hero'}>
                    <div css={tw`min-w-0`}>
                        <h1 className={'hydra-dashboard-greeting'}>{t(greetingKey(), { name })}</h1>
                        <p className={'hydra-dashboard-subtitle'}>
                            {total === undefined
                                ? ' '
                                : t(total === 1 ? 'server_count_one' : 'server_count', { count: total })}
                        </p>
                    </div>
                    {rootAdmin && (
                        <div className={'hydra-segmented'} role={'tablist'}>
                            <button
                                type={'button'}
                                role={'tab'}
                                aria-selected={!showOnlyAdmin}
                                className={!showOnlyAdmin ? 'active' : undefined}
                                onClick={() => setShowOnlyAdmin(false)}
                            >
                                {t('my_servers')}
                            </button>
                            <button
                                type={'button'}
                                role={'tab'}
                                aria-selected={showOnlyAdmin}
                                className={showOnlyAdmin ? 'active' : undefined}
                                onClick={() => setShowOnlyAdmin(true)}
                            >
                                {t('others_servers')}
                            </button>
                        </div>
                    )}
                </div>
                {showPool && (
                    <ResourcePoolCard
                        pool={pool!}
                        onChanged={() => {
                            mutatePool();
                            mutate();
                        }}
                    />
                )}
            </div>
            {!servers ? (
                <Spinner centered size={'large'} />
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length === 0 ? (
                            <p css={tw`text-center text-sm text-neutral-400`}>
                                {showOthers ? t('no_other_servers') : t('no_servers')}
                            </p>
                        ) : showOthers ? (
                            groupByOwner(items).map((group) => (
                                <div key={group.key} className={'hydra-dashboard-group'}>
                                    <div className={'hydra-nav-title'}>{group.title || t('unknown_owner')}</div>
                                    <div className={'grid grid-cols-1 gap-4 xl:grid-cols-2'}>
                                        {group.servers.map((server) => (
                                            <ServerRow key={server.uuid} server={server} />
                                        ))}
                                    </div>
                                </div>
                            ))
                        ) : (
                            // Hydra: two cards per row on wide screens.
                            <div className={'grid grid-cols-1 gap-4 xl:grid-cols-2'}>
                                {items.map((server) => (
                                    <ServerRow key={server.uuid} server={server} />
                                ))}
                            </div>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
