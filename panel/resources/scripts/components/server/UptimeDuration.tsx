import React from 'react';
import { useTranslation } from 'react-i18next';

export default ({ uptime }: { uptime: number }) => {
    const { t } = useTranslation('client/server');
    const days = Math.floor(uptime / (24 * 60 * 60));
    const hours = Math.floor((Math.floor(uptime) / 60 / 60) % 24);
    const remainder = Math.floor(uptime - hours * 60 * 60);
    const minutes = Math.floor((remainder / 60) % 60);
    const seconds = remainder % 60;

    if (days > 0) {
        return <>{t('details.uptime_days', { days, hours, minutes })}</>;
    }

    return <>{t('details.uptime_hours', { hours, minutes, seconds })}</>;
};
