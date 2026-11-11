import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Hourglass } from 'lucide-react';
import { ServerContext } from '@/state/server';

// Hydra: remaining runtime of a server, in the middle of the server header.
// Set by an admin (Servers > Manage > Runtime); once it has run out the panel
// suspends the server within a minute.

const pad = (value: number) => String(value).padStart(2, '0');

export const formatRemaining = (seconds: number, day: string): string => {
    const days = Math.floor(seconds / 86400);
    const rest = seconds % 86400;
    const time = `${pad(Math.floor(rest / 3600))}:${pad(Math.floor((rest % 3600) / 60))}:${pad(rest % 60)}`;

    return days > 0 ? `${days}${day} ${time}` : time;
};

export default ({ expiresAt }: { expiresAt: Date }) => {
    const { t, i18n } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const [now, setNow] = useState(Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    const seconds = Math.max(0, Math.floor((expiresAt.getTime() - now) / 1000));
    const expired = seconds === 0;
    const state = expired ? 'expired' : seconds < 3600 ? 'urgent' : seconds < 86400 ? 'soon' : 'ok';

    // After running out, load the server again once the panel had time to suspend it.
    useEffect(() => {
        if (!expired) return;
        const timer = setTimeout(() => getServer(uuid).catch(() => undefined), 75 * 1000);

        return () => clearTimeout(timer);
    }, [expired, uuid]);

    const until = expiresAt.toLocaleString(i18n.language, { dateStyle: 'medium', timeStyle: 'short' });

    return (
        <div
            className={'hydra-expiry-timer'}
            data-state={state}
            title={t('expiry.until', { date: until })}
            role={'timer'}
            aria-live={'off'}
        >
            <Hourglass className={'hydra-expiry-timer-icon'} aria-hidden />
            <div className={'hydra-expiry-timer-text'}>
                <span className={'hydra-expiry-timer-label'}>
                    {expired ? t('expiry.label') : t('expiry.remaining')}
                </span>
                <span className={'hydra-expiry-timer-value'}>
                    {expired ? t('expiry.expired') : formatRemaining(seconds, t('expiry.day'))}
                </span>
            </div>
        </div>
    );
};
