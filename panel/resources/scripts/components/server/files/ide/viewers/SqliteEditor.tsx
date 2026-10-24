import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { ChevronLeft, ChevronRight, Eye, Loader2, Play, Plus, Save, Table2, Trash2 } from 'lucide-react';
import type { Database, SqlValue } from 'sql.js';
import getFileBytes from '@/api/server/files/getFileBytes';
import saveFileContents from '@/api/server/files/saveFileContents';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import { useIde } from '@/components/server/files/ide/context';
import { useSaveShortcut, ViewerMessage, ViewerProps } from '@/components/server/files/ide/viewers/common';
// @ts-expect-error webpack turns the file into its URL
import wasmUrl from 'sql.js/dist/sql-wasm.wasm';

// Hydra: SQLite databases (.db, .sqlite) in the browser with sql.js. The file
// is loaded completely, changed in memory and written back when saved.

const PAGE_SIZE = 100;
const SQLITE_HEADER = 'SQLite format 3\u0000';

interface TableInfo {
    name: string;
    type: 'table' | 'view';
    sql: string;
    rows: number;
}

interface Column {
    name: string;
    type: string;
    notnull: boolean;
    pk: boolean;
    dflt: SqlValue;
}

interface QueryResult {
    columns: string[];
    values: SqlValue[][];
}

let sqlPromise: Promise<import('sql.js').SqlJsStatic> | null = null;

const loadSql = () => {
    if (!sqlPromise) {
        sqlPromise = import('sql.js').then(({ default: init }) => init({ locateFile: () => wasmUrl }));
    }

    return sqlPromise;
};

const quote = (name: string) => `"${name.replace(/"/g, '""')}"`;

const isSqlite = (bytes: Uint8Array) =>
    bytes.length === 0 || new TextDecoder().decode(bytes.subarray(0, 16)) === SQLITE_HEADER;

const display = (value: SqlValue): string => {
    if (value === null) return 'NULL';
    if (value instanceof Uint8Array) return `BLOB (${value.length} B)`;

    return String(value);
};

const Cell = ({
    value,
    editable,
    onSave,
}: {
    value: SqlValue;
    editable: boolean;
    onSave: (value: SqlValue) => void;
}) => {
    const { t } = useTranslation('client/files');
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState('');
    const blob = value instanceof Uint8Array;

    if (editing) {
        const commit = () => {
            setEditing(false);
            if (text !== display(value)) onSave(text);
        };

        return (
            <td className={'border-b border-r border-gray-700 p-0'}>
                <div className={'flex'}>
                    <input
                        autoFocus
                        value={text}
                        onChange={(event) => setText(event.currentTarget.value)}
                        onBlur={commit}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') commit();
                            if (event.key === 'Escape') setEditing(false);
                        }}
                        className={
                            'min-w-[8rem] flex-1 border-0 bg-gray-900 px-2 py-1 font-mono text-xs text-gray-100 outline-none ring-1 ring-inset ring-primary-400'
                        }
                    />
                    <button
                        type={'button'}
                        onMouseDown={(event) => {
                            event.preventDefault();
                            setEditing(false);
                            onSave(null);
                        }}
                        title={t('sqlite.set_null')}
                        className={'bg-gray-700 px-1.5 text-2xs text-gray-300 hover:bg-gray-600'}
                    >
                        NULL
                    </button>
                </div>
            </td>
        );
    }

    return (
        <td
            onDoubleClick={() => {
                if (!editable || blob) return;
                setText(value === null ? '' : String(value));
                setEditing(true);
            }}
            title={display(value)}
            className={classNames(
                'max-w-xs truncate border-b border-r border-gray-700 px-2 py-1 font-mono text-xs',
                value === null || blob
                    ? 'italic text-gray-500'
                    : typeof value === 'number'
                    ? 'text-right text-amber-200'
                    : 'text-gray-200',
                editable && !blob && 'cursor-text hover:bg-gray-700/40'
            )}
        >
            {display(value)}
        </td>
    );
};

