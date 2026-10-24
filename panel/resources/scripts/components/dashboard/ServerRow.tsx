import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Cpu, HardDrive, MapPin, MemoryStick, Network } from 'lucide-react';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { useServerLink } from '@/components/hydra/serverNavigation';
import { useCpuValue } from '@/lib/usage';

// Hydra: a server in the server list. On the left the picture of its egg
// (Design > Eggs) with name, ID, address and state, on the right the live
// usage of CPU, memory and storage.

type Timer = ReturnType<typeof setInterval>;

// Whether the usage is close to the limit (90 %), which is shown in red.
const isAlarm = (current: number, limit: number): boolean => limit > 0 && current / limit >= 0.9;

const stateColors: Record<ServerPowerState, string> = {
    running: 'bg-green-500/20 text-green-300',
    starting: 'bg-yellow-500/20 text-yellow-300',
    stopping: 'bg-yellow-500/20 text-yellow-300',
    offline: 'bg-red-500/20 text-red-300',
};

const dotColors: Record<ServerPowerState, string> = {
    running: 'bg-green-400',
    starting: 'bg-yellow-400',
    stopping: 'bg-yellow-400',
    offline: 'bg-red-400',
};

const Stat = ({
    icon: Icon,
    label,
    limit,
    value,
    alarm,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    limit: string;
    value: string;
    alarm: boolean;
}) => (
    <div className={'flex items-center gap-3 py-3'}>
        <span className={'flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-700/70 text-gray-300'}>
            <Icon className={'h-4 w-4'} />
        </span>
        <div className={'min-w-0 flex-1'}>
            <p className={'text-xs uppercase tracking-wide text-gray-400'}>{label}</p>
            <p className={'text-xs text-gray-500'}>{limit}</p>
        </div>
        <span className={`font-mono text-sm font-semibold ${alarm ? 'text-red-400' : 'text-gray-50'}`}>{value}</span>
    </div>
);

export default ({ server, className }: { server: Server; className?: string }) => {
    const { t } = useTranslation(['client/dashboard', 'client/server']);
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);
    // Hydra: users locked out by a lockdown get no stats for the server.
    const lockedOut = server.lockdown.lockedOut;
    const serverLink = useServerLink();
    const cpuValue = useCpuValue();

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        // Don't waste a request if there is nothing to show because the server is unavailable.
        if (isSuspended || lockedOut || server.isNodeUnderMaintenance) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended, lockedOut, server.isNodeUnderMaintenance]);

    const unlimited = t('client/common:unlimited');
    const allocation = server.allocations.find((item) => item.isDefault);
    const picture = server.listImage || server.banner;

    // A state that replaces the live state, e.g. suspended or installing.
    const notice = isSuspended
        ? t('server.suspended')
        : lockedOut
        ? t('server.lockdown')
        : server.isNodeUnderMaintenance
        ? t('server.maintenance')
        : server.isTransferring
        ? t('server.transferring')
        : server.status === 'installing'
        ? t('server.installing')
        : server.status === 'restoring_backup'
        ? t('server.restoring_backup')
        : server.status
        ? t('server.unavailable')
        : null;

    const memoryLimit = mbToBytes(server.limits.memory);
    const diskLimit = mbToBytes(server.limits.disk);
    const live = stats && !notice;

    return (
        <Link
            to={serverLink(server.id)}
            className={`group grid overflow-hidden rounded-lg border border-gray-700 bg-gray-800/80 no-underline backdrop-blur transition-colors hover:border-gray-500 sm:grid-cols-2 ${
                className || ''
            }`}
        >
            {/* Picture with name, ID, address and state */}
            <div className={'relative flex min-h-[11rem] flex-col p-4'}>
                {picture ? (
                    <img
                        src={picture}
                        alt={''}
                        className={
                            'absolute inset-0 h-full w-full object-cover transition-transform duration-300 group-hover:scale-105'
                        }
                    />
                ) : (
                    <div
                        className={'absolute inset-0'}
                        style={{ background: 'var(--hydra-bg) var(--hydra-pattern) center / 380px repeat' }}
                    />
                )}
                <div className={'absolute inset-0 bg-gradient-to-r from-black/75 via-black/45 to-black/10'} />
                <div className={'relative min-w-0'}>
                    <p className={'truncate text-lg font-semibold text-white'}>{server.name}</p>
                    <p className={'font-mono text-xs text-gray-300'}>{server.id}</p>
                    {allocation && (
                        <p className={'mt-3 flex items-center gap-1.5 text-sm text-gray-200'}>
                            <Network className={'h-3.5 w-3.5 shrink-0'} />
                            <span className={'truncate'}>
                                {allocation.alias || ip(allocation.ip)}:{allocation.port}
                            </span>
                        </p>
                    )}
                    <p className={'mt-1 flex items-center gap-1.5 text-xs text-gray-300'}>
                        <MapPin className={'h-3.5 w-3.5 shrink-0'} />
                        <span className={'truncate'}>
                            {server.location ? `${server.node} (${server.location})` : server.node}
                        </span>
                    </p>
                </div>
                <div className={'relative mt-auto pt-3'}>
                    {notice ? (
                        <span className={'rounded-full bg-red-500/80 px-2.5 py-1 text-xs font-medium text-white'}>
                            {notice}
                        </span>
                    ) : (
                        <span
                            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ${
                                stateColors[stats?.status || 'offline']
                            }`}
                        >
                            <span className={`h-1.5 w-1.5 rounded-full ${dotColors[stats?.status || 'offline']}`} />
                            {t(`client/server:status.${stats?.status || 'offline'}`)}
                        </span>
                    )}
                </div>
            </div>

            {/* Live usage */}
            <div className={'flex flex-col justify-center divide-y divide-gray-700/60 px-4 py-1'}>
                <Stat
                    icon={Cpu}
                    label={t('server.cpu')}
                    limit={server.limits.cpu ? `${cpuValue(server.limits.cpu, server.limits.cpu)} %` : unlimited}
                    value={live ? `${cpuValue(stats!.cpuUsagePercent, server.limits.cpu).toFixed(2)} %` : '–'}
                    alarm={!!live && server.limits.cpu > 0 && stats!.cpuUsagePercent >= server.limits.cpu * 0.9}
                />
                <Stat
                    icon={MemoryStick}
                    label={t('server.memory')}
                    limit={server.limits.memory ? bytesToString(memoryLimit) : unlimited}
                    value={live ? bytesToString(stats!.memoryUsageInBytes) : '–'}
                    alarm={!!live && isAlarm(stats!.memoryUsageInBytes, memoryLimit)}
                />
                <Stat
                    icon={HardDrive}
                    label={t('server.storage')}
                    limit={server.limits.disk ? bytesToString(diskLimit) : unlimited}
                    value={live ? bytesToString(stats!.diskUsageInBytes) : '–'}
                    alarm={!!live && isAlarm(stats!.diskUsageInBytes, diskLimit)}
                />
            </div>
        </Link>
    );
};
