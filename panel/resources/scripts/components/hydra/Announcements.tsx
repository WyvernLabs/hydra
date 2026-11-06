import React, { useEffect, useRef } from 'react';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import { CircleCheck, Info, LucideIcon, OctagonX, TriangleAlert, Wrench, X } from 'lucide-react';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

// Hydra: announcements from Design > Announcements. Without a server these are the
// general ones and those of the user's nodes, with a server those of its node.

type AnnouncementType = 'info' | 'success' | 'warning' | 'critical' | 'maintenance';

interface Announcement {
    id: number;
    type: AnnouncementType;
    title: string;
    description: string;
    button: { text: string; url: string } | null;
    dismissible: boolean;
}

const ICONS: Record<AnnouncementType, LucideIcon> = {
    info: Info,
    success: CircleCheck,
    warning: TriangleAlert,
    critical: OctagonX,
    maintenance: Wrench,
};

const getAnnouncements = (server?: string): Promise<Announcement[]> =>
    http.get('/api/client/announcements', { params: { server } }).then(({ data }) => data.data);

// The announcements of the node of the current server: on its dashboard while that
// addon is on, otherwise on its console.
export const ServerAnnouncements = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return <Announcements server={uuid} />;
};

const Announcements = ({ server }: { server?: string }) => {
    const { t } = useTranslation();
    const { data, mutate } = useSWR<Announcement[]>(['/api/client/announcements', server], () =>
        getAnnouncements(server)
    );
    const box = useRef<HTMLDivElement>(null);
    const shown = data?.length ?? 0;

    // On a server page the console leaves room for them, so that it still fits the screen.
    useEffect(() => {
        const element = box.current;
        if (!server || !element) return;

        const root = document.documentElement;
        const margin = parseFloat(getComputedStyle(element).marginBottom) || 0;
        const observer = new ResizeObserver(() => {
            root.style.setProperty('--hydra-announcements-height', `${element.offsetHeight + margin}px`);
        });
        observer.observe(element);

        return () => {
            observer.disconnect();
            root.style.removeProperty('--hydra-announcements-height');
        };
    }, [server, shown > 0]);

    if (!data || data.length === 0) return null;

    // Gone at once; if the request fails the next load brings it back.
    const dismiss = (id: number) => {
        mutate(
            data.filter((item) => item.id !== id),
            false
        );
        http.post(`/api/client/announcements/${id}/dismiss`)
            .catch(console.error)
            .then(() => mutate());
    };

    return (
        <div ref={box} className={'hydra-announcements'}>
            {data.map((item) => {
                const Icon = ICONS[item.type] || Info;
                const external = item.button && !item.button.url.startsWith('/');

                return (
                    <div key={item.id} className={'hydra-announcement'} data-type={item.type} role={'status'}>
                        <span className={'hydra-announcement-icon'}>
                            <Icon />
                        </span>
                        <div className={'hydra-announcement-text'}>
                            <strong>{item.title}</strong>
                            <p>{item.description}</p>
                        </div>
                        {item.button && (
                            <a
                                className={'hydra-announcement-button'}
                                href={item.button.url}
                                {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                            >
                                {item.button.text}
                            </a>
                        )}
                        {item.dismissible && (
                            <button
                                type={'button'}
                                className={'hydra-announcement-close'}
                                title={t('announcements.dismiss')}
                                aria-label={t('announcements.dismiss')}
                                onClick={() => dismiss(item.id)}
                            >
                                <X />
                            </button>
                        )}
                    </div>
                );
            })}
        </div>
    );
};

export default Announcements;
