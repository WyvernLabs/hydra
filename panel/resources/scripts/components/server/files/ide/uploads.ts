import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import getFileUploadUrl from '@/api/server/files/getFileUploadUrl';
import createDirectory from '@/api/server/files/createDirectory';
import { newTransferId } from '@/plugins/useFileTransfers';
import { joinPath } from '@/components/server/files/ide/paths';

// Hydra: uploads of files and whole folders. A folder is uploaded as files
// named by their path inside it ("world/region/r.0.0.mca"), Wings creates the
// folders on the way. Files are sent in batches, so a folder with thousands of
// small files does not need thousands of requests.

export interface UploadItem {
    file: File;
    // Path relative to the upload folder, including the file name.
    path: string;
}

export interface UploadSelection {
    items: UploadItem[];
    // Empty folders, which would get lost otherwise.
    folders: string[];
}

const BATCH_FILES = 50;
const BATCH_BYTES = 64 * 1024 * 1024;
const PARALLEL = 3;

const readEntries = (reader: FileSystemDirectoryReader): Promise<FileSystemEntry[]> =>
    new Promise((resolve, reject) => reader.readEntries(resolve, reject));

const entryFile = (entry: FileSystemFileEntry): Promise<File> =>
    new Promise((resolve, reject) => entry.file(resolve, reject));

const walkEntry = async (entry: FileSystemEntry, prefix: string, out: UploadSelection): Promise<void> => {
    const path = prefix ? `${prefix}/${entry.name}` : entry.name;
    if (entry.isFile) {
        out.items.push({ file: await entryFile(entry as FileSystemFileEntry), path });
        return;
    }
    if (!entry.isDirectory) return;

    const reader = (entry as FileSystemDirectoryEntry).createReader();
    let children: FileSystemEntry[] = [];
    // readEntries returns at most 100 entries per call.
    for (;;) {
        const batch = await readEntries(reader);
        if (batch.length === 0) break;
        children = children.concat(batch);
    }
    if (children.length === 0) {
        out.folders.push(path);
        return;
    }
    for (const child of children) {
        await walkEntry(child, path, out);
    }
};

/**
 * Files and folders dropped from the computer.
 */
export const selectionFromDataTransfer = async (transfer: DataTransfer): Promise<UploadSelection> => {
    const out: UploadSelection = { items: [], folders: [] };
    const entries = Array.from(transfer.items)
        .filter((item) => item.kind === 'file')
        .map((item) => item.webkitGetAsEntry?.())
        .filter((entry): entry is FileSystemEntry => !!entry);

    if (entries.length === 0) {
        Array.from(transfer.files).forEach((file) => out.items.push({ file, path: file.name }));
        return out;
    }
    for (const entry of entries) {
        await walkEntry(entry, '', out);
    }

    return out;
};

/**
 * Files picked with a file input, with webkitdirectory for whole folders.
 */
export const selectionFromInput = (files: FileList): UploadSelection => ({
    items: Array.from(files).map((file) => ({ file, path: file.webkitRelativePath || file.name })),
    folders: [],
});

const batches = (items: UploadItem[]): UploadItem[][] => {
    const out: UploadItem[][] = [];
    let current: UploadItem[] = [];
    let bytes = 0;
    for (const item of items) {
        if (current.length > 0 && (current.length >= BATCH_FILES || bytes + item.file.size > BATCH_BYTES)) {
            out.push(current);
            current = [];
            bytes = 0;
        }
        current.push(item);
        bytes += item.file.size;
    }
    if (current.length > 0) out.push(current);

    return out;
};

export default () => {
    const { t } = useTranslation('client/files');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { pushTransfer, setTransferProgress, finishTransfer } = ServerContext.useStoreActions(
        (actions) => actions.files
    );

    /**
     * Uploads the selection into the directory. Shows a single entry in the
     * transfer list and resolves once everything was uploaded.
     */
    return async (directory: string, selection: UploadSelection): Promise<void> => {
        const { items, folders } = selection;
        if (items.length === 0 && folders.length === 0) return;

        const roots = Array.from(
            new Set(items.map((item) => item.path.split('/')[0]).concat(folders.map((f) => f.split('/')[0])))
        );
        const total = items.reduce((sum, item) => sum + item.file.size, 0);
        const id = newTransferId();
        const controller = new AbortController();
        pushTransfer({
            id,
            kind: 'upload',
            name: roots.length === 1 ? roots[0] : t('upload.items', { count: items.length }),
            total,
            abort: controller,
        });

        const done = new Map<number, number>();
        const report = () => {
            let loaded = 0;
            done.forEach((value) => (loaded += value));
            setTransferProgress({ id, loaded, total });
        };

        try {
            for (const folder of folders) {
                const parts = folder.split('/');
                const name = parts.pop()!;
                await createDirectory(uuid, joinPath(directory, parts.join('/')), name);
            }

            const queue = batches(items).map((batch, index) => ({ batch, index }));
            const worker = async () => {
                for (let next = queue.shift(); next; next = queue.shift()) {
                    const { batch, index } = next;
                    const form = new FormData();
                    batch.forEach((item) => form.append('files', item.file, item.path));
                    const url = await getFileUploadUrl(uuid);
                    await axios.post(url, form, {
                        signal: controller.signal,
                        params: { directory },
                        onUploadProgress: (event) => {
                            done.set(index, event.loaded);
                            report();
                        },
                    });
                    done.set(
                        index,
                        batch.reduce((sum, item) => sum + item.file.size, 0)
                    );
                    report();
                }
            };
            await Promise.all(Array.from({ length: Math.min(PARALLEL, queue.length) }, worker));
            finishTransfer({ id });
        } catch (error) {
            if (axios.isCancel(error)) return;
            finishTransfer({ id, failed: true });
            throw error;
        }
    };
};
