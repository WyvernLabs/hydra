import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useFlashKey } from '@/plugins/useFlash';
import deleteAccount from '@/api/account/deleteAccount';

/**
 * Hydra: lets users delete their own account after typing the confirmation
 * word ("DELETE" or "LÖSCHEN") and their password.
 */
export default () => {
    const { t } = useTranslation('client/account');
    const [open, setOpen] = useState(false);
    const [confirm, setConfirm] = useState('');
    const [password, setPassword] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('account:delete');

    const word = t('delete.word');
    const confirmed = ['DELETE', 'LÖSCHEN'].includes(confirm.trim().toUpperCase()) && password.length > 0;

    const close = () => {
        if (submitting) return;
        setOpen(false);
        setConfirm('');
        setPassword('');
        clearFlashes();
    };

    const submit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        if (!confirmed || submitting) return;

        setSubmitting(true);
        clearFlashes();
        deleteAccount(password, confirm)
            .then(() => {
                // The session is gone, so start over at the login page.
                window.location.href = '/auth/login';
            })
            .catch((error) => {
                clearAndAddHttpError(error);
                setSubmitting(false);
            });
    };

    return (
        <section className={'hydra-box hydra-box--danger'}>
            <h3 className={'hydra-box-header'}>{t('delete.title')}</h3>
            <div className={'hydra-box-body hydra-danger-row'}>
                <p>{t('delete.description')}</p>
                <Button.Danger onClick={() => setOpen(true)}>{t('delete.button')}</Button.Danger>
            </div>
            <Dialog
                open={open}
                onClose={close}
                title={t('delete.dialog_title')}
                description={t('delete.dialog_description')}
                preventExternalClose={submitting}
            >
                <form id={'delete-account-form'} className={'mt-4'} onSubmit={submit}>
                    <FlashMessageRender byKey={'account:delete'} className={'mb-4'} />
                    <Label htmlFor={'delete_confirm'}>{t('delete.type_word', { word })}</Label>
                    <Input
                        id={'delete_confirm'}
                        value={confirm}
                        autoComplete={'off'}
                        placeholder={word}
                        onChange={(e) => setConfirm(e.currentTarget.value)}
                    />
                    <div className={'mt-4'}>
                        <Label htmlFor={'delete_password'}>{t('delete.password')}</Label>
                        <Input
                            id={'delete_password'}
                            type={'password'}
                            value={password}
                            autoComplete={'current-password'}
                            onChange={(e) => setPassword(e.currentTarget.value)}
                        />
                    </div>
                </form>
                <Dialog.Footer>
                    <Button.Text onClick={close} disabled={submitting}>
                        {t('client/common:actions.cancel')}
                    </Button.Text>
                    <Button.Danger type={'submit'} form={'delete-account-form'} disabled={!confirmed || submitting}>
                        {t('delete.confirm')}
                    </Button.Danger>
                </Dialog.Footer>
            </Dialog>
        </section>
    );
};
