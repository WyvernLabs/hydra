import http from '@/api/http';

// Hydra: puts the server into lockdown or lifts it (owner and admins only).
export default (uuid: string, lockdown: boolean): Promise<void> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/settings/lockdown`, { lockdown })
            .then(() => resolve())
            .catch(reject);
    });
};
