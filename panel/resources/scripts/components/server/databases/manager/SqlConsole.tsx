import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Play } from 'lucide-react';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { Button } from '@/components/elements/button/index';
import { Textarea } from '@/components/elements/Input';
import Cell from '@/components/server/databases/manager/Cell';
import { QueryResult, runQuery } from '@/api/server/databases/manager';

// Hydra: runs one SQL statement against the database and shows its result.

interface Props {
    databaseId: string;
    initial: string;
    flashKey: string;
    onChanged: () => void;
}

export default ({ databaseId, initial, flashKey, onChanged }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [sql, setSql] = useState(initial);
    const [running, setRunning] = useState(false);
    const [result, setResult] = useState<QueryResult | null>(null);

    const run = () => {
        if (!sql.trim() || running) return;
        clearFlashes(flashKey);
        setRunning(true);
        runQuery(uuid, databaseId, sql)
            .then((data) => {
                setResult(data);
                // The statement may have created or dropped tables.
                if (data.affected !== null) onChanged();
            })
            .catch((error) => {
                setResult(null);
                clearAndAddHttpError({ key: flashKey, error });
            })
            .then(() => setRunning(false));
    };

    return (
        <div>
            <Textarea
                className={'h-40 font-mono text-sm'}
                value={sql}
                spellCheck={false}
                onChange={(e) => setSql(e.currentTarget.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        run();
                    }
                }}
            />
            <div className={'mt-2 flex items-center justify-between gap-2'}>
                <p className={'text-xs text-gray-400'}>{t('databases.manager.sql_help')}</p>
                <Button size={Button.Sizes.Small} onClick={run} disabled={running || !sql.trim()}>
                    <Play className={'mr-1 inline h-4 w-4'} />
                    {t('databases.manager.run')}
                </Button>
            </div>

            {result && (
                <div className={'mt-4'}>
                    <p className={'mb-2 text-sm text-gray-400'}>
                        {result.affected !== null
                            ? t('databases.manager.affected', { count: result.affected, time: result.time })
                            : t('databases.manager.returned', { count: result.rows.length, time: result.time })}
                        {result.truncated && (
                            <span className={'ml-2 text-yellow-400'}>
                                {t('databases.manager.truncated', { limit: result.rows.length })}
                            </span>
                        )}
                    </p>
                    {result.columns.length > 0 && (
                        <div className={'max-h-[60vh] overflow-auto rounded border border-gray-700'}>
                            <table className={'w-full text-left text-sm'}>
                                <thead className={'sticky top-0 bg-gray-700 text-xs uppercase text-gray-300'}>
                                    <tr>
                                        {result.columns.map((column, index) => (
                                            <th key={index} className={'whitespace-nowrap px-3 py-2'}>
                                                {column.name}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {result.rows.map((row, index) => (
                                        <tr key={index} className={'border-t border-gray-700'}>
                                            {row.map((value, column) => (
                                                <td
                                                    key={column}
                                                    className={'max-w-xs px-3 py-1 font-mono text-xs text-gray-200'}
                                                >
                                                    <Cell value={value} />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
};
