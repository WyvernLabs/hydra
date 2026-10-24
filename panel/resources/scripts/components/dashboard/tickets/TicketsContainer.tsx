import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import { Plus, Server as ServerIcon } from 'lucide-react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { getTickets } from '@/api/account/tickets';
import { useFlashKey } from '@/plugins/useFlash';
import { formatDistanceToNow } from '@/lib/date';
import { PriorityLabel, StatusLabel } from '@/components/dashboard/tickets/TicketLabels';

// Hydra: the support tickets of the user (tickets addon).

type Filter = 'open' | 'closed' | 'all';

export default () => {
    const { t } = useTranslation('client/tickets');
    const { clearAndAddHttpError } = useFlashKey('tickets');
    const [filter, setFilter] = useState<Filter>('open');
    const [page, setPage] = useState(1);
    const { data, error } = useSWR(['/api/client/account/tickets', filter, page], () => getTickets(filter, page), {
        revalidateOnMount: true,
    });

    useEffect(() => {
        clearAndAddHttpError(error);
    }, [error]);

    return (
        <PageContentBlock title={t('title')}>
            <FlashMessageRender byKey={'tickets'} className={'mb-4'} />
            <div className={'mb-4 flex flex-wrap items-center gap-3'}>
                <div className={'flex overflow-hidden rounded border border-gray-600 text-sm'}>
                    {(['open', 'closed', 'all'] as Filter[]).map((value) => (
                        <button
                            key={value}
                            type={'button'}
                            onClick={() => {
                                setFilter(value);
                                setPage(1);
                            }}
                            className={`px-4 py-1.5 ${
                                filter === value ? 'bg-gray-600 text-gray-50' : 'text-gray-300 hover:bg-gray-700'
                            }`}
                        >
                            {t(`filter.${value}`)}
                        </button>
                    ))}
                </div>
                <Link
                    to={'/account/tickets/new'}
                    className={
                        'ml-auto flex items-center gap-2 rounded bg-primary-500 px-4 py-2 text-sm font-medium text-white no-underline hover:bg-primary-400'
                    }
                >
                    <Plus className={'h-4 w-4'} />
                    {t('new')}
                </Link>
            </div>

            {!data ? (
                <Spinner centered size={'large'} />
            ) : data.items.length === 0 ? (
                <p className={'py-10 text-center text-sm text-gray-400'}>{t('empty')}</p>
            ) : (
                <div className={'space-y-2'}>
                    {data.items.map((ticket) => (
                        <Link
                            key={ticket.id}
                            to={`/account/tickets/${ticket.id}`}
                            className={
                                'hydra-card flex flex-wrap items-center gap-3 p-4 no-underline hover:border-gray-500'
                            }
                        >
                            <span className={'font-mono text-sm text-gray-400'}>#{ticket.id}</span>
                            <div className={'min-w-0 flex-1'}>
                                <p className={'truncate font-medium text-gray-50'}>{ticket.subject}</p>
                                <p className={'mt-0.5 flex flex-wrap items-center gap-x-3 text-xs text-gray-400'}>
                                    {ticket.category && <span>{ticket.category}</span>}
                                    {ticket.languageName && <span>{ticket.languageName}</span>}
                                    {ticket.server && (
                                        <span className={'inline-flex items-center gap-1'}>
                                            <ServerIcon className={'h-3 w-3'} />
                                            {ticket.server.name}
                                        </span>
                                    )}
                                    {ticket.lastReplyAt && (
                                        <span>
                                            {t('last_reply', {
                                                time: formatDistanceToNow(ticket.lastReplyAt, { addSuffix: true }),
                                            })}
                                        </span>
                                    )}
                                </p>
                            </div>
                            <PriorityLabel priority={ticket.priority} />
                            <StatusLabel status={ticket.status} />
                        </Link>
                    ))}
                </div>
            )}

            {data && data.lastPage > 1 && (
                <div className={'mt-4 flex items-center justify-center gap-3 text-sm'}>
                    <button
                        type={'button'}
                        disabled={page <= 1}
                        onClick={() => setPage(page - 1)}
                        className={'rounded px-3 py-1 text-gray-300 hover:bg-gray-700 disabled:opacity-40'}
                    >
                        ‹
                    </button>
                    <span className={'text-gray-400'}>
                        {data.currentPage} / {data.lastPage}
                    </span>
                    <button
                        type={'button'}
                        disabled={page >= data.lastPage}
                        onClick={() => setPage(page + 1)}
                        className={'rounded px-3 py-1 text-gray-300 hover:bg-gray-700 disabled:opacity-40'}
                    >
                        ›
                    </button>
                </div>
            )}
        </PageContentBlock>
    );
};
