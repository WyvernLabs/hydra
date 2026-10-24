import React from 'react';
import { Route, Switch } from 'react-router-dom';
import DashboardContainer from '@/components/dashboard/DashboardContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import TransitionRouter from '@/TransitionRouter';
import { useLocation } from 'react-router';
import Spinner from '@/components/elements/Spinner';
import routes from '@/routers/routes';
import HydraLayout from '@/components/hydra/HydraLayout';
import { useAccountSection, useLinksSection, useWorkspaceSection } from '@/components/hydra/sections';
import PageContainer from '@/components/dashboard/PageContainer';
import { useStoreState } from '@/state/hooks';

export default () => {
    const location = useLocation();
    const workspace = useWorkspaceSection();
    const account = useAccountSection();
    const links = useLinksSection();
    // Hydra: pages of addons that are off do not exist.
    const settings = useStoreState((state) => state.settings.data!);
    const accountRoutes = routes.account.filter((route) => !route.addon || settings[route.addon].enabled);

    return (
        <HydraLayout sections={links ? [workspace, account, links] : [workspace, account]}>
            <TransitionRouter>
                <React.Suspense fallback={<Spinner centered />}>
                    <Switch location={location}>
                        <Route path={'/'} exact>
                            <DashboardContainer />
                        </Route>
                        {accountRoutes.map(({ path, component: Component }) => (
                            <Route key={path} path={`/account/${path}`.replace('//', '/')} exact>
                                <Component />
                            </Route>
                        ))}
                        <Route path={'/pages/:slug'} exact>
                            <PageContainer />
                        </Route>
                        <Route path={'*'}>
                            <NotFound />
                        </Route>
                    </Switch>
                </React.Suspense>
            </TransitionRouter>
        </HydraLayout>
    );
};
