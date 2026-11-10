import http from '@/api/http';

// Hydra: import of another server over SFTP (addon "importer") and the
// export to another server (addon "exporter"), which share the endpoints
// below /import and /export.

export type TransferKind = 'import' | 'export';

export interface ImportLogin {
    host: string;
    port: number;
    username: string;
    password?: string;
    privateKey?: string;
    source: string;
}

export type ImportMode = 'keep' | 'wipe' | 'profile';

export interface ImportTest {
    fingerprint: string;
    folder: string;
    entries: number;
    // Export: whether the target folder exists already (it is created otherwise).
    exists?: boolean;
    // The first entries of the folder, folders end with a slash.
    names: string[];
}

export interface ImportStatus {
    state: '' | 'listing' | 'copying' | 'done' | 'failed';
    done: number;
    total: number;
    files: number;
    filesTotal: number;
    error: string | null;
}

const body = (login: ImportLogin, target?: string) => ({
    host: login.host,
    port: login.port,
    username: login.username,
    password: login.password || null,
    private_key: login.privateKey || null,
    source: login.source,
    ...(target !== undefined ? { target } : {}),
});

export const toImportStatus = (data: any): ImportStatus => ({
    state: data.state || '',
    done: data.done || 0,
    total: data.total || 0,
    files: data.files || 0,
    filesTotal: data.files_total || 0,
    error: data.error || null,
});

export const getImportStatus = (uuid: string, kind: TransferKind = 'import'): Promise<ImportStatus> =>
    http.get(`/api/client/servers/${uuid}/${kind}`).then(({ data }) => toImportStatus(data));

export const testImport = (
    uuid: string,
    login: ImportLogin,
    kind: TransferKind = 'import',
    target?: string
): Promise<ImportTest> =>
    http
        .post(`/api/client/servers/${uuid}/${kind}/test`, body(login, kind === 'export' ? target : undefined), {
            timeout: 40000,
        })
        .then(({ data }) => data);

export const startExport = (
    uuid: string,
    login: ImportLogin,
    options: { hostKey: string; target: string }
): Promise<void> =>
    http
        .post(
            `/api/client/servers/${uuid}/export`,
            { ...body(login, options.target), host_key: options.hostKey },
            { timeout: 60000 }
        )
        .then(() => undefined);

export const startImport = (
    uuid: string,
    login: ImportLogin,
    options: { hostKey: string; target: string; mode: ImportMode; profileName: string }
): Promise<void> =>
    http
        .post(
            `/api/client/servers/${uuid}/import`,
            {
                ...body(login),
                host_key: options.hostKey,
                target: options.target,
                mode: options.mode,
                profile_name: options.profileName || null,
            },
            // Stopping the server and keeping its files as a profile takes a while.
            { timeout: 360000 }
        )
        .then(() => undefined);

export const cancelImport = (uuid: string, kind: TransferKind = 'import'): Promise<void> =>
    http.delete(`/api/client/servers/${uuid}/${kind}`).then(() => undefined);
