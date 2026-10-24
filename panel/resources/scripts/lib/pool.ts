import { PoolResource } from '@/api/account/pool';

// Hydra: units of the resource pool. The panel keeps CPU in percent of a core
// and memory, disk and backup space in MiB; users see cores and GB.

interface Unit {
    factor: number;
    step: number;
    kind: 'cores' | 'gb' | 'count';
}

export const POOL_UNITS: Record<PoolResource, Unit> = {
    cpu: { factor: 100, step: 0.1, kind: 'cores' },
    memory: { factor: 1024, step: 0.25, kind: 'gb' },
    disk: { factor: 1024, step: 0.5, kind: 'gb' },
    databases: { factor: 1, step: 1, kind: 'count' },
    ports: { factor: 1, step: 1, kind: 'count' },
    backups: { factor: 1024, step: 0.5, kind: 'gb' },
    subdomains: { factor: 1, step: 1, kind: 'count' },
    proxies: { factor: 1, step: 1, kind: 'count' },
};

const round = (value: number): number => Math.round(value * 100) / 100;

export const toShown = (key: PoolResource, value: number): number => round(value / POOL_UNITS[key].factor);

export const fromShown = (key: PoolResource, value: number): number => Math.round(value * POOL_UNITS[key].factor);

// Rounds down to a whole step, so a slider never offers more than is free.
export const floorToStep = (key: PoolResource, value: number): number => {
    const step = POOL_UNITS[key].step * POOL_UNITS[key].factor;

    return Math.floor(value / step + 1e-9) * step;
};

export const formatPool = (key: PoolResource, value: number, t: (key: string, options?: any) => string): string => {
    const shown = toShown(key, value).toLocaleString(undefined, { maximumFractionDigits: 2 });
    switch (POOL_UNITS[key].kind) {
        case 'cores':
            return t(toShown(key, value) === 1 ? 'units.cores_one' : 'units.cores', { value: shown });
        case 'gb':
            return t('units.gb', { value: shown });
        default:
            return shown;
    }
};

// One colour per server, the same in the bars and the list.
export const POOL_COLORS = ['#22c55e', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#ef4444', '#eab308'];

export const poolColor = (index: number): string => POOL_COLORS[index % POOL_COLORS.length];
