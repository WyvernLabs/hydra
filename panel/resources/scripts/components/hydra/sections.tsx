import React from 'react';
import { ShieldCheckIcon, ViewGridIcon } from '@heroicons/react/outline';
import { useStoreState } from '@/state/hooks';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import { SidebarItem, SidebarSection } from '@/components/hydra/Sidebar';
import routes from '@/routers/routes';

/**
 * The "Workspace" section is shown on every page of the client area.
 */
export const useWorkspaceSection = (): SidebarSection => {
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    const items: Array<SidebarItem | React.ReactElement> = [
        <SearchContainer key={'search'} />,
        { key: 'dashboard', name: 'Dashboard', icon: ViewGridIcon, to: '/', exact: true },
    ];

    if (rootAdmin) {
        items.push({ key: 'admin', name: 'Administration', icon: ShieldCheckIcon, href: '/admin' });
    }

    return { key: 'workspace', title: 'Workspace', items };
};

export const accountSection = (): SidebarSection => ({
    key: 'account',
    title: 'Account',
    items: routes.account
        .filter((route) => !!route.name)
        .map(({ path, name, icon, exact = false }) => ({
            key: path,
            name: name!,
            icon,
            exact,
            to: `/account/${path}`.replace('//', '/'),
        })),
});
