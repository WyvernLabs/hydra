import getFilePreviewUrl from '@/api/server/files/getFilePreviewUrl';

// Hydra: the raw bytes of a file (NBT and SQLite editors), loaded straight from
// the node so large files do not pass through the panel.
export default async (uuid: string, file: string): Promise<ArrayBuffer> => {
    const url = await getFilePreviewUrl(uuid, file);
    const response = await fetch(url);
    if (!response.ok) {
        throw new Error(`${response.status} ${response.statusText}`);
    }

    return response.arrayBuffer();
};
