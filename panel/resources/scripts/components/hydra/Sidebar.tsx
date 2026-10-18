import React, { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import classNames from 'classnames';
import { LogoutIcon, ShieldCheckIcon } from '@heroicons/react/outline';
import { useStoreState } from '@/state/hooks';
import http from '@/api/http';
import Avatar from '@/components/Avatar';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';

type IconComponent = React.ComponentType<{ className?: string }>;

export interface SidebarItem {
    key: string;
    name: string;
    icon?: IconComponent;
    // Internal route (react-router) …
    to?: string;
    exact?: boolean;
    // … or a regular link that leaves the React app (e.g. the admin area).
    href?: string;
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
        {item.icon && <item.icon className={'hydra-nav-icon'} />}
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
            <a href={item.href} className={'hydra-nav-item'}>
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
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

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
                <Tooltip placement={'top'} content={'Account'}>
                    <NavLink to={'/account'} className={'hydra-icon-btn'} onClick={onNavigate}>
                        <span className={'hydra-avatar'}>
                            <Avatar.User />
                        </span>
                        <span className={'sr-only'}>Account</span>
                    </NavLink>
                </Tooltip>
                {rootAdmin && (
                    <Tooltip placement={'top'} content={'Administration'}>
                        <a href={'/admin'} className={'hydra-icon-btn'}>
                            <ShieldCheckIcon className={'hydra-nav-icon'} />
                        </a>
                    </Tooltip>
                )}
                <Tooltip placement={'top'} content={'Sign Out'}>
                    <button type={'button'} className={'hydra-icon-btn'} onClick={onTriggerLogout}>
                        <LogoutIcon className={'hydra-nav-icon'} />
                    </button>
                </Tooltip>
            </div>
        </aside>
    );
};
