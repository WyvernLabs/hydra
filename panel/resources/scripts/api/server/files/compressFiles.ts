import { FileObject } from '@/api/server/files/loadDirectory';
import http from '@/api/http';
import { rawDataToFileObject } from '@/api/transformers';

// Hydra: progress is the id the daemon reports the progress under.
export default async (uuid: string, directory: string, files: string[], progress?: string): Promise<FileObject> => {
    const { data } = await http.post(
        `/api/client/servers/${uuid}/files/compress`,
        { root: directory, files, progress },
        {
            timeout: 900000,
            timeoutErrorMessage:
                'It looks like this archive is taking a long time to generate. It will appear once completed.',
        }
    );

    return rawDataToFileObject(data);
};
