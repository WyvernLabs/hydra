import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useFlashKey } from '@/plugins/useFlash';
import { deletePoolServer, PoolServer } from '@/api/account/pool';

// Hydra: deletes a pool server after its name was typed in (resource_pool addon).

interface Props {
    server: PoolServer | null;
    open: boolean;
    onClose: () => void;
    onDeleted: () => void;
}

export default ({ server, open, onClose, onDeleted }: Props) => {
    const { t } = useTranslation('client/pool');
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('pool:delete');
    const [confirm, setConfirm] = useState('');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!open) return;
        clearFlashes();
        setConfirm('');
        setBusy(false);
    }, [open]);

    const remove = () => {
        if (!server) return;
        clearFlashes();
        setBusy(true);
        deletePoolServer(server.identifier)
            .then(() => onDeleted())
            .catch((error) => {
                clearAndAddHttpError(error);
                setBusy(false);
            });
    };

    return (
        <Dialog
            open={open}
            onClose={() => !busy && onClose()}
            preventExternalClose={busy}
            hideCloseIcon={busy}
            title={t('delete_title', { name: server?.name || '' })}
        >
            <FlashMessageRender byKey={'pool:delete'} className={'mb-3'} />
            <p className={'text-sm text-gray-300'}>{t('delete_text')}</p>
            <label className={'mt-4 block text-xs text-gray-400'} htmlFor={'poolDeleteConfirm'}>
                {t('delete_confirm', { name: server?.name || '' })}
            </label>
            <Input
                id={'poolDeleteConfirm'}
                className={'mt-1'}
                value={confirm}
                onChange={(event) => setConfirm(event.currentTarget.value)}
                autoComplete={'off'}
            />
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={busy}>
                    {t('cancel')}
                </Button.Text>
                <Button.Danger onClick={remove} disabled={busy || confirm !== server?.name}>
                    {busy ? t('deleting') : t('delete')}
                </Button.Danger>
            </Dialog.Footer>
        </Dialog>
    );
};
