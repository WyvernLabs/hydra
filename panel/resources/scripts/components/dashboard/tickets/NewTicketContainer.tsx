import React, { useEffect, useState } from 'react';
import { Link, useHistory } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import PageContentBlock from '@/components/elements/PageContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Label from '@/components/elements/Label';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import { Button } from '@/components/elements/button/index';
import MarkdownEditor from '@/components/dashboard/tickets/MarkdownEditor';
import { createTicket, TicketPriority } from '@/api/account/tickets';
import getServers from '@/api/getServers';
import { Server } from '@/api/server/getServer';
import { useStoreState } from '@/state/hooks';
import { useFlashKey } from '@/plugins/useFlash';

// Hydra: opens a new support ticket (tickets addon).

const PRIORITIES: TicketPriority[] = ['low', 'normal', 'high', 'urgent'];

export default () => {
    const { t } = useTranslation('client/tickets');
    const history = useHistory();
    const { categories, languages } = useStoreState((state) => state.settings.data!.tickets);
    const locale = useStoreState((state) => state.settings.data!.locale);
    const { addError, clearFlashes, clearAndAddHttpError } = useFlashKey('tickets:new');
    const [servers, setServers] = useState<Server[]>([]);
    const [subject, setSubject] = useState('');
    // The panel language when support is offered in it, else the first support language.
    const [language, setLanguage] = useState(
        languages.find((item) => item.code === locale)?.code || languages[0]?.code || ''
    );
    const [category, setCategory] = useState(categories[0]?.id || '');
    const categoryName = (names: Record<string, string>) =>
        names[language] || names[languages[0]?.code] || Object.values(names)[0] || '';
    const [priority, setPriority] = useState<TicketPriority>('normal');
    const [server, setServer] = useState('');
    const [body, setBody] = useState('');
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        getServers({ page: 1 })
            .then(({ items }) => setServers(items))
            .catch(() => setServers([]));
    }, []);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        clearFlashes();
        setSubmitting(true);
        createTicket({
            subject,
            body,
            category: categories.length > 0 ? category : null,
            language,
            priority,
            server: server || null,
        })
            .then((ticket) => history.push(`/account/tickets/${ticket.id}`))
            .catch((error) => {
                clearAndAddHttpError(error);
                setSubmitting(false);
            });
    };

    return (
        <PageContentBlock title={t('new_title')}>
            <Link
                to={'/account/tickets'}
                className={'mb-4 inline-flex items-center gap-1 text-sm text-gray-400 no-underline hover:text-gray-200'}
            >
                <ArrowLeft className={'h-4 w-4'} />
                {t('back')}
            </Link>
            <FlashMessageRender byKey={'tickets:new'} className={'mb-4'} />
            <form onSubmit={submit} className={'hydra-card space-y-4 p-4 sm:p-6'}>
                <h2 className={'text-xl font-semibold text-gray-50'}>{t('new_title')}</h2>
                <div>
                    <Label htmlFor={'ticketSubject'}>{t('subject')}</Label>
                    <Input
                        id={'ticketSubject'}
                        value={subject}
                        onChange={(event) => setSubject(event.currentTarget.value)}
                        placeholder={t('subject_placeholder')}
                        minLength={3}
                        maxLength={191}
                        required
                    />
                </div>
                <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4'}>
                    {languages.length > 1 && (
                        <div>
                            <Label htmlFor={'ticketLanguage'}>{t('language')}</Label>
                            <Select
                                id={'ticketLanguage'}
                                value={language}
                                onChange={(event) => setLanguage(event.currentTarget.value)}
                            >
                                {languages.map((item) => (
                                    <option key={item.code} value={item.code}>
                                        {item.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                    {categories.length > 0 && (
                        <div>
                            <Label htmlFor={'ticketCategory'}>{t('category')}</Label>
                            <Select
                                id={'ticketCategory'}
                                value={category}
                                onChange={(event) => setCategory(event.currentTarget.value)}
                            >
                                {categories.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {categoryName(item.names)}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                    <div>
                        <Label htmlFor={'ticketPriority'}>{t('priority')}</Label>
                        <Select
                            id={'ticketPriority'}
                            value={priority}
                            onChange={(event) => setPriority(event.currentTarget.value as TicketPriority)}
                        >
                            {PRIORITIES.map((value) => (
                                <option key={value} value={value}>
                                    {t(`priorities.${value}`)}
                                </option>
                            ))}
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor={'ticketServer'}>{t('server')}</Label>
                        <Select
                            id={'ticketServer'}
                            value={server}
                            onChange={(event) => setServer(event.currentTarget.value)}
                        >
                            <option value={''}>{t('no_server')}</option>
                            {servers.map((item) => (
                                <option key={item.uuid} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </Select>
                    </div>
                </div>
                <div>
                    <Label>{t('message')}</Label>
                    <MarkdownEditor value={body} onChange={setBody} onError={addError} disabled={submitting} />
                </div>
                <div className={'flex justify-end'}>
                    <Button type={'submit'} disabled={submitting || subject.trim().length < 3 || !body.trim()}>
                        {t('create')}
                    </Button>
                </div>
            </form>
        </PageContentBlock>
    );
};
