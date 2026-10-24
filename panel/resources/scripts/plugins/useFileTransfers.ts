import { useTranslation } from 'react-i18next';
import { ServerContext } from '@/state/server';
import { FileObject } from '@/api/server/files/loadDirectory';
import compressFiles from '@/api/server/files/compressFiles';
import decompressFiles from '@/api/server/files/decompressFiles';
import getFileDownloadUrl from '@/api/server/files/getFileDownloadUrl';

// Hydra: id the daemon reports the progress of an operation under.
export const newTransferId = (): string => {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return Array.from({ length: 4 }, () => Math.random().toString(36).slice(2, 10)).join('-');
};

/**
 * Hydra: downloads, archives and extracts files while showing them in the
 * transfer list in the bottom right corner.
 */
export default () => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { pushTransfer, finishTransfer, removeTransfer } = ServerContext.useStoreActions((actions) => actions.files);

    const download = (path: string, name: string, size: number): Promise<void> => {
        const id = newTransferId();
        pushTransfer({ id, kind: 'download', name, total: size });

        return getFileDownloadUrl(uuid, path)
            .then((url) => {
                // @ts-expect-error this is valid
                window.location = `${url}&progress=${encodeURIComponent(id)}`;
            })
            .catch((error) => {
                removeTransfer(id);
                throw error;
            });
    };

    const archive = (directory: string, files: string[]): Promise<FileObject> => {
        const id = newTransferId();
        pushTransfer({
            id,
            kind: 'archive',
            name: files.length === 1 ? files[0] : t('files.transfers.files', { count: files.length }),
        });

        return compressFiles(uuid, directory, files, id)
            .then((file) => {
                finishTransfer({ id });
                return file;
            })
            .catch((error) => {
                removeTransfer(id);
                throw error;
            });
    };

    const unarchive = (directory: string, file: string): Promise<void> => {
        const id = newTransferId();
        pushTransfer({ id, kind: 'unarchive', name: file });

        return decompressFiles(uuid, directory, file, id)
            .then(() => finishTransfer({ id }))
            .catch((error) => {
                removeTransfer(id);
                throw error;
            });
    };

    return { download, archive, unarchive };
};
