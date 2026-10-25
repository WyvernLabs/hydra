import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams, useRouteMatch } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Code, Columns, Eye, List, Search, Table as TableIcon } from 'lucide-react';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { bytesToString } from '@/lib/formatters';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import getServerDatabases from '@/api/server/databases/getServerDatabases';
import { DatabaseTable, getTables } from '@/api/server/databases/manager';
import DatabaseTools from '@/components/server/databases/DatabaseTools';
import TableData from '@/components/server/databases/manager/TableData';
import TableStructure from '@/components/server/databases/manager/TableStructure';
import SqlConsole from '@/components/server/databases/manager/SqlConsole';

// Hydra: tables and data of a server database, right in the client area.

type Tab = 'data' | 'structure' | 'sql';

const FLASH = 'database:manager';

export default () => {
    const { t } = useTranslation('client/server');
    const { database: id } = useParams<{ database: string }>();
    const match = useRouteMatch();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const databases = ServerContext.useStoreState((state) => state.databases.data);
    const setDatabases = ServerContext.useStoreActions((actions) => actions.databases.setDatabases);
    const [canEdit] = usePermissions('database.edit');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [tables, setTables] = useState<DatabaseTable[] | null>(null);
    const [selected, setSelected] = useState<string | null>(null);
    const [tab, setTab] = useState<Tab>('data');
    const [filter, setFilter] = useState('');

    const database = databases.find((item) => item.id === id);

    const loadTables = () =>
        getTables(uuid, id)
            .then(({ tables }) => {
                setTables(tables);
                setSelected((current) =>
                    current && tables.some((table) => table.name === current) ? current : tables[0]?.name ?? null
                );
            })
            .catch((error) => {
                setTables([]);
                clearAndAddHttpError({ key: FLASH, error });
            });

    useEffect(() => {
        clearFlashes(FLASH);
        if (!database) {
            getServerDatabases(uuid)
                .then(setDatabases)
                .catch((error) => clearAndAddHttpError({ key: FLASH, error }));
        }
        loadTables();
    }, [id]);

    const visible = useMemo(
        () => (tables || []).filter((table) => table.name.toLowerCase().includes(filter.toLowerCase())),
        [tables, filter]
    );
    const current = tables?.find((table) => table.name === selected) || null;
    const back = match.url.replace(/\/[^/]+\/?$/, '');

    const tabs: { key: Tab; label: string; icon: typeof List }[] = [
        { key: 'data', label: t('databases.manager.tab_data'), icon: List },
        { key: 'structure', label: t('databases.manager.tab_structure'), icon: Columns },
        ...(canEdit ? [{ key: 'sql' as Tab, label: t('databases.manager.tab_sql'), icon: Code }] : []),
    ];

    return (
        <ServerContentBlock title={database ? database.name : t('databases.title')} className={'!max-w-none'}>
            <div className={'mb-4 flex flex-wrap items-center gap-3'}>
                <Link to={back} className={'flex items-center gap-1 text-sm text-gray-300 hover:text-gray-50'}>
                    <ArrowLeft className={'h-4 w-4'} />
                    {t('databases.title')}
                </Link>
                <h2 className={'flex-1 truncate text-lg text-gray-50'}>{database?.name}</h2>
                {database && (
                    <div className={'flex items-center'}>
                        <DatabaseTools database={database} flashKey={FLASH} onImported={loadTables} />
                    </div>
                )}
            </div>
            <FlashMessageRender byKey={FLASH} className={'mb-4'} />
            {tables === null ? (
                <Spinner size={'large'} centered />
            ) : (
                // One box like the file editor: tables with search on the left, tabs and content on the right.
                <div
                    className={
                        'flex flex-col overflow-hidden rounded-lg border border-gray-700 bg-gray-800/90 backdrop-blur md:h-[calc(var(--hydra-vh)-15rem)] md:min-h-[28rem] md:flex-row'
                    }
                >
                    <aside
                        className={
                            'flex max-h-72 shrink-0 flex-col border-b border-gray-700 md:max-h-none md:w-64 md:border-b-0 md:border-r'
                        }
                    >
                        <div className={'border-b border-gray-700 p-2'}>
                            <p className={'mb-1.5 flex justify-between px-1 text-xs text-gray-400'}>
                                <span>{t('databases.manager.tables')}</span>
                                <span>{tables.length}</span>
                            </p>
                            <div className={'relative'}>
                                <Search className={'absolute left-2 top-2 h-3.5 w-3.5 text-gray-400'} />
                                <input
                                    type={'search'}
                                    placeholder={t('databases.manager.filter')}
                                    value={filter}
                                    onChange={(e) => setFilter(e.currentTarget.value)}
                                    className={
                                        'w-full rounded border border-gray-600 bg-gray-700 py-1 pl-7 pr-2 text-xs text-gray-100 outline-none focus:border-primary-400'
                                    }
                                />
                            </div>
                        </div>
                        <ul className={'min-h-0 flex-1 overflow-y-auto py-1'}>
                            {visible.length === 0 && (
                                <li className={'px-3 py-4 text-center text-sm text-gray-400'}>
                                    {t('databases.manager.no_tables')}
                                </li>
                            )}
                            {visible.map((table) => (
                                <li key={table.name}>
                                    <button
                                        type={'button'}
                                        onClick={() => {
                                            setSelected(table.name);
                                            if (tab === 'sql') setTab('data');
                                        }}
                                        className={`flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm ${
                                            table.name === selected
                                                ? 'bg-gray-700 text-gray-50'
                                                : 'text-gray-300 hover:bg-gray-700/50'
                                        }`}
                                    >
                                        {table.view ? (
                                            <Eye className={'h-3.5 w-3.5 shrink-0 text-gray-400'} />
                                        ) : (
                                            <TableIcon className={'h-3.5 w-3.5 shrink-0 text-gray-400'} />
                                        )}
                                        <span
                                            className={'min-w-0 flex-1 truncate'}
                                            title={
                                                table.view
                                                    ? `${table.name} (${t('databases.manager.view')})`
                                                    : table.name
                                            }
                                        >
                                            {table.name}
                                        </span>
                                        <span className={'shrink-0 text-xs text-gray-500'}>
                                            {table.rows ?? '–'} · {bytesToString(table.size)}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </aside>
                    <section className={'flex min-h-0 min-w-0 flex-1 flex-col'}>
                        <div className={'flex shrink-0 items-center gap-1 border-b border-gray-700 px-2'}>
                            {tabs.map(({ key, label, icon: Icon }) => (
                                <button
                                    key={key}
                                    type={'button'}
                                    onClick={() => setTab(key)}
                                    className={`-mb-px flex items-center gap-1 border-b-2 px-3 py-2 text-sm ${
                                        tab === key
                                            ? 'border-primary-400 text-gray-50'
                                            : 'border-transparent text-gray-400 hover:text-gray-200'
                                    }`}
                                >
                                    <Icon className={'h-4 w-4'} />
                                    {label}
                                </button>
                            ))}
                            {current && tab !== 'sql' && (
                                <span className={'ml-auto truncate pl-2 font-mono text-xs text-gray-400'}>
                                    {current.name}
                                </span>
                            )}
                        </div>
                        <div className={'min-h-0 flex-1 overflow-auto p-4'}>
                            {tab === 'sql' ? (
                                <SqlConsole
                                    databaseId={id}
                                    initial={current ? `SELECT * FROM \`${current.name}\` LIMIT 100;` : ''}
                                    flashKey={FLASH}
                                    onChanged={loadTables}
                                />
                            ) : !current ? (
                                <p className={'py-10 text-center text-sm text-gray-400'}>
                                    {t('databases.manager.no_tables_yet')}
                                </p>
                            ) : tab === 'structure' ? (
                                <TableStructure databaseId={id} table={current.name} flashKey={FLASH} />
                            ) : (
                                <TableData
                                    databaseId={id}
                                    table={current.name}
                                    view={current.view}
                                    flashKey={FLASH}
                                    onChanged={loadTables}
                                />
                            )}
                        </div>
                    </section>
                </div>
            )}
        </ServerContentBlock>
    );
};
