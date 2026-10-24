import React, { useEffect, useState } from 'react';
import { faClock, faWifi } from '@fortawesome/free-solid-svg-icons';
import { Cpu, HardDrive, MemoryStick } from 'lucide-react';
import { ip, mbToBytes } from '@/lib/formatters';
import { cpuValue as toCpuValue, gbOrMb, percentOf } from '@/lib/usage';
import UsageRing from '@/components/server/console/UsageRing';
import { useStoreState } from '@/state/hooks';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import UptimeDuration from '@/components/server/UptimeDuration';
import StatBlock from '@/components/server/console/StatBlock';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import classNames from 'classnames';
import { capitalize } from '@/lib/strings';
import { useTranslation } from 'react-i18next';

type Stats = Record<'memory' | 'cpu' | 'disk' | 'uptime' | 'rx' | 'tx', number>;

const getBackgroundColor = (value: number, max: number | null): string | undefined => {
    const delta = !max ? 0 : value / max;

    if (delta > 0.8) {
        if (delta > 0.9) {
            return 'bg-red-500';
        }
        return 'bg-yellow-500';
    }

    return undefined;
};

const ServerDetailsBlock = ({ className }: { className?: string }) => {
    const [stats, setStats] = useState<Stats>({ memory: 0, cpu: 0, disk: 0, uptime: 0, tx: 0, rx: 0 });
    const { t, i18n } = useTranslation('client/server');
    const cpuNormalized = useStoreState((state) => state.settings.data!.console.cpuNormalized);
    const cpuValue = (value: number, limit: number) => toCpuValue(value, limit, cpuNormalized);

    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);

    const allocation = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((allocation) => allocation.isDefault);

        return !match ? null : `${match.alias || ip(match.ip)}:${match.port}`;
    });

    useEffect(() => {
        if (!connected || !instance) {
            return;
        }

        instance.send(SocketRequest.SEND_STATS);
    }, [instance, connected]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        let stats: any = {};
        try {
            stats = JSON.parse(data);
        } catch (e) {
            return;
        }

        setStats({
            memory: stats.memory_bytes,
            cpu: stats.cpu_absolute,
            disk: stats.disk_bytes,
            tx: stats.network.tx_bytes,
            rx: stats.network.rx_bytes,
            uptime: stats.uptime || 0,
        });
    });

    // Hydra: rings for CPU, memory and disk like the usage of the nodes.
    const running = status !== null && status !== 'offline';
    const cpu = running ? stats.cpu : 0;
    const memory = running ? stats.memory : 0;
    const cores = limits.cpu / 100;
    const memoryLimit = mbToBytes(limits.memory);
    const diskLimit = mbToBytes(limits.disk);
    const of = (value: number, limit: number) =>
        t('details.of', { value: gbOrMb(value), limit: limit > 0 ? gbOrMb(limit) : t('details.unlimited') });

    return (
        <div className={classNames('flex flex-col gap-2 md:gap-4', className)}>
            <div className={'grid grid-cols-1 gap-2 sm:grid-cols-3 md:gap-4 lg:flex-1 lg:grid-cols-1 lg:grid-rows-3'}>
                <UsageRing
                    title={t('details.cpu')}
                    icon={Cpu}
                    fill={limits.cpu > 0 ? percentOf(cpu, limits.cpu) : Math.min(cpu, 100)}
                    value={`${Math.round(cpuValue(cpu, limits.cpu))} %`}
                    detail={
                        limits.cpu > 0
                            ? t(cores === 1 ? 'details.core' : 'details.cores', {
                                  count: cores.toLocaleString(i18n.language) as unknown as number,
                              })
                            : t('details.cores_unlimited')
                    }
                />
                <UsageRing
                    title={t('details.memory')}
                    icon={MemoryStick}
                    fill={percentOf(memory, memoryLimit)}
                    value={`${Math.round(percentOf(memory, memoryLimit))} %`}
                    detail={of(memory, memoryLimit)}
                />
                <UsageRing
                    title={t('details.disk')}
                    icon={HardDrive}
                    fill={percentOf(stats.disk, diskLimit)}
                    value={`${Math.round(percentOf(stats.disk, diskLimit))} %`}
                    detail={of(stats.disk, diskLimit)}
                />
            </div>
            <div className={'grid grid-cols-2 gap-2 md:gap-4 lg:grid-cols-1'}>
                <StatBlock icon={faWifi} title={t('details.address')} copyOnClick={allocation || undefined}>
                    {allocation || t('details.not_available')}
                </StatBlock>
                <StatBlock
                    icon={faClock}
                    title={t('details.uptime')}
                    color={getBackgroundColor(status === 'running' ? 0 : status !== 'offline' ? 9 : 10, 10)}
                >
                    {status === null ? (
                        t('status.offline')
                    ) : stats.uptime > 0 ? (
                        <UptimeDuration uptime={stats.uptime / 1000} />
                    ) : (
                        t(`status.${status}`, capitalize(status))
                    )}
                </StatBlock>
            </div>
        </div>
    );
};

export default ServerDetailsBlock;
