import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import Spinner from '@/components/elements/Spinner';
import { DatabaseColumn, DatabaseIndex, getStructure } from '@/api/server/databases/manager';

// Hydra: columns and indexes of a table.

export default ({ databaseId, table, flashKey }: { databaseId: string; table: string; flashKey: string }) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [data, setData] = useState<{ columns: DatabaseColumn[]; indexes: DatabaseIndex[] } | null>(null);

    useEffect(() => {
        setData(null);
        clearFlashes(flashKey);
        getStructure(uuid, databaseId, table)
            .then(setData)
            .catch((error) => clearAndAddHttpError({ key: flashKey, error }));
    }, [table]);

    if (!data) {
        return <Spinner size={'large'} centered />;
    }

    const head = 'px-3 py-2 whitespace-nowrap';
    const cell = 'px-3 py-1 border-t border-gray-700';

    return (
        <div className={'space-y-6'}>
            <div className={'overflow-x-auto rounded border border-gray-700'}>
                <table className={'w-full text-left text-sm'}>
                    <thead className={'bg-gray-700 text-xs uppercase text-gray-300'}>
                        <tr>
                            <th className={head}>{t('databases.manager.column')}</th>
                            <th className={head}>{t('databases.manager.type')}</th>
                            <th className={head}>NULL</th>
                            <th className={head}>{t('databases.manager.default')}</th>
                            <th className={head}>{t('databases.manager.extra')}</th>
                            <th className={head}>{t('databases.manager.comment')}</th>
                        </tr>
                    </thead>
                    <tbody className={'text-gray-200'}>
                        {data.columns.map((column) => (
                            <tr key={column.name}>
                                <td className={cell}>
                                    {column.primary && <span className={'mr-1 text-yellow-400'}>●</span>}
                                    {column.name}
                                </td>
                                <td className={`${cell} font-mono text-xs`}>{column.type}</td>
                                <td className={cell}>
                                    {column.nullable ? t('databases.manager.yes') : t('databases.manager.no')}
                                </td>
                                <td className={`${cell} font-mono text-xs`}>
                                    {column.default === null ? (
                                        <span className={'italic text-gray-500'}>NULL</span>
                                    ) : (
                                        column.default
                                    )}
                                </td>
                                <td className={`${cell} text-xs`}>{column.extra}</td>
                                <td className={`${cell} text-xs text-gray-400`}>{column.comment}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div>
                <h3 className={'mb-2 text-sm font-medium text-gray-200'}>{t('databases.manager.indexes')}</h3>
                {data.indexes.length === 0 ? (
                    <p className={'text-sm text-gray-400'}>{t('databases.manager.no_indexes')}</p>
                ) : (
                    <div className={'overflow-x-auto rounded border border-gray-700'}>
                        <table className={'w-full text-left text-sm'}>
                            <thead className={'bg-gray-700 text-xs uppercase text-gray-300'}>
                                <tr>
                                    <th className={head}>{t('databases.manager.index')}</th>
                                    <th className={head}>{t('databases.manager.columns')}</th>
                                    <th className={head}>{t('databases.manager.unique')}</th>
                                    <th className={head}>{t('databases.manager.type')}</th>
                                </tr>
                            </thead>
                            <tbody className={'text-gray-200'}>
                                {data.indexes.map((index) => (
                                    <tr key={index.name}>
                                        <td className={cell}>{index.name}</td>
                                        <td className={`${cell} font-mono text-xs`}>{index.columns.join(', ')}</td>
                                        <td className={cell}>
                                            {index.unique ? t('databases.manager.yes') : t('databases.manager.no')}
                                        </td>
                                        <td className={`${cell} text-xs`}>{index.type}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </div>
    );
};
