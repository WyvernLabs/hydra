import { useEffect, useState } from 'react';
import { useStoreState } from '@/state/hooks';
import { pluginsAvailable } from '@/api/server/plugins';
import { modsAvailable } from '@/api/server/mods';
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

// Hydra: whether a server has a plugins folder (addon "plugins") or a mods
// folder (addon "mods"), asked once a minute per server, shared by every
// component that shows the navigation.
const FOLDER_CHECKS = { plugins: pluginsAvailable, mods: modsAvailable };

type FolderKey = keyof typeof FOLDER_CHECKS;

const folders = new Map<string, { at: number; value: Promise<boolean> }>();

// Components that show the navigation, told to ask again after forgetFolder().
const folderListeners = new Set<() => void>();

// Forgets what is known about the folder, e.g. when a page found it missing.
export const forgetFolder = (key: FolderKey, uuid: string) => {
    folders.delete(`${key}:${uuid}`);
    folderListeners.forEach((listener) => listener());
};

const hasFolder = (key: FolderKey, uuid: string): Promise<boolean> => {
    const cached = folders.get(`${key}:${uuid}`);
    if (cached && Date.now() - cached.at < 60000) return cached.value;
    const value = FOLDER_CHECKS[key](uuid).catch(() => false);
    folders.set(`${key}:${uuid}`, { at: Date.now(), value });

    return value;
};

// Whether the page of a folder addon is wanted (addon on, permission, egg) and
// whether the folder exists: null while the check runs. Without "hide", the
// page is shown everywhere and explains a missing folder itself.
const useFolderPage = (
    key: FolderKey,
    enabled: boolean,
    hide: boolean,
    buttons: ServerNavItem[],
    permissions: string[],
    eggId: number | undefined,
    uuid: string | undefined
) => {
    const [folder, setFolder] = useState<boolean | null>(null);
    const [tick, setTick] = useState(0);
    const button = buttons.find((item) => item.key === key);
    const wanted =
        enabled &&
        !!uuid &&
        can(permissions, `${key}.read`) &&
        (!button?.eggs || eggId === undefined || button.eggs.indexOf(eggId) >= 0);

    useEffect(() => {
        const listener = () => setTick((value) => value + 1);
        folderListeners.add(listener);

        return () => {
            folderListeners.delete(listener);
        };
    }, []);

    useEffect(() => {
        if (!wanted || !uuid || !hide) {
            setFolder(wanted && !hide);
            return;
        }
        setFolder(null);
        let active = true;
        hasFolder(key, uuid).then((value) => active && setFolder(value));

        return () => {
            active = false;
        };
    }, [wanted, uuid, hide, tick]);

    return { wanted, folder };
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
    const board = useStoreState((state) => state.settings.data!.board || false);
    const plugins = useStoreState((state) => state.settings.data!.plugins || false);
    const mods = useStoreState((state) => state.settings.data!.mods || false);
    const folderPages = useStoreState((state) => state.settings.data!.folderPages);
    const modpacks = useStoreState((state) => state.settings.data!.modpacks || false);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);

    const buttons = navigation.flatMap((category) => category.items);
    // The plugin and mod managers additionally need their folder on the server.
    const pluginPage = useFolderPage(
        'plugins',
        plugins,
        folderPages?.plugins ?? true,
        buttons,
        permissions,
        eggId,
        uuid
    );
    const modPage = useFolderPage('mods', mods, folderPages?.mods ?? true, buttons, permissions, eggId, uuid);

    // Pages of buttons hidden for this egg are not reachable either. Buttons the
    // navigation does not know (yet) stay visible. The dashboard is an addon.
    const isVisible = (navKey: string): boolean => {
        if (navKey === 'dashboard' && !dashboard) return false;
        if (navKey === 'properties' && !properties) return false;
        if (navKey === 'games' && !games) return false;
        if (navKey === 'versions' && !versions) return false;
        if (navKey === 'env' && !env) return false;
        if (navKey === 'board' && !board) return false;
        if (navKey === 'plugins' && (!plugins || pluginPage.folder !== true)) return false;
        if (navKey === 'mods' && (!mods || modPage.folder !== true)) return false;
        if (navKey === 'modpacks' && !modpacks) return false;
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

    // The pages of the folder addons stay reachable whatever the folder check
    // says: they leave by themselves when the folder is missing, so nobody ends
    // up on a "page not found" when the route vanishes under them.
    const isRoutable = (navKey: string): boolean => {
        const page = navKey === 'plugins' ? pluginPage : navKey === 'mods' ? modPage : null;

        return page ? page.wanted : isVisible(navKey);
    };

    return { categories, isVisible, isRoutable };
};
