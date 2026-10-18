import React, { useEffect, useState } from 'react';
import { Button } from '@/components/elements/button/index';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import { useTranslation } from 'react-i18next';

interface PowerButtonProps {
    className?: string;
}

export default ({ className }: PowerButtonProps) => {
    const [open, setOpen] = useState(false);
    const { t } = useTranslation('client/server');
    const status = ServerContext.useStoreState((state) => state.status.value);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);

    const killable = status === 'stopping';
    const onButtonClick = (
        action: PowerAction | 'kill-confirmed',
        e: React.MouseEvent<HTMLButtonElement, MouseEvent>
    ): void => {
        e.preventDefault();
        if (action === 'kill') {
            return setOpen(true);
        }

        if (instance) {
            setOpen(false);
            instance.send('set state', action === 'kill-confirmed' ? 'kill' : action);
        }
    };

    useEffect(() => {
        if (status === 'offline') {
            setOpen(false);
        }
    }, [status]);

    return (
        <div className={className}>
            <Dialog.Confirm
                open={open}
                hideCloseIcon
                onClose={() => setOpen(false)}
                title={t('console.kill_title')}
                confirm={t('client/common:actions.continue')}
                onConfirmed={onButtonClick.bind(this, 'kill-confirmed')}
            >
                {t('console.kill_text')}
            </Dialog.Confirm>
            <Can action={'control.start'}>
                <Button
                    className={'flex-1'}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    {t('console.start')}
                </Button>
            </Can>
            <Can action={'control.restart'}>
                <Button.Text className={'flex-1'} disabled={!status} onClick={onButtonClick.bind(this, 'restart')}>
                    {t('console.restart')}
                </Button.Text>
            </Can>
            <Can action={'control.stop'}>
                <Button.Danger
                    className={'flex-1'}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    {killable ? t('console.kill') : t('console.stop')}
                </Button.Danger>
            </Can>
        </div>
    );
};
