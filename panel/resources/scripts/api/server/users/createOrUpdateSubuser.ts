import http from '@/api/http';
import { rawDataToServerSubuser } from '@/api/server/users/getServerSubusers';
import { Subuser } from '@/state/server/subusers';

interface Params {
    username: string;
    permissions: string[];
    allowedPaths: string[];
    // Hydra: role for the permissions, null for own permissions.
    role: number | null;
}

export default (uuid: string, params: Params, subuser?: Subuser): Promise<Subuser> => {
    return new Promise((resolve, reject) => {
        http.post(`/api/client/servers/${uuid}/users${subuser ? `/${subuser.uuid}` : ''}`, {
            username: params.username,
            permissions: params.permissions,
            // eslint-disable-next-line camelcase
            allowed_paths: params.allowedPaths,
            role: params.role,
        })
            .then((data) => resolve(rawDataToServerSubuser(data.data)))
            .catch(reject);
    });
};
