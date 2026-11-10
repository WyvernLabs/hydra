import React, { useEffect, useState } from 'react';
import {
    ActivityFilterOptions,
    ActivityLogFilters,
    getActivityFilterOptions,
    useActivityLogs,
} from '@/api/server/activity';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { useFlashKey } from '@/plugins/useFlash';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import PaginationFooter from '@/components/elements/table/PaginationFooter';
import { Link } from 'react-router-dom';
import classNames from 'classnames';
import { styles as btnStyles } from '@/components/elements/button/index';
import { CircleX as XCircleIcon, Search } from 'lucide-react';
import Select from '@/components/elements/Select';
import Input from '@/components/elements/Input';
import { ServerContext } from '@/state/server';
import useLocationHash from '@/plugins/useLocationHash';
import { useTranslation } from 'react-i18next';

export default () => {
    const { t } = useTranslation('client/server');
    const { hash } = useLocationHash();
    const { clearAndAddHttpError } = useFlashKey('server:activity');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [filters, setFilters] = useState<ActivityLogFilters>({ page: 1, sorts: { timestamp: -1 } });
    // Hydra: filter by user, action and free text.
    const [options, setOptions] = useState<ActivityFilterOptions>({ actors: [], events: [] });
    const [search, setSearch] = useState('');

    useEffect(() => {
        getActivityFilterOptions(uuid)
            .then(setOptions)
            .catch(() => undefined);
    }, [uuid]);

    // Typing waits a moment before the log is loaded again.
    useEffect(() => {
        const timer = setTimeout(() => {
            setFilters((value) =>
                (value.filters?.search || '') === search.trim()
                    ? value
                    : { ...value, page: 1, filters: { ...value.filters, search: search.trim() || undefined } }
            );
        }, 350);

        return () => clearTimeout(timer);
    }, [search]);

    const setFilter = (key: 'actor' | 'action', value: string) =>
        setFilters((current) => ({ ...current, page: 1, filters: { ...current.filters, [key]: value || undefined } }));

    const active = Object.values(filters.filters || {}).some((value) => !!value);

    const { data, isValidating, error } = useActivityLogs(filters, {
        revalidateOnMount: true,
        revalidateOnFocus: false,
    });

    useEffect(() => {
        setFilters((value) => ({ ...value, page: 1, filters: { ...value.filters, ip: hash.ip, event: hash.event } }));
    }, [hash]);

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    return (
        <ServerContentBlock title={t('activity.title')}>
            <FlashMessageRender byKey={'server:activity'} />
            {/* Hydra: filter by user, action and text; only matching entries are listed. */}
            <div
                className={
                    'mb-3 grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_minmax(0,1.4fr)_auto]'
                }
            >
                <Select
                    aria-label={t('activity.filter_user')}
                    value={String(filters.filters?.actor || '')}
                    onChange={(event) => setFilter('actor', event.currentTarget.value)}
                >
                    <option value={''}>{t('activity.all_users')}</option>
                    {options.actors.map((actor) => (
                        <option key={actor.id} value={String(actor.id)}>
                            {actor.username}
                        </option>
                    ))}
                </Select>
                <Select
                    aria-label={t('activity.filter_action')}
                    value={String(filters.filters?.action || '')}
                    onChange={(event) => setFilter('action', event.currentTarget.value)}
                >
                    <option value={''}>{t('activity.all_actions')}</option>
                    {options.events.map((item) => (
                        <option key={item.event} value={item.event}>
                            {item.label}
                        </option>
                    ))}
                </Select>
                <div className={'relative'}>
                    <Search
                        className={'pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400'}
                    />
                    <Input
                        type={'search'}
                        value={search}
                        onChange={(event) => setSearch(event.currentTarget.value)}
                        placeholder={t('activity.filter_search')}
                        className={'!pl-9'}
                    />
                </div>
                {active && (
                    <Link
                        to={'#'}
                        className={classNames(btnStyles.button, btnStyles.text, 'w-full justify-center md:w-auto')}
                        onClick={() => {
                            setSearch('');
                            setFilters((value) => ({ ...value, page: 1, filters: {} }));
                        }}
                    >
                        {t('activity.clear_filters')} <XCircleIcon className={'w-4 h-4 ml-2'} />
                    </Link>
                )}
            </div>
            {!data && isValidating ? (
                <Spinner centered />
            ) : !data?.items.length ? (
                <p className={'text-sm text-center text-gray-400'}>
                    {active ? t('activity.no_results') : t('activity.empty')}
                </p>
            ) : (
                <div className={'bg-gray-700'}>
                    {data?.items.map((activity) => (
                        <ActivityLogEntry key={activity.id} activity={activity}>
                            <span />
                        </ActivityLogEntry>
                    ))}
                </div>
            )}
            {data && (
                <PaginationFooter
                    pagination={data.pagination}
                    onPageSelect={(page) => setFilters((value) => ({ ...value, page }))}
                />
            )}
        </ServerContentBlock>
    );
};
