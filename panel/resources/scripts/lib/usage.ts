import { useStoreState } from '@/state/hooks';

// Hydra: helpers to show the usage of a server.

/**
 * CPU usage as shown to the user. Pterodactyl counts 100 % per core, so a
 * server with a 300 % limit can use up to 300 %. With the addon "CPU out of
 * 100 %" the value is shown out of the limit instead: 60 % of 300 % is 20 %.
 * Servers without a limit keep the usual value.
 */
export const cpuValue = (value: number, limit: number, normalized: boolean): number =>
    normalized && limit > 0 ? (value / limit) * 100 : value;

/**
 * cpuValue with the setting of the panel.
 */
export const useCpuValue = (): ((value: number, limit: number) => number) => {
    const normalized = useStoreState((state) => state.settings.data!.console.cpuNormalized);

    return (value, limit) => cpuValue(value, limit, normalized);
};

/**
 * Size in GB when it is at least one, else in MB, e.g. "1.25 GB" or "512 MB".
 */
export const gbOrMb = (bytes: number): string => {
    const gb = bytes / 1024 / 1024 / 1024;

    return gb >= 1 ? `${gb.toFixed(2)} GB` : `${Math.round(bytes / 1024 / 1024)} MB`;
};

/**
 * Share of the limit in percent, 0 without a limit.
 */
export const percentOf = (value: number, limit: number): number => (limit > 0 ? (value / limit) * 100 : 0);
