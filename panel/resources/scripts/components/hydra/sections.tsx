import React from 'react';
import { ShieldCheckIcon, ViewGridIcon } from '@heroicons/react/outline';
import { useStoreState } from '@/state/hooks';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import { SidebarItem, SidebarSection } from '@/components/hydra/Sidebar';
import routes from '@/routers/routes';
import { useTranslation } from 'react-i18next';

/**
 * The "Workspace" section is shown on every page of the client area.
 */
export const useWorkspaceSection = (): SidebarSection => {
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const { t } = useTranslation();

    const items: Array<SidebarItem | React.ReactElement> = [
        <SearchContainer key={'search'} />,
        { key: 'dashboard', name: t('nav.dashboard'), icon: ViewGridIcon, to: '/', exact: true },
    ];

    if (rootAdmin) {
        items.push({ key: 'admin', name: t('nav.administration'), icon: ShieldCheckIcon, href: '/admin' });
    }

    return { key: 'workspace', title: t('nav.workspace'), items };
};

export const useAccountSection = (): SidebarSection => {
    const { t } = useTranslation();

    return {
        key: 'account',
        title: t('nav.account'),
        items: routes.account
            .filter((route) => !!route.name)
            .map(({ path, name, icon, exact = false }) => ({
                key: path,
                name: t(name!),
                icon,
                exact,
                to: `/account/${path}`.replace('//', '/'),
            })),
    };
};
