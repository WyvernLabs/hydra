import http from '@/api/http';

// Hydra: the files go into the trash unless "permanent" is set (or the trash is off).
export default (uuid: string, directory: string, files: string[], permanent = false): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/files/delete`, { root: directory, files, permanent })
            .then(() => resolve())
            .catch(reject);
    });
};
