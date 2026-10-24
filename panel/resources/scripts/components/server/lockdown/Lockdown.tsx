import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Lock } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { Alert } from '@/components/elements/alert';
import { Dialog } from '@/components/elements/dialog';
import PageContentBlock from '@/components/elements/PageContentBlock';
import setServerLockdown from '@/api/server/setServerLockdown';
import { httpErrorToHuman } from '@/api/http';
import { formatDate } from '@/lib/date';
import useFlash from '@/plugins/useFlash';

// Hydra: lockdown of a server by its owner. While it lasts only the owner and
// admins with "servers.lockdown" may use the server.

/**
 * Whether a failed request was refused because the server is in lockdown.
 */
export const isLockedDownError = (error: any): boolean =>
    error?.response?.data?.errors?.[0]?.code === 'ServerLockedDownException';

/**
 * Starts or lifts the lockdown after a confirmation. Returns what a button
 * needs and the dialog, which has to be rendered next to it.
 */
export const useLockdownToggle = (flashKey: string) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const lockdown = ServerContext.useStoreState((state) => state.server.data!.lockdown);
    const suspended = ServerContext.useStoreState((state) => state.server.suspendedAccess === 'limited');
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const { addFlash, clearFlashes } = useFlash();
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);

    const submit = () => {
        clearFlashes(flashKey);
        setLoading(true);
        setServerLockdown(uuid, !lockdown.active)
            .then(() => getServer(uuid))
            .then(() =>
                addFlash({
                    key: flashKey,
                    type: 'success',
                    message: t(lockdown.active ? 'lockdown.disabled' : 'lockdown.enabled'),
                })
            )
            .catch((error) => {
                console.error(error);
                addFlash({ key: flashKey, type: 'error', message: httpErrorToHuman(error) });
            })
            .then(() => {
                setLoading(false);
                setOpen(false);
            });
    };

    const dialog = (
        <Dialog.Confirm
            open={open}
            title={t(lockdown.active ? 'lockdown.confirm_disable_title' : 'lockdown.confirm_enable_title')}
            confirm={t(lockdown.active ? 'lockdown.disable' : 'lockdown.enable')}
            onClose={() => !loading && setOpen(false)}
            onConfirmed={submit}
        >
            {t(lockdown.active ? 'lockdown.confirm_disable_text' : 'lockdown.confirm_enable_text')}
        </Dialog.Confirm>
    );

    return {
        active: lockdown.active,
        since: lockdown.since,
        canToggle: lockdown.canToggle,
        // The suspension comes first: the owner cannot change the lockdown meanwhile.
        disabled: suspended || loading,
        suspended,
        request: () => setOpen(true),
        dialog,
    };
};

/**
 * Banner above every page of a server in lockdown, for those who may still use it.
 */
export const LockdownBanner = () => {
    const { t } = useTranslation('client/server');
    const lockdown = ServerContext.useStoreState((state) => state.server.data?.lockdown);

    if (!lockdown?.active || lockdown.lockedOut) return null;

    return (
        <Alert type={'danger'} className={'mb-4'}>
            {lockdown.since ? t('lockdown.active', { date: formatDate(lockdown.since) }) : t('lockdown.banner')}
        </Alert>
    );
};

/**
 * Shown instead of every page to users who are locked out by the lockdown.
 */
export default () => {
    const { t } = useTranslation('client/server');

    return (
        <PageContentBlock title={t('lockdown.locked_out_title')}>
            <div className={'flex justify-center'}>
                <div className={'hydra-card w-full p-12 text-center sm:w-3/4 md:w-1/2 md:p-16'}>
                    <Lock className={'mx-auto h-16 w-16 text-red-400'} />
                    <h2 className={'mt-6 text-3xl font-bold text-neutral-100'}>{t('lockdown.locked_out_title')}</h2>
                    <p className={'mt-2 text-sm text-neutral-300'}>{t('lockdown.locked_out_text')}</p>
                    <Link
                        to={'/'}
                        className={'mt-6 inline-block text-sm text-primary-400 no-underline hover:text-primary-300'}
                    >
                        {t('lockdown.back')}
                    </Link>
                </div>
            </div>
        </PageContentBlock>
    );
};
