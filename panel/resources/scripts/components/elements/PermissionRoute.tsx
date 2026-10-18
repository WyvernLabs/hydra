import React from 'react';
import { Route } from 'react-router-dom';
import { RouteProps } from 'react-router';
import Can from '@/components/elements/Can';
import { ServerError } from '@/components/elements/ScreenBlock';
import { useTranslation } from 'react-i18next';

interface Props extends Omit<RouteProps, 'path'> {
    path: string;
    permission: string | string[] | null;
}

export default ({ permission, children, ...props }: Props) => {
    const { t } = useTranslation();

    return (
        <Route {...props}>
            {!permission ? (
                children
            ) : (
                <Can
                    matchAny
                    action={permission}
                    renderOnError={
                        <ServerError title={t('errors.access_denied')} message={t('errors.no_permission')} />
                    }
                >
                    {children}
                </Can>
            )}
        </Route>
    );
};
