import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Input, { Textarea } from '@/components/elements/Input';
import { CellValue, DatabaseColumn, getRow, insertRow, updateRow } from '@/api/server/databases/manager';

// Hydra: form to add a row or change one, field by field.

type Mode = 'value' | 'null' | 'default';

interface FieldState {
    mode: Mode;
    value: string;
}

interface Props {
    databaseId: string;
    table: string;
    columns: DatabaseColumn[];
    // Primary key of the row to edit, null to add a row.
    rowKey: Record<string, CellValue> | null;
    open: boolean;
    onClose: () => void;
    onSaved: () => void;
}

const isLong = (type: string) => /text|json|blob|char\((\d{3,})\)/i.test(type);

export default ({ databaseId, table, columns, rowKey, open, onClose, onSaved }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [fields, setFields] = useState<Record<string, FieldState>>({});
    const [original, setOriginal] = useState<Record<string, CellValue>>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;
        clearFlashes('database:row');

        if (!rowKey) {
            // New rows use the default of a column until a value is entered.
            const initial: Record<string, FieldState> = {};
            columns.forEach((column) => {
                const hasDefault =
                    column.default !== null || column.extra.includes('auto_increment') || column.nullable;
                initial[column.name] = { mode: hasDefault ? 'default' : 'value', value: '' };
            });
            setFields(initial);
            setOriginal({});
            return;
        }

        setLoading(true);
        getRow(uuid, databaseId, table, rowKey)
            .then((values) => {
                const initial: Record<string, FieldState> = {};
                columns.forEach((column) => {
                    const value = values[column.name];
                    initial[column.name] = {
                        mode: value === null ? 'null' : 'value',
                        value: value === null ? '' : String(value),
                    };
                });
                setFields(initial);
                setOriginal(values);
            })
            .catch((error) => clearAndAddHttpError({ key: 'database:row', error }))
            .then(() => setLoading(false));
    }, [open, rowKey, table]);

    const set = (name: string, state: Partial<FieldState>) =>
        setFields((current) => ({ ...current, [name]: { ...current[name], ...state } }));

    const submit = () => {
        const values: Record<string, CellValue> = {};
        columns.forEach((column) => {
            const field = fields[column.name];
            if (!field || column.binary || field.mode === 'default') return;
            const value = field.mode === 'null' ? null : field.value;
            // Only changed columns are written.
            if (rowKey && (original[column.name] === null ? null : String(original[column.name])) === value) return;
            values[column.name] = value;
        });

        clearFlashes('database:row');
        setLoading(true);
        (rowKey ? updateRow(uuid, databaseId, table, rowKey, values) : insertRow(uuid, databaseId, table, values))
            .then(() => {
                setLoading(false);
                onSaved();
            })
            .catch((error) => {
                setLoading(false);
                clearAndAddHttpError({ key: 'database:row', error });
            });
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            preventExternalClose={loading}
            title={rowKey ? t('databases.manager.edit_row', { table }) : t('databases.manager.add_row', { table })}
        >
            <SpinnerOverlay visible={loading} />
            <FlashMessageRender byKey={'database:row'} className={'mb-4'} />
            <div className={'max-h-[60vh] space-y-4 overflow-y-auto pr-1'}>
                {columns.map((column) => {
                    const field = fields[column.name] || { mode: 'value', value: '' };
                    const disabled = column.binary || field.mode !== 'value';
                    const id = `column-${column.name}`;

                    return (
                        <div key={column.name}>
                            <label htmlFor={id} className={'mb-1 flex items-baseline gap-2 text-sm text-gray-200'}>
                                <span className={'font-medium'}>{column.name}</span>
                                <span className={'font-mono text-xs text-gray-400'}>{column.type}</span>
                                {column.primary && (
                                    <span className={'text-xs text-yellow-400'}>{t('databases.manager.primary')}</span>
                                )}
                            </label>
                            {isLong(column.type) ? (
                                <Textarea
                                    id={id}
                                    className={'h-24 font-mono text-sm'}
                                    value={field.value}
                                    disabled={disabled}
                                    onChange={(e) => set(column.name, { value: e.currentTarget.value })}
                                />
                            ) : (
                                <Input
                                    id={id}
                                    className={'font-mono text-sm'}
                                    value={column.binary ? t('databases.manager.binary') : field.value}
                                    disabled={disabled}
                                    onChange={(e) => set(column.name, { value: e.currentTarget.value })}
                                />
                            )}
                            {!column.binary && (
                                <div className={'mt-1 flex gap-4 text-xs text-gray-400'}>
                                    {column.nullable && (
                                        <label className={'flex items-center gap-1'}>
                                            <input
                                                type={'checkbox'}
                                                checked={field.mode === 'null'}
                                                onChange={(e) =>
                                                    set(column.name, {
                                                        mode: e.currentTarget.checked ? 'null' : 'value',
                                                    })
                                                }
                                            />
                                            NULL
                                        </label>
                                    )}
                                    {!rowKey && (
                                        <label className={'flex items-center gap-1'}>
                                            <input
                                                type={'checkbox'}
                                                checked={field.mode === 'default'}
                                                onChange={(e) =>
                                                    set(column.name, {
                                                        mode: e.currentTarget.checked ? 'default' : 'value',
                                                    })
                                                }
                                            />
                                            {t('databases.manager.use_default')}
                                            {column.default !== null && (
                                                <span className={'font-mono'}>({column.default})</span>
                                            )}
                                        </label>
                                    )}
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={loading}>
                    {t('client/common:actions.cancel')}
                </Button.Text>
                <Button onClick={submit} disabled={loading}>
                    {t('client/common:actions.save')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};
