import { action, Action } from 'easy-peasy';
import { cleanDirectoryPath } from '@/helpers';

export type FileTransferKind = 'upload' | 'download' | 'archive' | 'unarchive';

// Hydra: uploads, downloads, archives and extractions shown in the transfer
// list. Uploads report their progress from the browser, everything else from
// the daemon over the websocket.
export interface FileTransfer {
    id: string;
    kind: FileTransferKind;
    name: string;
    loaded: number;
    total: number;
    // Set once the first progress arrived, until then the progress is unknown.
    started: boolean;
    status: 'running' | 'done' | 'failed';
    updatedAt: number;
    readonly abort?: AbortController;
}

export interface ServerFileStore {
    directory: string;
    selectedFiles: string[];
    transfers: Record<string, FileTransfer>;

    setDirectory: Action<ServerFileStore, string>;
    setSelectedFiles: Action<ServerFileStore, string[]>;
    appendSelectedFile: Action<ServerFileStore, string>;
    removeSelectedFile: Action<ServerFileStore, string>;

    pushTransfer: Action<
        ServerFileStore,
        Pick<FileTransfer, 'id' | 'kind' | 'name'> & { total?: number; abort?: AbortController }
    >;
    setTransferProgress: Action<ServerFileStore, { id: string; loaded: number; total?: number }>;
    finishTransfer: Action<ServerFileStore, { id: string; failed?: boolean }>;
    removeTransfer: Action<ServerFileStore, string>;
    cancelTransfer: Action<ServerFileStore, string>;
}

const files: ServerFileStore = {
    directory: '/',
    selectedFiles: [],
    transfers: {},

    setDirectory: action((state, payload) => {
        state.directory = cleanDirectoryPath(payload);
    }),

    setSelectedFiles: action((state, payload) => {
        state.selectedFiles = payload;
    }),

    appendSelectedFile: action((state, payload) => {
        state.selectedFiles = state.selectedFiles.filter((f) => f !== payload).concat(payload);
    }),

    removeSelectedFile: action((state, payload) => {
        state.selectedFiles = state.selectedFiles.filter((f) => f !== payload);
    }),

    pushTransfer: action((state, { total, ...payload }) => {
        state.transfers[payload.id] = {
            ...payload,
            loaded: 0,
            total: total || 0,
            started: payload.kind === 'upload',
            status: 'running',
            updatedAt: Date.now(),
        };
    }),

    setTransferProgress: action((state, { id, loaded, total }) => {
        const transfer = state.transfers[id];
        if (!transfer || transfer.status !== 'running') return;

        transfer.loaded = loaded;
        if (total !== undefined) transfer.total = total;
        transfer.started = true;
        transfer.updatedAt = Date.now();
    }),

    finishTransfer: action((state, { id, failed }) => {
        const transfer = state.transfers[id];
        if (!transfer || transfer.status !== 'running') return;

        transfer.status = failed ? 'failed' : 'done';
        if (!failed) transfer.loaded = transfer.total;
        transfer.updatedAt = Date.now();
    }),

    removeTransfer: action((state, payload) => {
        delete state.transfers[payload];
    }),

    cancelTransfer: action((state, payload) => {
        // Abort the request if it is still in flight. If it already completed this is
        // a no-op.
        state.transfers[payload]?.abort?.abort();

        delete state.transfers[payload];
    }),
};

export default files;
