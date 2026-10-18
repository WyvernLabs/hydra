import React from 'react';
import Can from '@/components/elements/Can';
import { ServerError } from '@/components/elements/ScreenBlock';
import { useTranslation } from 'react-i18next';

export interface RequireServerPermissionProps {
    permissions: string | string[];
}

const RequireServerPermission: React.FC<RequireServerPermissionProps> = ({ children, permissions }) => {
    const { t } = useTranslation();

    return (
        <Can
            action={permissions}
            renderOnError={<ServerError title={t('errors.access_denied')} message={t('errors.no_permission')} />}
        >
            {children}
        </Can>
    );
};

export default RequireServerPermission;
