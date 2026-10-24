import useSWR from 'swr';
import http from '@/api/http';
import { ServerContext } from '@/state/server';

// Hydra: roles of a server, named sets of permissions for its subusers.

export interface ServerRole {
    id: number;
    name: string;
    color: string;
    permissions: string[];
    // Subusers that have the role.
    members: number;
}

export interface RoleValues {
    name: string;
    color: string;
    permissions: string[];
}

export const getServerRoles = (uuid: string): Promise<ServerRole[]> =>
    http.get(`/api/client/servers/${uuid}/roles`).then(({ data }) => data.data);

export const saveServerRole = (uuid: string, values: RoleValues, id?: number): Promise<ServerRole> =>
    (id
        ? http.patch(`/api/client/servers/${uuid}/roles/${id}`, values)
        : http.post(`/api/client/servers/${uuid}/roles`, values)
    ).then(({ data }) => data);

export const deleteServerRole = (uuid: string, id: number): Promise<void> =>
    http.delete(`/api/client/servers/${uuid}/roles/${id}`).then(() => undefined);

export const useServerRoles = () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return useSWR<ServerRole[]>(['server:roles', uuid], () => getServerRoles(uuid), { revalidateOnFocus: false });
};
