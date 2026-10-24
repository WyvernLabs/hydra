import React from 'react';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { useLockdownToggle } from '@/components/server/lockdown/Lockdown';
import { formatDate } from '@/lib/date';

// Hydra: lockdown of the server, only for the owner and admins with "servers.lockdown".
export default () => {
    const { t } = useTranslation('client/server');
    const lockdown = useLockdownToggle('settings');

    if (!lockdown.canToggle) return null;

    return (
        <TitledGreyBox title={t('lockdown.title')} css={tw`mb-6 md:mb-10`}>
            {lockdown.dialog}
            <p css={tw`text-sm`}>
                {lockdown.active && lockdown.since
                    ? t('lockdown.active', { date: formatDate(lockdown.since) })
                    : t('lockdown.text')}
            </p>
            {lockdown.suspended && <p css={tw`text-xs text-neutral-400 mt-2`}>{t('lockdown.suspended')}</p>}
            <div css={tw`mt-6 text-right`}>
                {lockdown.active ? (
                    <Button disabled={lockdown.disabled} onClick={lockdown.request}>
                        {t('lockdown.disable')}
                    </Button>
                ) : (
                    <Button.Danger disabled={lockdown.disabled} onClick={lockdown.request}>
                        {t('lockdown.enable')}
                    </Button.Danger>
                )}
            </div>
        </TitledGreyBox>
    );
};