const ResultTable = ({ result }: { result: QueryResult }) => (
    <table className={'min-w-full border-collapse'}>
        <thead className={'sticky top-0 bg-gray-800'}>
            <tr>
                {result.columns.map((column, i) => (
                    <th
                        key={i}
                        className={
                            'border-b border-r border-gray-600 px-2 py-1 text-left font-mono text-xs font-medium text-gray-300'
                        }
                    >
                        {column}
                    </th>
                ))}
            </tr>
        </thead>
        <tbody>
            {result.values.map((row, i) => (
                <tr key={i}>
                    {row.map((value, j) => (
                        <Cell key={j} value={value} editable={false} onSave={() => undefined} />
                    ))}
                </tr>
            ))}
        </tbody>
    </table>
);

export default ({ tab, active }: ViewerProps) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const running = ServerContext.useStoreState(
        (state) => state.status.value === 'running' || state.status.value === 'starting'
    );
    const db = useRef<Database | null>(null);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notSqlite, setNotSqlite] = useState(false);
    const [tables, setTables] = useState<TableInfo[]>([]);
    const [table, setTable] = useState<string | null>(null);
    const [view, setView] = useState<'data' | 'structure' | 'sql'>('data');
    const [page, setPage] = useState(0);
    const [version, setVersion] = useState(0);
    const [actionError, setActionError] = useState<string | null>(null);
    const [sql, setSql] = useState('');
    const [results, setResults] = useState<QueryResult[] | null>(null);
    const [sqlInfo, setSqlInfo] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const readOnly = !ide.can.update || !ide.canWriteIn(tab.path);

    const refreshTables = () => {
        if (!db.current) return;
        const list: TableInfo[] = [];
        const [result] = db.current.exec(
            "SELECT name, type, COALESCE(sql, '') FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%' ORDER BY type, name"
        );
        (result?.values || []).forEach(([name, type, sql]) => {
            let rows = 0;
            try {
                rows = Number(db.current!.exec(`SELECT COUNT(*) FROM ${quote(String(name))}`)[0]?.values[0][0] || 0);
            } catch {
                rows = 0;
            }
            list.push({ name: String(name), type: type as TableInfo['type'], sql: String(sql), rows });
        });
        setTables(list);
        setTable((current) => (current && list.some((t) => t.name === current) ? current : list[0]?.name ?? null));
    };

    const load = useCallback(() => {
        setError(null);
        setReady(false);
        setNotSqlite(false);
        Promise.all([getFileBytes(ide.uuid, tab.path), loadSql()])
            .then(([buffer, SQL]) => {
                const bytes = new Uint8Array(buffer);
                if (!isSqlite(bytes)) {
                    setNotSqlite(true);
                    return;
                }
                db.current?.close();
                db.current = new SQL.Database(bytes);
                refreshTables();
                setReady(true);
            })
            .catch((error) => setError(error?.response ? httpErrorToHuman(error) : String(error?.message || error)));
    }, [ide.uuid, tab.path]);

    useEffect(() => {
        load();
        return () => {
            db.current?.close();
            db.current = null;
        };
    }, [ide.uuid]);

    const changed = () => {
        ide.setDirty(tab.id, true);
        refreshTables();
        setVersion((v) => v + 1);
    };

    const run = (statement: string, params: SqlValue[] = []) => {
        setActionError(null);
        try {
            db.current!.run(statement, params);
            changed();
        } catch (error) {
            setActionError(String((error as Error).message || error));
        }
    };

    const info = tables.find((entry) => entry.name === table) || null;

    const columns = useMemo<Column[]>(() => {
        if (!db.current || !table) return [];
        const [result] = db.current.exec(`PRAGMA table_info(${quote(table)})`);

        return (result?.values || []).map((row) => ({
            name: String(row[1]),
            type: String(row[2] || ''),
            notnull: row[3] === 1,
            dflt: row[4],
            pk: Number(row[5]) > 0,
        }));
    }, [table, version, ready]);

    // Rows of the page; tables are edited through their rowid.
    const data = useMemo<{ rowids: SqlValue[] | null; result: QueryResult } | null>(() => {
        if (!db.current || !table) return null;
        const offset = page * PAGE_SIZE;
        try {
            if (info?.type === 'table') {
                const [result] = db.current.exec(
                    `SELECT rowid, * FROM ${quote(table)} LIMIT ${PAGE_SIZE} OFFSET ${offset}`
                );
                if (!result) return { rowids: [], result: { columns: columns.map((c) => c.name), values: [] } };
                return {
                    rowids: result.values.map((row) => row[0]),
                    result: { columns: result.columns.slice(1), values: result.values.map((row) => row.slice(1)) },
                };
            }
        } catch {
            // Tables without rowid can only be shown.
        }
        const [result] = db.current.exec(`SELECT * FROM ${quote(table)} LIMIT ${PAGE_SIZE} OFFSET ${offset}`);

        return { rowids: null, result: result || { columns: columns.map((c) => c.name), values: [] } };
    }, [table, page, version, ready, info?.type]);

    const editable = !readOnly && !!data?.rowids;
    const pages = Math.max(1, Math.ceil((info?.rows || 0) / PAGE_SIZE));

    const updateCell = (rowid: SqlValue, column: string, value: SqlValue) => {
        const type = columns.find((c) => c.name === column)?.type.toUpperCase() || '';
        let typed: SqlValue = value;
        if (
            typeof value === 'string' &&
            /INT|REAL|FLOA|DOUB|NUM|DEC/.test(type) &&
            value.trim() !== '' &&
            !Number.isNaN(Number(value))
        ) {
            typed = Number(value);
        }
        run(`UPDATE ${quote(table!)} SET ${quote(column)} = ? WHERE rowid = ?`, [typed, rowid]);
    };

    const runSql = () => {
        if (!db.current || !sql.trim()) return;
        setActionError(null);
        setSqlInfo(null);
        try {
            const before = db.current.exec('SELECT total_changes()')[0].values[0][0];
            const output = db.current.exec(sql);
            const after = db.current.exec('SELECT total_changes()')[0].values[0][0];
            setResults(output.map((r) => ({ columns: r.columns, values: r.values })));
            const modified = Number(after) - Number(before);
            const schema = /^\s*(CREATE|DROP|ALTER)\b/im.test(sql);
            if (modified > 0 || schema) {
                changed();
            }
            setSqlInfo(output.length === 0 ? t('sqlite.rows_changed', { count: modified }) : null);
        } catch (error) {
            setResults(null);
            setActionError(String((error as Error).message || error));
        }
    };

    const save = () => {
        if (!db.current || readOnly) return;
        setSaving(true);
        setActionError(null);
        const bytes = db.current.export();
        saveFileContents(ide.uuid, tab.path, new Blob([bytes]))
            .then(() => ide.setDirty(tab.id, false))
            .catch((error) => setActionError(httpErrorToHuman(error)))
            .then(() => setSaving(false));
    };

    useSaveShortcut(active, save);

    if (error) {
        return <ViewerMessage tab={tab} title={t('sqlite.failed')} message={error} onRetry={load} />;
    }
    if (notSqlite) {
        return <ViewerMessage tab={tab} title={t('sqlite.not_sqlite')} message={t('sqlite.not_sqlite_help')} />;
    }
    if (!ready) {
        return (
            <div className={'flex h-full items-center justify-center'}>
                <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
            </div>
        );
    }

    return (
        <div className={'flex h-full flex-col'}>
            {running && !readOnly && (
                <p className={'border-b border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-200'}>
                    {t('sqlite.running')}
                </p>
            )}
            <div className={'flex min-h-0 flex-1'}>
                <div className={'w-48 shrink-0 overflow-y-auto border-r border-gray-700 p-1'}>
                    <p className={'px-2 py-1 text-2xs uppercase tracking-wide text-gray-500'}>{t('sqlite.tables')}</p>
                    {tables.length === 0 && <p className={'px-2 text-xs text-gray-500'}>{t('sqlite.no_tables')}</p>}
                    {tables.map((entry) => (
                        <button
                            key={entry.name}
                            type={'button'}
                            onClick={() => {
                                setTable(entry.name);
                                setPage(0);
                                if (view === 'sql') setView('data');
                            }}
                            className={classNames(
                                'flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs',
                                entry.name === table && view !== 'sql'
                                    ? 'bg-primary-500/20 text-gray-50'
                                    : 'text-gray-300 hover:bg-gray-700/60'
                            )}
                        >
                            {entry.type === 'view' ? (
                                <Eye className={'h-3.5 w-3.5 shrink-0'} />
                            ) : (
                                <Table2 className={'h-3.5 w-3.5 shrink-0'} />
                            )}
                            <span className={'min-w-0 flex-1 truncate font-mono'}>{entry.name}</span>
                            <span className={'text-2xs text-gray-500'}>{entry.rows}</span>
                        </button>
                    ))}
                </div>
                <div className={'flex min-w-0 flex-1 flex-col'}>
                    <div className={'flex items-center gap-1 border-b border-gray-700 px-2 py-1'}>
                        {(['data', 'structure', 'sql'] as const).map((key) => (
                            <button
                                key={key}
                                type={'button'}
                                onClick={() => setView(key)}
                                className={classNames(
                                    'rounded px-2 py-0.5 text-xs',
                                    view === key ? 'bg-gray-700 text-gray-50' : 'text-gray-400 hover:text-gray-100'
                                )}
                            >
                                {t(`sqlite.views.${key}`)}
                            </button>
                        ))}
                        {view === 'data' && info && (
                            <div className={'ml-auto flex items-center gap-2 text-2xs text-gray-400'}>
                                {editable && (
                                    <button
                                        type={'button'}
                                        onClick={() => run(`INSERT INTO ${quote(info.name)} DEFAULT VALUES`)}
                                        className={
                                            'flex items-center gap-1 rounded px-1.5 py-0.5 text-xs text-gray-300 hover:bg-gray-700'
                                        }
                                    >
                                        <Plus className={'h-3.5 w-3.5'} />
                                        {t('sqlite.add_row')}
                                    </button>
                                )}
                                <button
                                    type={'button'}
                                    disabled={page === 0}
                                    onClick={() => setPage((p) => p - 1)}
                                    className={'disabled:opacity-30'}
                                >
                                    <ChevronLeft className={'h-4 w-4'} />
                                </button>
                                {t('sqlite.page', { page: page + 1, pages, rows: info.rows })}
                                <button
                                    type={'button'}
                                    disabled={page + 1 >= pages}
                                    onClick={() => setPage((p) => p + 1)}
                                    className={'disabled:opacity-30'}
                                >
                                    <ChevronRight className={'h-4 w-4'} />
                                </button>
                            </div>
                        )}
                    </div>
                    {actionError && (
                        <p
                            className={
                                'border-b border-red-500/30 bg-red-500/10 px-3 py-1.5 font-mono text-xs text-red-300'
                            }
                        >
                            {actionError}
                        </p>
                    )}
                    <div className={'min-h-0 flex-1 overflow-auto'}>
                        {view === 'data' && data && (
                            <table className={'min-w-full border-collapse'}>
                                <thead className={'sticky top-0 z-10 bg-gray-800'}>
                                    <tr>
                                        {data.result.columns.map((column) => {
                                            const meta = columns.find((c) => c.name === column);
                                            return (
                                                <th
                                                    key={column}
                                                    className={
                                                        'border-b border-r border-gray-600 px-2 py-1 text-left font-mono text-xs font-medium text-gray-200'
                                                    }
                                                >
                                                    {column}
                                                    {meta && (
                                                        <span
                                                            className={
                                                                'ml-1 font-sans text-2xs font-normal text-gray-500'
                                                            }
                                                        >
                                                            {meta.type}
                                                            {meta.pk && ' · PK'}
                                                        </span>
                                                    )}
                                                </th>
                                            );
                                        })}
                                        {editable && <th className={'w-8 border-b border-gray-600'} />}
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.result.values.map((row, i) => (
                                        <tr key={String(data.rowids?.[i] ?? i)} className={'group'}>
                                            {row.map((value, j) => (
                                                <Cell
                                                    key={j}
                                                    value={value}
                                                    editable={editable}
                                                    onSave={(next) =>
                                                        updateCell(data.rowids![i], data.result.columns[j], next)
                                                    }
                                                />
                                            ))}
                                            {editable && (
                                                <td className={'border-b border-gray-700 px-1'}>
                                                    <button
                                                        type={'button'}
                                                        title={t('sqlite.delete_row')}
                                                        onClick={() =>
                                                            run(`DELETE FROM ${quote(info!.name)} WHERE rowid = ?`, [
                                                                data.rowids![i],
                                                            ])
                                                        }
                                                        className={
                                                            'rounded p-0.5 text-gray-500 opacity-0 hover:text-red-400 group-hover:opacity-100'
                                                        }
                                                    >
                                                        <Trash2 className={'h-3.5 w-3.5'} />
                                                    </button>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                        {view === 'data' && data && data.result.values.length === 0 && (
                            <p className={'p-6 text-center text-xs text-gray-500'}>{t('sqlite.empty_table')}</p>
                        )}
                        {view === 'structure' && info && (
                            <div className={'space-y-4 p-3'}>
                                <table className={'min-w-full border-collapse'}>
                                    <thead>
                                        <tr>
                                            {['name', 'type', 'notnull', 'default', 'pk'].map((key) => (
                                                <th
                                                    key={key}
                                                    className={
                                                        'border-b border-gray-600 px-2 py-1 text-left text-xs font-medium text-gray-300'
                                                    }
                                                >
                                                    {t(`sqlite.columns.${key}`)}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {columns.map((column) => (
                                            <tr key={column.name} className={'text-xs text-gray-200'}>
                                                <td className={'border-b border-gray-700 px-2 py-1 font-mono'}>
                                                    {column.name}
                                                </td>
                                                <td className={'border-b border-gray-700 px-2 py-1 font-mono'}>
                                                    {column.type}
                                                </td>
                                                <td className={'border-b border-gray-700 px-2 py-1'}>
                                                    {column.notnull ? '✓' : ''}
                                                </td>
                                                <td className={'border-b border-gray-700 px-2 py-1 font-mono'}>
                                                    {column.dflt === null ? '' : display(column.dflt)}
                                                </td>
                                                <td className={'border-b border-gray-700 px-2 py-1'}>
                                                    {column.pk ? '✓' : ''}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                <pre
                                    className={
                                        'overflow-auto rounded bg-gray-900/60 p-3 font-mono text-xs text-gray-200'
                                    }
                                >
                                    {info.sql}
                                </pre>
                            </div>
                        )}
                        {view === 'sql' && (
                            <div className={'flex h-full flex-col'}>
                                <div className={'border-b border-gray-700 p-2'}>
                                    <textarea
                                        value={sql}
                                        onChange={(event) => setSql(event.currentTarget.value)}
                                        onKeyDown={(event) => {
                                            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                                                event.preventDefault();
                                                runSql();
                                            }
                                        }}
                                        placeholder={info ? `SELECT * FROM ${quote(info.name)} LIMIT 10;` : 'SELECT 1;'}
                                        rows={5}
                                        spellCheck={false}
                                        className={
                                            'w-full rounded border border-gray-600 bg-gray-900/60 p-2 font-mono text-xs text-gray-100 outline-none focus:border-primary-400'
                                        }
                                    />
                                    <div className={'mt-1 flex items-center gap-2'}>
                                        <button
                                            type={'button'}
                                            onClick={runSql}
                                            className={
                                                'flex items-center gap-1 rounded bg-primary-500 px-2 py-1 text-xs text-white hover:bg-primary-400'
                                            }
                                        >
                                            <Play className={'h-3.5 w-3.5'} />
                                            {t('sqlite.run')}
                                        </button>
                                        <span className={'text-2xs text-gray-500'}>
                                            {sqlInfo || t('sqlite.run_help')}
                                        </span>
                                    </div>
                                </div>
                                <div className={'min-h-0 flex-1 overflow-auto'}>
                                    {results?.map((result, i) => (
                                        <div key={i} className={'mb-3'}>
                                            <ResultTable result={result} />
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <div className={'flex items-center gap-3 border-t border-gray-700 px-2 py-1 text-2xs text-gray-400'}>
                <span className={'min-w-0 flex-1 truncate'}>
                    SQLite · {t('sqlite.table_count', { count: tables.length })} ·{' '}
                    {readOnly ? t('editor.read_only') : tab.dirty ? t('sqlite.unsaved') : t('editor.saved')}
                </span>
                {!readOnly && (
                    <button
                        type={'button'}
                        onClick={save}
                        disabled={saving || !tab.dirty}
                        className={classNames(
                            'flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium disabled:cursor-not-allowed',
                            tab.dirty ? 'bg-primary-500 text-white hover:bg-primary-400' : 'bg-gray-700 text-gray-400'
                        )}
                    >
                        {saving ? (
                            <Loader2 className={'h-3.5 w-3.5 animate-spin'} />
                        ) : (
                            <Save className={'h-3.5 w-3.5'} />
                        )}
                        {t('editor.save')}
                    </button>
                )}
            </div>
        </div>
    );
};
