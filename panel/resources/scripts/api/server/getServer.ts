import http, { FractalResponseData, FractalResponseList } from '@/api/http';
import { rawDataToServerAllocation, rawDataToServerEggVariable } from '@/api/transformers';
import { ServerEggVariable, ServerStatus } from '@/api/server/types';
import { Identifier } from '@/api/definitions';
import { HydraBackground } from '@/state/settings';
import { ip } from '@/lib/formatters';

export interface Allocation {
    id: number;
    ip: string;
    alias: string | null;
    port: number;
    notes: string | null;
    isDefault: boolean;
}

export interface Server {
    /**
     * This value is determined by the presence of the `PTERODACTYL_USE_SERVER_IDENTIFIERS` environment
     * variable which changes what the API can respond with. It will eventually be removed and referenced
     * as the "identifier" key, but this allows users to slowly opt-in to these new URLs and trial it
     * as they wish.
     *
     * @deprecated this is the "uuid_short" which will be removed in 2.0, prefer use of "identifier"
     */
    id: string | Identifier<'serv'>;
    identifier: Identifier<'serv'>; // Set from "server_identifier" and should be used moving forward to reference a server.
    internalId: number | string;
    /**
     * Exists only to maintain support in cases where the short-uuid is necessary for server reference
     * and cannot be easily replaced with "identifier".
     *
     * @deprecated
     */
    __deprecatedUuidShort: string;
    uuid: string;
    name: string;
    node: string;
    isNodeUnderMaintenance: boolean;
    status: ServerStatus;
    sftpDetails: {
        ip: string;
        port: number;
    };
    invocation: string;
    dockerImage: string;
    description: string;
    limits: {
        memory: number;
        swap: number;
        disk: number;
        io: number;
        cpu: number;
        threads: string;
    };
    eggFeatures: string[];
    eggId: number;
    featureLimits: {
        databases: number;
        allocations: number;
        backups: number;
        // Hydra: backup space in MiB.
        backup_storage: number;
    };
    isTransferring: boolean;
    skipScripts: boolean;
    // Hydra: whether this user keeps full access while the server is suspended.
    canUseSuspended: boolean;
    // Hydra: lockdown by the owner. "lockedOut" users may not use the server meanwhile.
    lockdown: {
        active: boolean;
        since: Date | null;
        canToggle: boolean;
        lockedOut: boolean;
    };
    variables: ServerEggVariable[];
    allocations: Allocation[];
    // Hydra: only filled in for users who may open every server.
    owner: { username: string; name: string } | null;
    // Hydra: background of the egg, null for the dashboard background.
    background: HydraBackground | null;
    // Hydra: for the server dashboard (addon).
    banner: string | null;
    // Hydra: picture in the server list (Design > Eggs).
    listImage: string | null;
    eggName: string;
    // Hydra: players online are counted (addon "player_stats").
    playerStats: boolean;
    // Hydra: address players connect over when it is not the primary one (e.g. ARK: query port).
    connectAddress: { ip: string; alias: string | null; port: number } | null;
    location: string | null;
    createdAt: Date | null;
}

/**
 * Hydra: the address players connect over, "host:port". That is the primary
 * allocation unless the egg marks another port for it.
 */
export const serverAddress = (server: Pick<Server, 'allocations' | 'connectAddress'>): string | null => {
    const address = server.connectAddress || server.allocations.find((item) => item.isDefault);

    return address ? `${address.alias || ip(address.ip)}:${address.port}` : null;
};

export const rawDataToServerObject = ({ attributes: data }: FractalResponseData): Server => ({
    id: data.identifier,
    identifier: data.server_identifier,
    internalId: data.internal_id,
    __deprecatedUuidShort: data.__deprecated_uuid_short,
    uuid: data.uuid,
    name: data.name,
    owner: data.owner || null,
    background: data.background || null,
    banner: data.banner || null,
    listImage: data.list_image || null,
    eggName: data.egg_name || '',
    playerStats: !!data.player_stats,
    connectAddress: data.connect_address || null,
    location: data.location || null,
    createdAt: data.created_at ? new Date(data.created_at) : null,
    node: data.node,
    isNodeUnderMaintenance: data.is_node_under_maintenance,
    status: data.status,
    invocation: data.invocation,
    dockerImage: data.docker_image,
    sftpDetails: {
        ip: data.sftp_details.ip,
        port: data.sftp_details.port,
    },
    description: data.description ? (data.description.length > 0 ? data.description : null) : null,
    limits: { ...data.limits },
    eggFeatures: data.egg_features || [],
    eggId: data.egg_id,
    featureLimits: { ...data.feature_limits },
    isTransferring: data.is_transferring,
    skipScripts: data.skip_scripts,
    canUseSuspended: data.can_use_suspended || false,
    lockdown: {
        active: data.lockdown?.active || false,
        since: data.lockdown?.since ? new Date(data.lockdown.since) : null,
        canToggle: data.lockdown?.can_toggle || false,
        lockedOut: data.lockdown?.locked_out || false,
    },
    variables: ((data.relationships?.variables as FractalResponseList | undefined)?.data || []).map(
        rawDataToServerEggVariable
    ),
    allocations: ((data.relationships?.allocations as FractalResponseList | undefined)?.data || []).map(
        rawDataToServerAllocation
    ),
});

export default (uuid: string): Promise<[Server, string[], string[]]> => {
    return new Promise((resolve, reject) => {
        http.get(`/api/client/servers/${uuid}`)
            .then(({ data }) =>
                resolve([
                    rawDataToServerObject(data),
                    // eslint-disable-next-line camelcase
                    data.meta?.is_server_owner ? ['*'] : data.meta?.user_permissions || [],
                    // Hydra: folders the user is limited to, empty for the whole server.
                    // eslint-disable-next-line camelcase
                    data.meta?.allowed_paths || [],
                ])
            )
            .catch(reject);
    });
};
