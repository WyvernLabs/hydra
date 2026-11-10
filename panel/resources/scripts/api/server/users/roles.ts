import useSWR from 'swr';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

// Hydra: roles of a server, named sets of permissions for its subusers.

export interface ServerRole {
    id: number;
    name: string;
    color: string;
    permissions: string[];
    // Folders the members are limited to, empty for the whole server.
    allowedPaths: string[];
    // Subusers that have the role.
    members: number;
}

export interface RoleValues {
    name: string;
    color: string;
    permissions: string[];
    allowedPaths: string[];
}

export interface RoleCopyTarget {
    uuid: string;
    identifier: string;
    name: string;
}

export type RoleCopyStatus = 'created' | 'updated' | 'forbidden' | 'permissions' | 'folders' | 'limit';

export interface RoleCopyResult {
    uuid: string;
    name: string | null;
    status: RoleCopyStatus;
}

const toRole = (data: any): ServerRole => ({
    id: data.id,
    name: data.name,
    color: data.color,
    permissions: data.permissions,
    allowedPaths: data.allowed_paths || [],
    members: data.members,
});

export const getServerRoles = (uuid: string): Promise<ServerRole[]> =>
    http.get(`/api/client/servers/${uuid}/roles`).then(({ data }) => data.data.map(toRole));

export const saveServerRole = (uuid: string, values: RoleValues, id?: number): Promise<ServerRole> => {
    const body = {
        name: values.name,
        color: values.color,
        permissions: values.permissions,
        allowed_paths: values.allowedPaths,
    };

    return (
        id
            ? http.patch(`/api/client/servers/${uuid}/roles/${id}`, body)
            : http.post(`/api/client/servers/${uuid}/roles`, body)
    ).then(({ data }) => toRole(data));
};

export const deleteServerRole = (uuid: string, id: number): Promise<void> =>
    http.delete(`/api/client/servers/${uuid}/roles/${id}`).then(() => undefined);

// Other servers of the user the role can be copied to.
export const getRoleCopyTargets = (uuid: string): Promise<RoleCopyTarget[]> =>
    http.get(`/api/client/servers/${uuid}/roles/targets`).then(({ data }) => data.data);

export const copyServerRole = (uuid: string, id: number, servers: string[]): Promise<RoleCopyResult[]> =>
    http.post(`/api/client/servers/${uuid}/roles/${id}/copy`, { servers }).then(({ data }) => data.data);

export const useServerRoles = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<ServerRole[]>(['server:roles', uuid], () => getServerRoles(uuid), { revalidateOnFocus: false });
};
