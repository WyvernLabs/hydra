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

export default () => {
    const location = useLocation();
    const workspace = useWorkspaceSection();
    const account = useAccountSection();
    const links = useLinksSection();

    return (
        <HydraLayout sections={links ? [workspace, account, links] : [workspace, account]}>
            <TransitionRouter>
                <React.Suspense fallback={<Spinner centered />}>
                    <Switch location={location}>
                        <Route path={'/'} exact>
                            <DashboardContainer />
                        </Route>
                        {routes.account.map(({ path, component: Component }) => (
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
