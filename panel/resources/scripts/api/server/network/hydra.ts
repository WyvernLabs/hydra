import http from '@/api/http';
import { Allocation } from '@/api/server/getServer';
import { rawDataToServerAllocation } from '@/api/transformers';

// Hydra: subdomains, reverse proxies and free ports on a dedicated node.

export interface Subdomain {
    id: number;
    name: string;
    allocation: number | null;
    srv: string | null;
}

export interface Proxy {
    id: number;
    domain: string;
    allocation: number;
    port: number;
    managed: boolean;
}

export interface NetworkExtras {
    dedicated: { enabled: boolean; reserved: number[]; minPort: number };
    subdomains: {
        enabled: boolean;
        limit: number;
        domains: { id: number; name: string }[];
        services: string[];
        items: Subdomain[];
    };
    proxies: { enabled: boolean; limit: number; target: string; items: Proxy[] };
}

export const getNetworkExtras = async (uuid: string): Promise<NetworkExtras> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/network/hydra`);

    return {
        dedicated: {
            enabled: data.dedicated.enabled,
            reserved: data.dedicated.reserved,
            minPort: data.dedicated.min_port,
        },
        subdomains: data.subdomains,
        proxies: data.proxies,
    };
};

export const addDedicatedPort = async (uuid: string, port: number): Promise<Allocation> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/network/allocations-dedicated`, { port });

    return rawDataToServerAllocation(data);
};

export const createSubdomain = async (
    uuid: string,
    values: { domain: number; name: string; allocation: number | null; srv: string | null }
): Promise<Subdomain> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/network/subdomains`, values, { timeout: 60000 });

    return data;
};

export const deleteSubdomain = async (uuid: string, id: number): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/network/subdomains/${id}`, { timeout: 60000 });
};

export const createProxy = async (
    uuid: string,
    values: { allocation: number; type: 'own' | 'subdomain'; domain?: string; domainId?: number; name?: string }
): Promise<Proxy> => {
    const { data } = await http.post(
        `/api/client/servers/${uuid}/network/proxies`,
        {
            allocation: values.allocation,
            type: values.type,
            domain: values.domain,
            domain_id: values.domainId,
            name: values.name,
        },
        { timeout: 90000 }
    );

    return data;
};

export const deleteProxy = async (uuid: string, id: number): Promise<void> => {
    await http.delete(`/api/client/servers/${uuid}/network/proxies/${id}`, { timeout: 60000 });
};
