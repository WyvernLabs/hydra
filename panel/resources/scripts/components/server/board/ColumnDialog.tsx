import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import { BoardColumn, createColumn, updateColumn } from '@/api/server/board';
import { DIALOG_FLASH, useBoard } from '@/components/server/board/context';
import ColorPicker from '@/components/server/board/ColorPicker';

// Hydra: create a column or change its name and color.

interface Props {
    open: boolean;
    column: BoardColumn | null;
    onClose: () => void;
}

export default ({ open, column, onClose }: Props) => {
    const { t } = useTranslation('client/board');
    const { run } = useBoard();
    const [name, setName] = useState('');
    const [color, setColor] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        setName(column?.name || '');
        setColor(column ? column.color : null);
    }, [open, column?.id]);

    const submit = (event?: React.FormEvent) => {
        event?.preventDefault();
        if (!name.trim() || saving) return;
        setSaving(true);
        run(
            (uuid) =>
                column
                    ? updateColumn(uuid, column.id, { name: name.trim(), color })
                    : createColumn(uuid, { name: name.trim(), color }),
            DIALOG_FLASH
        ).then((ok) => {
            setSaving(false);
            if (ok) onClose();
        });
    };

    return (
        <Dialog open={open} onClose={onClose} title={column ? t('column.edit') : t('column.create')}>
            <FlashMessageRender byKey={DIALOG_FLASH} className={'mb-3'} />
            <form onSubmit={submit} className={'space-y-4'}>
                <div>
                    <label htmlFor={'board-column-name'} className={'mb-1 block text-xs text-gray-300'}>
                        {t('column.name')}
                    </label>
                    <input
                        id={'board-column-name'}
                        autoFocus
                        maxLength={64}
                        value={name}
                        onChange={(event) => setName(event.currentTarget.value)}
                        placeholder={t('column.name_placeholder')}
                        className={
                            'w-full rounded border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-gray-100 outline-none focus:border-primary-400'
                        }
                    />
                </div>
                <div>
                    <p className={'mb-1.5 text-xs text-gray-300'}>{t('column.color')}</p>
                    <ColorPicker value={color} onChange={setColor} allowNone />
                </div>
                <button type={'submit'} className={'hidden'} />
            </form>
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('actions.cancel')}</Button.Text>
                <Button onClick={() => submit()} disabled={!name.trim() || saving}>
                    {column ? t('actions.save') : t('column.create')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};
