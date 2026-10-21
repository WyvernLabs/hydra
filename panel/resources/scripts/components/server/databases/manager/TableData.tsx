import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Spinner from '@/components/elements/Spinner';
import Cell from '@/components/server/databases/manager/Cell';
import RowEditor from '@/components/server/databases/manager/RowEditor';
import { CellValue, DatabaseRow, DatabaseRows, deleteRow, getRows } from '@/api/server/databases/manager';

// Hydra: the rows of a table, page by page, with sorting and editing.

interface Props {
    databaseId: string;
    table: string;
    view: boolean;
    flashKey: string;
    onChanged: () => void;
}

export default ({ databaseId, table, view, flashKey, onChanged }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [canEdit] = usePermissions('database.edit');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [data, setData] = useState<DatabaseRows | null>(null);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [sort, setSort] = useState<{ column: string; direction: 'asc' | 'desc' } | null>(null);
    const [editing, setEditing] = useState<{ key: Record<string, CellValue> | null } | null>(null);
    const [deleting, setDeleting] = useState<Record<string, CellValue> | null>(null);

    const load = () => {
        clearFlashes(flashKey);
        setLoading(true);
        getRows(uuid, databaseId, { table, page, sort: sort?.column, direction: sort?.direction })
            .then(setData)
            .catch((error) => clearAndAddHttpError({ key: flashKey, error }))
            .then(() => setLoading(false));
    };

    useEffect(() => {
        setPage(1);
        setSort(null);
        setData(null);
    }, [table]);

    useEffect(() => {
        load();
    }, [table, page, sort]);

    const editable = canEdit && !view && !!data && data.primary.length > 0;
    const keyOf = (row: DatabaseRow) =>
        Object.fromEntries((data?.primary || []).map((name) => [name, row.values[name]])) as Record<string, CellValue>;

    const toggleSort = (column: string) =>
        setSort((current) =>
            current?.column !== column
                ? { column, direction: 'asc' }
                : current.direction === 'asc'
                ? { column, direction: 'desc' }
                : null
        );

    const confirmDelete = () => {
        if (!deleting) return;
        const key = deleting;
        setDeleting(null);
        deleteRow(uuid, databaseId, table, key)
            .then(() => {
                load();
                onChanged();
            })
            .catch((error) => clearAndAddHttpError({ key: flashKey, error }));
    };

    if (!data) {
        return <Spinner size={'large'} centered />;
    }

    const { pagination } = data;

    return (
        <div>
            {data && (
                <RowEditor
                    databaseId={databaseId}
                    table={table}
                    columns={data.columns}
                    rowKey={editing?.key ?? null}
                    open={!!editing}
                    onClose={() => setEditing(null)}
                    onSaved={() => {
                        setEditing(null);
                        load();
                        onChanged();
                    }}
                />
            )}
            <Dialog.Confirm
                open={!!deleting}
                onClose={() => setDeleting(null)}
                title={t('databases.manager.delete_row_title')}
                confirm={t('client/common:actions.delete')}
                onConfirmed={confirmDelete}
            >
                {t('databases.manager.delete_row_text', { table })}
            </Dialog.Confirm>

            <div className={'mb-3 flex flex-wrap items-center gap-2'}>
                <p className={'flex-1 text-sm text-gray-400'}>
                    {t('databases.manager.rows_total', { count: pagination.total })}
                    {!view && canEdit && data.primary.length === 0 && (
                        <span className={'ml-2 text-yellow-400'}>{t('databases.manager.no_primary')}</span>
                    )}
                </p>
                <Button.Text className={'p-2'} onClick={load} disabled={loading} title={t('databases.manager.reload')}>
                    <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                </Button.Text>
                {canEdit && !view && (
                    <Button size={Button.Sizes.Small} onClick={() => setEditing({ key: null })}>
                        <Plus className={'mr-1 inline h-4 w-4'} />
                        {t('databases.manager.add_row_button')}
                    </Button>
                )}
            </div>

            <div className={'overflow-x-auto rounded border border-gray-700'}>
                <table className={'w-full text-left text-sm'}>
                    <thead className={'bg-gray-700 text-xs uppercase text-gray-300'}>
                        <tr>
                            {editable && <th className={'w-16 px-3 py-2'} />}
                            {data.columns.map((column) => (
                                <th key={column.name} className={'whitespace-nowrap px-3 py-2'}>
                                    <button
                                        type={'button'}
                                        className={'inline-flex items-center gap-1 hover:text-gray-50'}
                                        onClick={() => toggleSort(column.name)}
                                        title={column.type}
                                    >
                                        {column.primary && <span className={'text-yellow-400'}>●</span>}
                                        {column.name}
                                        {sort?.column === column.name &&
                                            (sort.direction === 'asc' ? (
                                                <ArrowUp className={'h-3 w-3'} />
                                            ) : (
                                                <ArrowDown className={'h-3 w-3'} />
                                            ))}
                                    </button>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {data.rows.length === 0 && (
                            <tr>
                                <td colSpan={data.columns.length + 1} className={'px-3 py-6 text-center text-gray-400'}>
                                    {t('databases.manager.no_rows')}
                                </td>
                            </tr>
                        )}
                        {data.rows.map((row, index) => (
                            <tr key={index} className={'border-t border-gray-700 hover:bg-gray-700/40'}>
                                {editable && (
                                    <td className={'whitespace-nowrap px-2 py-1'}>
                                        <button
                                            type={'button'}
                                            className={'p-1 text-gray-400 hover:text-gray-50'}
                                            title={t('client/common:actions.edit')}
                                            onClick={() => setEditing({ key: keyOf(row) })}
                                        >
                                            <Pencil className={'h-4 w-4'} />
                                        </button>
                                        <button
                                            type={'button'}
                                            className={'p-1 text-gray-400 hover:text-red-400'}
                                            title={t('client/common:actions.delete')}
                                            onClick={() => setDeleting(keyOf(row))}
                                        >
                                            <Trash2 className={'h-4 w-4'} />
                                        </button>
                                    </td>
                                )}
                                {data.columns.map((column) => (
                                    <td
                                        key={column.name}
                                        className={'max-w-xs px-3 py-1 font-mono text-xs text-gray-200'}
                                    >
                                        <Cell
                                            value={row.values[column.name]}
                                            truncated={row.truncated.includes(column.name)}
                                        />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {pagination.total_pages > 1 && (
                <div className={'mt-3 flex items-center justify-end gap-2 text-sm text-gray-300'}>
                    <Button.Text className={'p-2'} disabled={page <= 1 || loading} onClick={() => setPage(page - 1)}>
                        <ChevronLeft className={'h-4 w-4'} />
                    </Button.Text>
                    <span>
                        {t('databases.manager.page', { page: pagination.current_page, pages: pagination.total_pages })}
                    </span>
                    <Button.Text
                        className={'p-2'}
                        disabled={page >= pagination.total_pages || loading}
                        onClick={() => setPage(page + 1)}
                    >
                        <ChevronRight className={'h-4 w-4'} />
                    </Button.Text>
                </div>
            )}
        </div>
    );
};
