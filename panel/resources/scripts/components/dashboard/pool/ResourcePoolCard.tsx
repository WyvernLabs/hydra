import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ServerCog } from 'lucide-react';
import { ResourcePool } from '@/api/account/pool';
import ManagePoolDialog from '@/components/dashboard/pool/ManagePoolDialog';

// Hydra: card next to the greeting of the dashboard (resource_pool addon):
// how many servers the user has and may have, and the button to manage them.

interface Props {
    pool: ResourcePool;
    onChanged: () => void;
}

export default ({ pool, onChanged }: Props) => {
    const { t } = useTranslation('client/pool');
    const [open, setOpen] = useState(false);
    const limit = pool.limits.servers;
    const used = pool.used.servers;
    const percent = limit === null ? 0 : limit === 0 ? 100 : Math.min(100, (used / limit) * 100);

    return (
        <div className={'hydra-dashboard-pool'}>
            <span className={'hydra-dashboard-pool-label'}>{t('card_title')}</span>
            <strong className={'hydra-dashboard-pool-value'}>
                {used} / {limit === null ? '∞' : limit}
            </strong>
            {limit !== null && (
                <div className={'hydra-dashboard-pool-bar'}>
                    <span style={{ width: `${percent}%` }} />
                </div>
            )}
            <button type={'button'} className={'hydra-dashboard-pool-button'} onClick={() => setOpen(true)}>
                {t('manage')}
            </button>
            <ServerCog className={'hydra-dashboard-pool-icon'} aria-hidden />
            <ManagePoolDialog pool={pool} open={open} onClose={() => setOpen(false)} onChanged={onChanged} />
        </div>
    );
};
