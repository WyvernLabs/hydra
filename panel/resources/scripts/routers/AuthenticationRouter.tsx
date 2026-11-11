import React from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import LoginContainer from '@/components/auth/LoginContainer';
import ForgotPasswordContainer from '@/components/auth/ForgotPasswordContainer';
import ResetPasswordContainer from '@/components/auth/ResetPasswordContainer';
import LoginCheckpointContainer from '@/components/auth/LoginCheckpointContainer';
import RegisterContainer from '@/components/auth/RegisterContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import { useHistory, useLocation } from 'react-router';
import { useStoreState } from '@/state/hooks';
import HydraBackdrop from '@/components/hydra/HydraBackdrop';
import { useDesignerBackground, useLoginDesign } from '@/lib/designer';

export default () => {
    const history = useHistory();
    const location = useLocation();
    const { path } = useRouteMatch();
    const saved = useStoreState((state) => state.settings.data!.backgrounds?.login);
    const background = useDesignerBackground('login', saved);
    const style = useLoginDesign().style;

    return (
        <div className={`hydra-auth-page hydra-auth-page--${style} ${style === 'classic' ? 'pt-8 xl:pt-32' : ''}`}>
            <HydraBackdrop background={background} />
            <Switch location={location}>
                <Route path={`${path}/login`} component={LoginContainer} exact />
                <Route path={`${path}/login/checkpoint`} component={LoginCheckpointContainer} />
                <Route path={`${path}/password`} component={ForgotPasswordContainer} exact />
                <Route path={`${path}/password/reset/:token`} component={ResetPasswordContainer} />
                <Route path={`${path}/register`} component={RegisterContainer} exact />
                <Route path={`${path}/checkpoint`} />
                <Route path={'*'}>
                    <NotFound onBack={() => history.push('/auth/login')} />
                </Route>
            </Switch>
        </div>
    );
};
