import http from '@/api/http';

// Hydra: tables and data of a server database, import, export and phpMyAdmin.

export interface DatabaseTable {
    name: string;
    view: boolean;
    engine: string | null;
    rows: number | null;
    size: number;
    collation: string | null;
}

export interface DatabaseColumn {
    name: string;
    type: string;
    nullable: boolean;
    default: string | null;
    primary: boolean;
    key: string;
    extra: string;
    comment: string;
    binary: boolean;
}

export interface DatabaseIndex {
    name: string;
    unique: boolean;
    type: string;
    columns: string[];
}

export type CellValue = string | number | null;

export interface DatabaseRow {
    values: Record<string, CellValue>;
    // Columns whose value was cut in the table view.
    truncated: string[];
}

export interface DatabaseRows {
    columns: DatabaseColumn[];
    primary: string[];
    rows: DatabaseRow[];
    pagination: { total: number; per_page: number; current_page: number; total_pages: number };
}

export interface QueryResult {
    columns: { name: string }[];
    rows: CellValue[][];
    affected: number | null;
    truncated: boolean;
    time: number;
}

const base = (uuid: string, database: string) => `/api/client/servers/${uuid}/databases/${database}`;

// Hydra: size in bytes and number of tables, null when the database server is not reachable.
export const getDatabaseStats = (
    uuid: string,
    database: string
): Promise<{ tables: number | null; size: number | null }> =>
    http.get(`/api/client/servers/${uuid}/databases/${database}/stats`).then(({ data }) => data);

export const getTables = (uuid: string, database: string): Promise<{ tables: DatabaseTable[]; phpmyadmin: boolean }> =>
    http.get(`${base(uuid, database)}/tables`).then(({ data }) => data);

export const getStructure = (
    uuid: string,
    database: string,
    table: string
): Promise<{ columns: DatabaseColumn[]; indexes: DatabaseIndex[] }> =>
    http.get(`${base(uuid, database)}/structure`, { params: { table } }).then(({ data }) => data);

export const getRows = (
    uuid: string,
    database: string,
    params: { table: string; page: number; sort?: string | null; direction?: 'asc' | 'desc' }
): Promise<DatabaseRows> => http.get(`${base(uuid, database)}/rows`, { params }).then(({ data }) => data);

export const getRow = (
    uuid: string,
    database: string,
    table: string,
    key: Record<string, CellValue>
): Promise<Record<string, CellValue>> =>
    http.post(`${base(uuid, database)}/rows/show`, { table, key }).then(({ data }) => data.values);

export const insertRow = (uuid: string, database: string, table: string, values: Record<string, CellValue>) =>
    http.post(`${base(uuid, database)}/rows`, { table, values });

export const updateRow = (
    uuid: string,
    database: string,
    table: string,
    key: Record<string, CellValue>,
    values: Record<string, CellValue>
) => http.patch(`${base(uuid, database)}/rows`, { table, key, values });

export const deleteRow = (uuid: string, database: string, table: string, key: Record<string, CellValue>) =>
    http.delete(`${base(uuid, database)}/rows`, { data: { table, key } });

export const runQuery = (uuid: string, database: string, sql: string): Promise<QueryResult> =>
    http.post(`${base(uuid, database)}/query`, { sql }).then(({ data }) => data);

export const importDatabase = (uuid: string, database: string, file: File, onProgress?: (percent: number) => void) => {
    const form = new FormData();
    form.append('file', file);

    return http.post(`${base(uuid, database)}/import`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        timeout: 0,
        onUploadProgress: (event) => {
            if (onProgress && event.total) onProgress(Math.round((event.loaded / event.total) * 100));
        },
    });
};

export const exportDatabase = (uuid: string, database: string): Promise<string> =>
    http.post(`${base(uuid, database)}/export`).then(({ data }) => data.url);

export const openPhpMyAdmin = (uuid: string, database: string): Promise<string> =>
    http.post(`${base(uuid, database)}/phpmyadmin`).then(({ data }) => data.url);
