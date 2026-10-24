import http from '@/api/http';

// Hydra: progress is the id the daemon reports the progress under.
export default async (uuid: string, directory: string, file: string, progress?: string): Promise<void> => {
    await http.post(
        `/api/client/servers/${uuid}/files/decompress`,
        { root: directory, file, progress },
        {
            timeout: 900000,
            timeoutErrorMessage:
                'It looks like this archive is taking a long time to be unarchived. Once completed the unarchived files will appear.',
        }
    );
};
