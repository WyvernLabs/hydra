import TransferListener from '@/components/server/TransferListener';
import React, { useEffect, useState } from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import TransitionRouter from '@/TransitionRouter';
import WebsocketHandler from '@/components/server/WebsocketHandler';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import Spinner from '@/components/elements/Spinner';
import { NotFound, ServerError } from '@/components/elements/ScreenBlock';
import { httpErrorToHuman } from '@/api/http';
import { useStoreState } from 'easy-peasy';
import InstallListener from '@/components/server/InstallListener';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { ExternalLinkIcon } from '@heroicons/react/outline';
import { useLocation } from 'react-router';
import ConflictStateRenderer from '@/components/server/ConflictStateRenderer';
import PermissionRoute from '@/components/elements/PermissionRoute';
import routes from '@/routers/routes';
import HydraLayout from '@/components/hydra/HydraLayout';
import { SidebarLink, SidebarSection } from '@/components/hydra/Sidebar';
import { useWorkspaceSection } from '@/components/hydra/sections';
import ServerSidebarHeader from '@/components/hydra/ServerSidebarHeader';
import { useTranslation } from 'react-i18next';
import SuspendedNotice, { isSuspendedPath, SuspendedBanner } from '@/components/server/SuspendedNotice';

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
    // Hydra: on a suspended server, users without full access only see files, databases and backups.
    const serverRoutes =
        suspendedAccess === 'limited' ? routes.server.filter((route) => isSuspendedPath(route.path)) : routes.server;
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

    const serverSection: SidebarSection = {
        key: 'server',
        title: t('nav.server'),
        header: uuid ? <ServerSidebarHeader /> : undefined,
        items: !uuid
            ? []
            : [
                  ...serverRoutes
                      .filter((route) => !!route.name)
                      .map(({ path, name, icon, exact, permission }) => {
                          const link = (
                              <SidebarLink
                                  key={path}
                                  item={{ key: path, name: t(name!), icon, exact, to: to(path, true) }}
                              />
                          );

                          return permission ? (
                              <Can key={path} action={permission} matchAny>
                                  {link}
                              </Can>
                          ) : (
                              link
                          );
                      }),
                  ...(canManageAsAdmin
                      ? [
                            <SidebarLink
                                key={'admin'}
                                item={{
                                    key: 'admin',
                                    name: t('nav.manage_as_admin'),
                                    icon: ExternalLinkIcon,
                                    href: `/admin/servers/view/${serverId}`,
                                }}
                            />,
                        ]
                      : []),
              ],
    };

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
        <HydraLayout key={'server-router'} sections={[workspace, serverSection]} background={background}>
            {!uuid || !id ? (
                error ? (
                    <ServerError message={error} />
                ) : (
                    <Spinner size={'large'} centered />
                )
            ) : (
                <>
                    <InstallListener />
                    <TransferListener />
                    {suspendedAccess !== 'limited' && <WebsocketHandler />}
                    {inConflictState &&
                    suspendedAccess === 'none' &&
                    (!rootAdmin || (rootAdmin && !location.pathname.endsWith(`/server/${id}`))) ? (
                        <ConflictStateRenderer />
                    ) : (
                        <ErrorBoundary>
                            <SuspendedBanner />
                            <TransitionRouter>
                                <Switch location={location}>
                                    {suspendedAccess === 'limited' && (
                                        <Route path={to('/')} exact>
                                            <SuspendedNotice />
                                        </Route>
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
