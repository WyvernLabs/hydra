import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams, useRouteMatch } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, Code, Columns, List } from 'lucide-react';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { bytesToString } from '@/lib/formatters';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import Input from '@/components/elements/Input';
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
        <ServerContentBlock title={database ? database.name : t('databases.title')}>
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
                <div className={'flex flex-col gap-4 lg:flex-row'}>
                    <aside className={'w-full shrink-0 lg:w-60'}>
                        <Input
                            type={'search'}
                            placeholder={t('databases.manager.filter')}
                            value={filter}
                            onChange={(e) => setFilter(e.currentTarget.value)}
                        />
                        <ul className={'mt-2 max-h-[70vh] overflow-y-auto rounded border border-gray-700'}>
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
                                        className={`flex w-full items-baseline justify-between gap-2 px-3 py-2 text-left text-sm ${
                                            table.name === selected
                                                ? 'bg-gray-700 text-gray-50'
                                                : 'text-gray-300 hover:bg-gray-700/50'
                                        }`}
                                    >
                                        <span className={'truncate'}>
                                            {table.name}
                                            {table.view && (
                                                <span className={'ml-1 text-xs text-gray-400'}>
                                                    ({t('databases.manager.view')})
                                                </span>
                                            )}
                                        </span>
                                        <span className={'shrink-0 text-xs text-gray-400'}>
                                            {table.rows ?? '–'} · {bytesToString(table.size)}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </aside>
                    <section className={'min-w-0 flex-1'}>
                        <div className={'mb-4 flex gap-1 border-b border-gray-700'}>
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
                        </div>
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
                    </section>
                </div>
            )}
        </ServerContentBlock>
    );
};
