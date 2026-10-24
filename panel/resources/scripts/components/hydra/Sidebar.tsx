import React, { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import classNames from 'classnames';
import { Download as DownloadIcon, LogOut as LogoutIcon, ShieldCheck as ShieldCheckIcon } from 'lucide-react';
import { useStoreState } from '@/state/hooks';
import http from '@/api/http';
import Avatar from '@/components/Avatar';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { useTranslation } from 'react-i18next';
import { useInstallPrompt } from '@/lib/pwa';

type IconComponent = React.ComponentType<{ className?: string; style?: React.CSSProperties }>;

export interface SidebarItem {
    key: string;
    name: string;
    icon?: IconComponent;
    // Hydra: color of the icon, set per category of the server navigation.
    iconColor?: string | null;
    // Internal route (react-router) …
    to?: string;
    exact?: boolean;
    // … or a regular link that leaves the React app (e.g. the admin area).
    href?: string;
    // Opens the link in a new tab (e.g. a Discord invite).
    external?: boolean;
    onClick?: () => void;
}

export interface SidebarSection {
    key: string;
    title?: string;
    // Rendered between the title and the items, e.g. the current server.
    header?: React.ReactNode;
    items: Array<SidebarItem | React.ReactElement>;
}

interface Props {
    sections: SidebarSection[];
    open: boolean;
    onNavigate: () => void;
}

const ItemContent = ({ item }: { item: SidebarItem }) => (
    <>
        {item.icon && (
            <item.icon className={'hydra-nav-icon'} style={item.iconColor ? { color: item.iconColor } : undefined} />
        )}
        <span>{item.name}</span>
    </>
);

export const SidebarLink = ({ item, onNavigate }: { item: SidebarItem; onNavigate?: () => void }) => {
    if (item.to) {
        return (
            <NavLink to={item.to} exact={item.exact} className={'hydra-nav-item'} onClick={onNavigate}>
                <ItemContent item={item} />
            </NavLink>
        );
    }

    if (item.href) {
        return (
            <a
                href={item.href}
                className={'hydra-nav-item'}
                {...(item.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
            >
                <ItemContent item={item} />
            </a>
        );
    }

    return (
        <button type={'button'} className={'hydra-nav-item'} onClick={item.onClick}>
            <ItemContent item={item} />
        </button>
    );
};

export default ({ sections, open, onNavigate }: Props) => {
    const branding = useStoreState((state) => state.settings.data!.branding);
    const name = useStoreState((state) => state.settings.data!.name);
    const rootAdmin = useStoreState((state) => state.user.data!.adminAccess);
    const { t } = useTranslation();
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    // Hydra: offered by the browser while the app (addon "pwa") is not installed.
    const install = useInstallPrompt();

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <aside className={classNames('hydra-sidebar', { 'hydra-sidebar--open': open })}>
            <SpinnerOverlay visible={isLoggingOut} />
            <Link to={'/'} className={'hydra-sidebar-logo'} onClick={onNavigate}>
                <img src={branding.logo} alt={name} />
            </Link>
            <nav className={'hydra-nav'}>
                {sections.map((section) => (
                    <div key={section.key} className={'hydra-nav-section'}>
                        {section.title && <div className={'hydra-nav-title'}>{section.title}</div>}
                        {section.header}
                        {section.items.map((item) =>
                            React.isValidElement(item) ? (
                                item
                            ) : (
                                <SidebarLink
                                    key={(item as SidebarItem).key}
                                    item={item as SidebarItem}
                                    onNavigate={onNavigate}
                                />
                            )
                        )}
                    </div>
                ))}
            </nav>
            <div className={'hydra-sidebar-footer'}>
                <Tooltip placement={'top'} content={t('nav.account')}>
                    <NavLink to={'/account'} className={'hydra-icon-btn'} onClick={onNavigate}>
                        <span className={'hydra-avatar'}>
                            <Avatar.User />
                        </span>
                        <span className={'sr-only'}>{t('nav.account')}</span>
                    </NavLink>
                </Tooltip>
                {rootAdmin && (
                    <Tooltip placement={'top'} content={t('nav.administration')}>
                        <a href={'/admin'} className={'hydra-icon-btn'}>
                            <ShieldCheckIcon className={'hydra-nav-icon'} />
                        </a>
                    </Tooltip>
                )}
                {install && (
                    <Tooltip placement={'top'} content={t('nav.install_app')}>
                        <button type={'button'} className={'hydra-icon-btn'} onClick={install}>
                            <DownloadIcon className={'hydra-nav-icon'} />
                            <span className={'sr-only'}>{t('nav.install_app')}</span>
                        </button>
                    </Tooltip>
                )}
                <Tooltip placement={'top'} content={t('nav.sign_out')}>
                    <button type={'button'} className={'hydra-icon-btn'} onClick={onTriggerLogout}>
                        <LogoutIcon className={'hydra-nav-icon'} />
                    </button>
                </Tooltip>
            </div>
        </aside>
    );
};
