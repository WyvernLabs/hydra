import http from '@/api/http';

// Hydra: binary files (NBT, SQLite) are saved as bytes.
export default async (uuid: string, file: string, content: string | Blob | Uint8Array): Promise<void> => {
    await http.post(`/api/client/servers/${uuid}/files/write`, content, {
        params: { file },
        headers: {
            'Content-Type': typeof content === 'string' ? 'text/plain' : 'application/octet-stream',
        },
        timeout: 300000,
    });
};
