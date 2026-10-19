import http, { FractalResponseData } from '@/api/http';
import { Subuser } from '@/state/server/subusers';

export const rawDataToServerSubuser = (data: FractalResponseData): Subuser => ({
    uuid: data.attributes.uuid,
    username: data.attributes.username,
    image: data.attributes.image || null,
    online: typeof data.attributes.online === 'boolean' ? data.attributes.online : null,
    twoFactorEnabled: data.attributes['2fa_enabled'],
    createdAt: new Date(data.attributes.created_at),
    permissions: data.attributes.permissions || [],
    allowedPaths: data.attributes.allowed_paths || [],
    can: (permission) => (data.attributes.permissions || []).indexOf(permission) >= 0,
});

export default (uuid: string): Promise<Subuser[]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}/users`)
            .then(({ data }) => resolve((data.data || []).map(rawDataToServerSubuser)))
            .catch(reject);
    });
};
