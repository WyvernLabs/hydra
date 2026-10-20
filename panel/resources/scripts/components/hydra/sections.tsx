import React from 'react';
import { ShieldCheck as ShieldCheckIcon, LayoutGrid as ViewGridIcon } from 'lucide-react';
import { useStoreState } from '@/state/hooks';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import { SidebarItem, SidebarSection } from '@/components/hydra/Sidebar';
import routes from '@/routers/routes';
import { useTranslation } from 'react-i18next';
import { linkIcon } from '@/components/hydra/LinkIcon';

/**
 * The "Workspace" section is shown on every page of the client area.
 */
export const useWorkspaceSection = (): SidebarSection => {
    const adminAccess = useStoreState((state) => state.user.data!.adminAccess);
    const { t } = useTranslation();

    const items: Array<SidebarItem | React.ReactElement> = [
        <SearchContainer key={'search'} />,
        { key: 'dashboard', name: t('nav.dashboard'), icon: ViewGridIcon, to: '/', exact: true },
    ];

    if (adminAccess) {
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

/**
 * Hydra: the "Links" section with the links and pages set up in the admin
 * area (e.g. imprint, terms, Discord). Null when there are none.
 */
export const useLinksSection = (): SidebarSection | null => {
    const links = useStoreState((state) => state.settings.data!.links || []);
    const { t } = useTranslation();

    if (links.length === 0) {
        return null;
    }

    return {
        key: 'links',
        title: t('nav.links'),
        items: links.map((link) =>
            link.type === 'page'
                ? { key: `link-${link.id}`, name: link.title, icon: linkIcon(link.icon), to: `/pages/${link.slug}` }
                : {
                      key: `link-${link.id}`,
                      name: link.title,
                      icon: linkIcon(link.icon),
                      href: link.url!,
                      external: true,
                  }
        ),
    };
};
