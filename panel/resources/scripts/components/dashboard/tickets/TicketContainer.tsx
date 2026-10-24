import React, { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Server as ServerIcon } from 'lucide-react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { ServerError } from '@/components/elements/ScreenBlock';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import Avatar from '@/components/Avatar';
import MarkdownEditor from '@/components/dashboard/tickets/MarkdownEditor';
import { PriorityLabel, StatusLabel } from '@/components/dashboard/tickets/TicketLabels';
import { closeTicket, getTicket, replyToTicket, TicketMessage } from '@/api/account/tickets';
import { httpErrorToHuman } from '@/api/http';
import { useFlashKey } from '@/plugins/useFlash';
import { formatDate, formatDistanceToNow } from '@/lib/date';
import { useDocumentTitle } from '@/components/hydra/tabs/context';

// Hydra: a support ticket with its messages and the answer form (tickets addon).

const Message = ({ message }: { message: TicketMessage }) => {
    const { t } = useTranslation('client/tickets');

    // Spoilers open on click, like on Discord.
    const reveal = (event: React.MouseEvent) => {
        const target = event.target as HTMLElement;
        if (target.classList.contains('md-spoiler')) target.classList.add('is-revealed');
    };

    return (
        <article className={`hydra-card overflow-hidden ${message.staff ? 'border-l-4 border-l-primary-500' : ''}`}>
            <header className={'flex items-center gap-3 border-b border-gray-700 px-4 py-3'}>
                <div className={'h-8 w-8 shrink-0 overflow-hidden rounded-full'}>
                    {message.author?.avatarUrl ? (
                        <img src={message.author.avatarUrl} alt={''} className={'h-full w-full object-cover'} />
                    ) : (
                        <Avatar name={message.author?.username || 'deleted'} size={32} />
                    )}
                </div>
                <div className={'min-w-0 flex-1'}>
                    <p className={'flex items-center gap-2 text-sm font-medium text-gray-50'}>
                        {message.author?.username || t('deleted_user')}
                        {message.staff && (
                            <span className={'rounded bg-primary-500/20 px-1.5 py-0.5 text-xs text-primary-300'}>
                                {t('team')}
                            </span>
                        )}
                    </p>
                    <p className={'text-xs text-gray-400'} title={formatDate(message.createdAt)}>
                        {formatDistanceToNow(message.createdAt, { addSuffix: true })}
                    </p>
                </div>
            </header>
            <div
                className={'hydra-markdown p-4 text-sm'}
                onClick={reveal}
                // Rendered by the panel from Markdown, without raw HTML.
                dangerouslySetInnerHTML={{ __html: message.html }}
            />
        </article>
    );
};

export default () => {
    const { t } = useTranslation('client/tickets');
    const { id } = useParams<{ id: string }>();
    const { addError, clearFlashes, clearAndAddHttpError } = useFlashKey('tickets:view');
    const { data, error, mutate } = useSWR(['/api/client/account/tickets', id], () => getTicket(Number(id)), {
        revalidateOnMount: true,
    });
    const [body, setBody] = useState('');
    const [sending, setSending] = useState(false);
    const [confirmClose, setConfirmClose] = useState(false);

    useDocumentTitle(data ? `#${data.id} ${data.subject}` : undefined);

    if (error) {
        return <ServerError message={httpErrorToHuman(error)} onRetry={() => mutate()} />;
    }

    const send = (event: React.FormEvent) => {
        event.preventDefault();
        clearFlashes();
        setSending(true);
        replyToTicket(Number(id), body)
            .then(() => mutate())
            .then(() => setBody(''))
            .catch(clearAndAddHttpError)
            .then(() => setSending(false));
    };

    const close = () => {
        clearFlashes();
        closeTicket(Number(id))
            .then(() => mutate())
            .catch(clearAndAddHttpError)
            .then(() => setConfirmClose(false));
    };

    return (
        <PageContentBlock title={data ? `#${data.id} ${data.subject}` : t('title')}>
            <Link
                to={'/account/tickets'}
                className={'mb-4 inline-flex items-center gap-1 text-sm text-gray-400 no-underline hover:text-gray-200'}
            >
                <ArrowLeft className={'h-4 w-4'} />
                {t('back')}
            </Link>
            {!data ? (
                <Spinner centered size={'large'} />
            ) : (
                <>
                    <Dialog.Confirm
                        open={confirmClose}
                        title={t('close_confirm_title')}
                        confirm={t('close')}
                        onClose={() => setConfirmClose(false)}
                        onConfirmed={close}
                    >
                        {t('close_confirm_text')}
                    </Dialog.Confirm>
                    <div className={'mb-4 flex flex-wrap items-start gap-3'}>
                        <div className={'min-w-0 flex-1'}>
                            <h1 className={'text-2xl font-semibold text-gray-50'}>
                                <span className={'mr-2 font-mono text-gray-400'}>#{data.id}</span>
                                {data.subject}
                            </h1>
                            <p className={'mt-1 flex flex-wrap items-center gap-x-3 text-sm text-gray-400'}>
                                {data.category && <span>{data.category}</span>}
                                {data.languageName && <span>{data.languageName}</span>}
                                {data.server && (
                                    <Link
                                        to={`/server/${data.server.identifier}`}
                                        className={'inline-flex items-center gap-1 text-gray-300 hover:text-gray-100'}
                                    >
                                        <ServerIcon className={'h-3.5 w-3.5'} />
                                        {data.server.name}
                                    </Link>
                                )}
                                <span>{t('opened', { time: formatDate(data.createdAt) })}</span>
                            </p>
                        </div>
                        <div className={'flex items-center gap-2'}>
                            <PriorityLabel priority={data.priority} />
                            <StatusLabel status={data.status} />
                            {data.status !== 'closed' && (
                                <Button.Text size={Button.Sizes.Small} onClick={() => setConfirmClose(true)}>
                                    {t('close')}
                                </Button.Text>
                            )}
                        </div>
                    </div>

                    <div className={'space-y-3'}>
                        {data.messages.map((message) => (
                            <Message key={message.id} message={message} />
                        ))}
                    </div>

                    <form onSubmit={send} className={'hydra-card mt-6 space-y-3 p-4'}>
                        <h2 className={'text-lg font-semibold text-gray-50'}>{t('reply')}</h2>
                        {data.status === 'closed' && <p className={'text-sm text-gray-400'}>{t('closed_notice')}</p>}
                        <FlashMessageRender byKey={'tickets:view'} />
                        <MarkdownEditor value={body} onChange={setBody} onError={addError} disabled={sending} />
                        <div className={'flex justify-end'}>
                            <Button type={'submit'} disabled={sending || !body.trim()}>
                                {t('send')}
                            </Button>
                        </div>
                    </form>
                </>
            )}
        </PageContentBlock>
    );
};
