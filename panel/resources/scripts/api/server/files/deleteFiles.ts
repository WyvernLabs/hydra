import http from '@/api/http';

// Hydra: the files go into the trash while it is enabled. Only the trash can delete them for good.
export default (uuid: string, directory: string, files: string[]): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/files/delete`, { root: directory, files })
            .then(() => resolve())
            .catch(reject);
    });
};
