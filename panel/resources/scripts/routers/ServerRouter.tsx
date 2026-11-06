import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { Redirect, Route, Switch, useRouteMatch } from 'react-router-dom';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { ExternalLink as ExternalLinkIcon } from 'lucide-react';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import HydraLayout from '@/components/hydra/HydraLayout';
import { SidebarItem, SidebarSection } from '@/components/hydra/Sidebar';
import { iconComponent } from '@/components/hydra/HydraIcon';
import { isSuspendedPath, useServerNavigation } from '@/components/hydra/serverNavigation';
import { useWorkspaceSection } from '@/components/hydra/sections';
import ServerSidebarHeader from '@/components/hydra/ServerSidebarHeader';
import { useTranslation } from 'react-i18next';
import SuspendedNotice, { SuspendedBanner } from '@/components/server/SuspendedNotice';
import LockdownNotice, { LockdownBanner } from '@/components/server/lockdown/Lockdown';
import ServerHeader from '@/components/server/ServerHeader';
import FileTransfers from '@/components/server/files/FileTransfers';

export default () => {
    const match = useRouteMatch<{ id: string }>();
    const location = useLocation();

    const rootAdmin = useStoreState((state) => state.user.data!.serverAccess);
    const canManageAsAdmin = useStoreState((state) => {
        const user = state.user.data!;
        return user.rootAdmin || user.adminPermissions.indexOf('servers.view') >= 0;
    });
    const [error, setError] = useState('');

    const id = ServerContext.useStoreState((state) => state.server.data?.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const inConflictState = ServerContext.useStoreState((state) => state.server.inConflictState);
    const suspendedAccess = ServerContext.useStoreState((state) => state.server.suspendedAccess);
    // Hydra: users locked out by a lockdown get neither pages nor navigation.
    const lockedOut = ServerContext.useStoreState((state) => state.server.data?.lockdown.lockedOut || false);
    const { categories: allCategories, isVisible } = useServerNavigation();
    const categories = lockedOut ? [] : allCategories;
    // Hydra: on a suspended server, users without full access only see files, databases and backups.
    // Pages whose button is hidden for the egg of this server are not reachable either.
    const serverRoutes = routes.server.filter(
        (route) => isVisible(route.navKey) && (suspendedAccess !== 'limited' || isSuspendedPath(route.path))
    );
    const firstPage = categories[0]?.items[0]?.path;
    const serverId = ServerContext.useStoreState((state) => state.server.data?.internalId);
    const background = ServerContext.useStoreState((state) => state.server.data?.background);
    const workspace = useWorkspaceSection();
    const { t } = useTranslation();
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const clearServerState = ServerContext.useStoreActions((actions) => actions.clearServerState);

    const to = (value: string, url = false) => {
        if (value === '/') {
            return url ? match.url : match.path;
        }
        return `${(url ? match.url : match.path).replace(/\/*$/, '')}/${value.replace(/^\/+/, '')}`;
    };

    // Hydra: one sidebar section per category of the navigation set in the admin area.
    const adminLink: SidebarItem = {
        key: 'admin',
        name: t('nav.manage_as_admin'),
        icon: ExternalLinkIcon,
        href: `/admin/servers/view/${serverId}`,
    };
    const serverSections: SidebarSection[] = !uuid
        ? [{ key: 'server', title: t('nav.server'), items: [] }]
        : (categories.length > 0 ? categories : [{ id: 'server', name: null, color: null, items: [] }]).map(
              (category, index, all) => ({
                  key: `server-${category.id}`,
                  title: category.name || t('nav.server'),
                  header: index === 0 ? <ServerSidebarHeader /> : undefined,
                  items: [
                      ...category.items.map(
                          (item): SidebarItem => ({
                              key: item.key,
                              name: t(item.name),
                              icon: iconComponent(item.icon),
                              iconColor: category.color,
                              exact: item.exact,
                              to: to(item.path, true),
                          })
                      ),
                      ...(canManageAsAdmin && index === all.length - 1 ? [adminLink] : []),
                  ],
              })
          );

    useEffect(
        () => () => {
            clearServerState();
        },
        []
    );

    useEffect(() => {
        setError('');

        getServer(match.params.id).catch((error) => {
            console.error(error);
            setError(httpErrorToHuman(error));
        });

        return () => {
            clearServerState();
        };
    }, [match.params.id]);

    return (
        <HydraLayout key={'server-router'} sections={[workspace, ...serverSections]} background={background}>
            {!uuid || !id ? (
                error ? (
                    <ServerError message={error} />
                ) : (
                    <Spinner size={'large'} centered />
                )
            ) : lockedOut ? (
                <LockdownNotice />
            ) : (
                <>
                    <InstallListener />
                    <TransferListener />
                    <FileTransfers />
                    {suspendedAccess !== 'limited' && <WebsocketHandler />}
                    {inConflictState &&
                    suspendedAccess === 'none' &&
                    (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                        <ConflictStateRenderer />
                    ) : (
                        <ErrorBoundary>
                            <ServerHeader />
                            <SuspendedBanner />
                            <LockdownBanner />
                            <TransitionRouter>
                                <Switch location={location}>
                                    {suspendedAccess === 'limited' ? (
                                        <Route path={to('/')} exact>
                                            <SuspendedNotice />
                                        </Route>
                                    ) : (
                                        // Without a console for this egg the first visible page opens instead.
                                        !isVisible('console') &&
                                        firstPage && (
                                            <Route path={to('/')} exact>
                                                <Redirect to={to(firstPage, true)} />
                                            </Route>
                                        )
                                    )}
                                    {serverRoutes.map(({ path, permission, component: Component }) => (
                                        <PermissionRoute key={path} permission={permission} path={to(path)} exact>
                                            <Spinner.Suspense>
                                                <Component />
                                            </Spinner.Suspense>
                                        </PermissionRoute>
                                    ))}
                                    <Route path={'*'} component={NotFound} />
                                </Switch>
                            </TransitionRouter>
                        </ErrorBoundary>
                    )}
                </>
            )}
        </HydraLayout>
    );
};
