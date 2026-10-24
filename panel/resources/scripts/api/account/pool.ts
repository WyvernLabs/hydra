import http from '@/api/http';

// Hydra: resource pool of the logged in user (resource_pool addon). CPU is in
// percent of a core, memory, disk and backup space in MiB.

export const POOL_RESOURCES = [
    'cpu',
    'memory',
    'disk',
    'databases',
    'ports',
    'backups',
    'subdomains',
    'proxies',
] as const;

export type PoolResource = typeof POOL_RESOURCES[number];

export type PoolResources = Record<PoolResource, number>;

export interface PoolServer {
    uuid: string;
    identifier: string;
    name: string;
    egg: string | null;
    status: string | null;
    resources: PoolResources;
    // What the server uses right now and cannot be taken away.
    inUse: Pick<PoolResources, 'ports' | 'databases' | 'subdomains' | 'proxies'>;
}

export interface PoolEggVariable {
    env: string;
    name: string;
    description: string;
    default: string;
    required: boolean;
}

export interface PoolEgg {
    id: number;
    name: string;
    nest: string | null;
    description: string;
    variables: PoolEggVariable[];
}

export interface ResourcePool {
    // "servers" is null when there is no limit of servers.
    limits: PoolResources & { servers: number | null };
    used: PoolResources & { servers: number };
    minimum: Partial<PoolResources>;
    servers: PoolServer[];
    eggs: PoolEgg[];
    locations: { id: number; name: string }[];
    features: { subdomains: boolean; proxies: boolean };
    canEdit: boolean;
    canDelete: boolean;
}

export interface NewPoolServer extends PoolResources {
    name: string;
    egg: number;
    location: number | null;
    environment: Record<string, string>;
}

const toPool = (data: any): ResourcePool | null =>
    data
        ? {
              limits: data.limits,
              used: data.used,
              minimum: data.minimum,
              servers: data.servers.map((server: any) => ({
                  uuid: server.uuid,
                  identifier: server.identifier,
                  name: server.name,
                  egg: server.egg,
                  status: server.status,
                  resources: server.resources,
                  inUse: server.in_use,
              })),
              eggs: data.eggs,
              locations: data.locations,
              features: data.features,
              canEdit: data.can_edit,
              canDelete: data.can_delete,
          }
        : null;

export const getResourcePool = (): Promise<ResourcePool | null> =>
    http.get('/api/client/account/pool').then(({ data }) => toPool(data.pool));

export const createPoolServer = (values: NewPoolServer): Promise<{ identifier: string; uuid: string }> =>
    http.post('/api/client/account/pool/servers', values).then(({ data }) => data);

export const updatePoolServer = (identifier: string, values: PoolResources): Promise<ResourcePool | null> =>
    http.patch(`/api/client/account/pool/servers/${identifier}`, values).then(({ data }) => toPool(data.pool));

export const deletePoolServer = (identifier: string): Promise<void> =>
    http.delete(`/api/client/account/pool/servers/${identifier}`).then(() => undefined);
