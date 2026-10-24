import { useStoreState } from '@/state/hooks';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';
import { ServerNavCategory, ServerNavItem } from '@/state/settings';

// Hydra: the server navigation set in the admin area (Design > Navigation),
// reduced to what the current user may see on the current server.

// Parts of a server that stay available while it is suspended.
export const SUSPENDED_PATHS = ['/files', '/databases', '/backups'];

export const isSuspendedPath = (path: string) => SUSPENDED_PATHS.some((allowed) => path.startsWith(allowed));

// Hydra: link to a server from the lists: its dashboard when the addon says so.
export const useServerLink = () => {
    const landing = useStoreState((state) => state.settings.data!.dashboard?.landing || false);

    return (id: string) => `/server/${id}${landing ? '/dashboard' : ''}`;
};

const can = (userPermissions: string[], permission: string | string[] | null): boolean => {
    if (!permission || userPermissions[0] === '*') return true;

    // Same rules as usePermissions(): "file.*" matches any permission of the file namespace.
    return (Array.isArray(permission) ? permission : [permission]).some((p) =>
        p.endsWith('.*')
            ? userPermissions.some((own) => own.startsWith(p.split('.')[0]))
            : userPermissions.indexOf(p) >= 0
    );
};

export interface VisibleNavItem extends ServerNavItem {
    path: string;
    name: string;
    exact?: boolean;
}

export interface VisibleNavCategory extends Omit<ServerNavCategory, 'items'> {
    items: VisibleNavItem[];
}

export const useServerNavigation = () => {
    const navigation = useStoreState((state) => state.settings.data!.serverNavigation || []);
    const eggId = ServerContext.useStoreState((state) => state.server.data?.eggId);
    const permissions = ServerContext.useStoreState((state) => state.server.permissions);
    const suspendedAccess = ServerContext.useStoreState((state) => state.server.suspendedAccess);

    const dashboard = useStoreState((state) => state.settings.data!.dashboard?.enabled || false);
    const properties = useStoreState((state) => state.settings.data!.properties?.enabled || false);
    const games = useStoreState((state) => state.settings.data!.games || false);
    const versions = useStoreState((state) => state.settings.data!.versions || false);
    const env = useStoreState((state) => state.settings.data!.env?.enabled || false);

    const buttons = navigation.flatMap((category) => category.items);

    // Pages of buttons hidden for this egg are not reachable either. Buttons the
    // navigation does not know (yet) stay visible. The dashboard is an addon.
    const isVisible = (navKey: string): boolean => {
        if (navKey === 'dashboard' && !dashboard) return false;
        if (navKey === 'properties' && !properties) return false;
        if (navKey === 'games' && !games) return false;
        if (navKey === 'versions' && !versions) return false;
        if (navKey === 'env' && !env) return false;
        const button = buttons.find((item) => item.key === navKey);

        return !button || !button.eggs || eggId === undefined || button.eggs.indexOf(eggId) >= 0;
    };

    const categories: VisibleNavCategory[] = navigation
        .map((category) => ({
            ...category,
            items: category.items.flatMap((item) => {
                const route = routes.server.find((r) => r.navKey === item.key && !!r.name);
                if (!route || !isVisible(item.key) || !can(permissions, route.permission)) return [];
                if (suspendedAccess === 'limited' && !isSuspendedPath(route.path)) return [];

                return [{ ...item, path: route.path, name: route.name!, exact: route.exact }];
            }),
        }))
        // Categories without visible buttons are hidden.
        .filter((category) => category.items.length > 0);

    return { categories, isVisible };
};
