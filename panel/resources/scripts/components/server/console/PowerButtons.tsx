import React, { useEffect, useState } from 'react';
import { Play, RotateCw, Square, Zap } from 'lucide-react';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import { PowerAction } from '@/components/server/console/ServerConsoleContainer';
import { Dialog } from '@/components/elements/dialog';
import { useTranslation } from 'react-i18next';

interface PowerButtonProps {
    className?: string;
}

type Icon = React.ComponentType<{ className?: string }>;

// Hydra: grey buttons with a thin coloured border and a coloured icon: start
// green, restart yellow, stop and kill red. Disabled ones are grey all over.
const PowerButton = ({
    color,
    icon: Icon,
    disabled,
    onClick,
    children,
}: {
    color: string;
    icon: Icon;
    disabled: boolean;
    onClick: (e: React.MouseEvent<HTMLButtonElement, MouseEvent>) => void;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        className={'hydra-power-btn flex-1'}
        style={{ '--power-color': color } as React.CSSProperties}
        disabled={disabled}
        onClick={onClick}
    >
        <Icon className={'hydra-power-btn-icon'} />
        {children}
    </button>
);

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
                <PowerButton
                    color={'#00ff00'}
                    icon={Play}
                    disabled={status !== 'offline'}
                    onClick={onButtonClick.bind(this, 'start')}
                >
                    {t('console.start')}
                </PowerButton>
            </Can>
            <Can action={'control.restart'}>
                <PowerButton
                    color={'#ffff00'}
                    icon={RotateCw}
                    disabled={!status}
                    onClick={onButtonClick.bind(this, 'restart')}
                >
                    {t('console.restart')}
                </PowerButton>
            </Can>
            <Can action={'control.stop'}>
                <PowerButton
                    color={'#ff0000'}
                    icon={killable ? Zap : Square}
                    disabled={status === 'offline'}
                    onClick={onButtonClick.bind(this, killable ? 'kill' : 'stop')}
                >
                    {killable ? t('console.kill') : t('console.stop')}
                </PowerButton>
            </Can>
        </div>
    );
};
