import React from 'react';
import { Dialog, RenderDialogProps } from './';
import { Button } from '@/components/elements/button/index';
import { useTranslation } from 'react-i18next';

type ConfirmationProps = Omit<RenderDialogProps, 'description' | 'children'> & {
    children: React.ReactNode;
    confirm?: string | undefined;
    onConfirmed: (e: React.MouseEvent<HTMLButtonElement, MouseEvent>) => void;
};

export default ({ confirm, children, onConfirmed, ...props }: ConfirmationProps) => {
    const { t } = useTranslation();

    return (
        <Dialog {...props} description={typeof children === 'string' ? children : undefined}>
            {typeof children !== 'string' && children}
            <Dialog.Footer>
                <Button.Text onClick={props.onClose}>{t('actions.cancel')}</Button.Text>
                <Button.Danger onClick={onConfirmed}>{confirm ?? t('actions.okay')}</Button.Danger>
            </Dialog.Footer>
        </Dialog>
    );
};
