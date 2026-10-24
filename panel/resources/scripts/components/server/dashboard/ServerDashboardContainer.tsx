import React, { useEffect, useState } from 'react';
import { Link, useRouteMatch } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';
import { useTranslation } from 'react-i18next';
import {
    Archive,
    CalendarClock,
    Clock,
    Copy,
    Database,
    ExternalLink,
    Gauge,
    HardDrive,
    KeyRound,
    Lock,
    LockOpen,
    MemoryStick,
    Network,
    Server as ServerIcon,
    Cpu,
} from 'lucide-react';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import { usePermissions } from '@/plugins/usePermissions';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import CopyOnClick from '@/components/elements/CopyOnClick';
import UptimeDuration from '@/components/server/UptimeDuration';
import getServerSchedules, { Schedule } from '@/api/server/schedules/getServerSchedules';
import getServerDatabases from '@/api/server/databases/getServerDatabases';
import getServerBackups from '@/api/swr/getServerBackups';
import { useActivityLogs } from '@/api/server/activity';
import ActivityLogEntry from '@/components/elements/activity/ActivityLogEntry';
import { useCpuValue } from '@/lib/usage';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useLockdownToggle } from '@/components/server/lockdown/Lockdown';
import PlayerStatsCard from '@/components/server/dashboard/PlayerStatsCard';

// Hydra: overview page of a server (addon "Server Dashboard").

interface Stats {
    cpu: number;
    memory: number;
    disk: number;
    rx: number;
    tx: number;
    uptime: number;
}

const Card = ({
    title,
    icon: Icon,
    className,
    children,
}: {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    className?: string;
    children: React.ReactNode;
}) => (
    <section className={`rounded-lg border border-gray-700 bg-gray-800/80 p-4 backdrop-blur ${className || ''}`}>
        <h3 className={'mb-3 flex items-center gap-2 text-sm font-medium uppercase tracking-wide text-gray-300'}>
            <Icon className={'h-4 w-4 text-primary-400'} />
            {title}
        </h3>
        {children}
    </section>
);

const Row = ({ label, value, copy }: { label: string; value: React.ReactNode; copy?: string }) => (
    <div className={'flex items-baseline justify-between gap-3 border-t border-gray-700/60 py-2 first:border-t-0'}>
        <span className={'shrink-0 text-xs text-gray-400'}>{label}</span>
        {copy ? (
            <CopyOnClick text={copy}>
                <span
                    className={
                        'flex min-w-0 cursor-pointer items-center gap-1 truncate font-mono text-sm text-gray-100'
                    }
                >
                    <span className={'truncate'}>{value}</span>
                    <Copy className={'h-3 w-3 shrink-0 text-gray-500'} />
                </span>
            </CopyOnClick>
        ) : (
            <span className={'min-w-0 truncate text-right text-sm text-gray-100'}>{value}</span>
        )}
    </div>
);

const Meter = ({
    icon: Icon,
    label,
    value,
    max,
    text,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: number;
    max: number | null;
    text: string;
}) => {
    const percent = max ? Math.min(100, (value / max) * 100) : null;
    const color =
        percent === null
            ? 'bg-primary-500'
            : percent > 90
            ? 'bg-red-500'
            : percent > 75
            ? 'bg-yellow-500'
            : 'bg-primary-500';

    return (
        <div>
            <div className={'mb-1 flex items-center justify-between text-xs'}>
                <span className={'flex items-center gap-1 text-gray-300'}>
                    <Icon className={'h-3.5 w-3.5'} />
                    {label}
                </span>
                <span className={'font-mono text-gray-200'}>{text}</span>
            </div>
            <div className={'h-1.5 overflow-hidden rounded bg-gray-700'}>
                <div className={`h-full ${color} transition-all duration-500`} style={{ width: `${percent ?? 0}%` }} />
            </div>
        </div>
    );
};

// Hydra: backups are limited by their space.
const BackupStorage = ({ limit }: { limit: number }) => {
    const { data } = getServerBackups();

    return <>{`${data ? bytesToString(data.storageUsed) : '–'} / ${bytesToString(limit * 1024 * 1024)}`}</>;
};

const RecentActivity = () => {
    const { t } = useTranslation('client/server');
    const { data } = useActivityLogs({ page: 1, sorts: { timestamp: -1 } }, { revalidateOnMount: true });
    const items = (data?.items || []).slice(0, 5);

    if (!data) return <p className={'text-sm text-gray-400'}>…</p>;
    if (items.length === 0) return <p className={'text-sm text-gray-400'}>{t('dashboard.no_activity')}</p>;

    return (
        <div className={'-mx-2'}>
            {items.map((activity) => (
                <ActivityLogEntry key={activity.id} activity={activity} />
            ))}
        </div>
    );
};

const countdown = (ms: number): string => {
    const total = Math.max(0, Math.floor(ms / 1000));
    const days = Math.floor(total / 86400);
    const pad = (value: number) => String(value).padStart(2, '0');
    const time = `${pad(Math.floor((total % 86400) / 3600))}:${pad(Math.floor((total % 3600) / 60))}:${pad(
        total % 60
    )}`;

    return days > 0 ? `${days}d ${time}` : time;
};

/**
 * Hydra: lockdown of the server, which only the owner and admins may start
 * or lift (addon "Server Lockdown").
 */
const LockdownCard = () => {
    const { t } = useTranslation('client/server');
    const lockdown = useLockdownToggle('server:dashboard');

    if (!lockdown.canToggle) return null;

    const Icon = lockdown.active ? LockOpen : Lock;

    return (
        <Card title={t('lockdown.title')} icon={Lock}>
            {lockdown.dialog}
            <FlashMessageRender byKey={'server:dashboard'} className={'mb-3'} />
            <p className={'text-sm text-gray-300'}>
                {lockdown.active && lockdown.since
                    ? t('lockdown.active', { date: formatDate(lockdown.since) })
                    : t('lockdown.text')}
            </p>
            {lockdown.suspended && <p className={'mt-2 text-xs text-gray-400'}>{t('lockdown.suspended')}</p>}
            <button
                type={'button'}
                onClick={lockdown.request}
                disabled={lockdown.disabled}
                className={`mt-3 flex w-full items-center justify-center gap-2 rounded px-3 py-2 text-sm font-medium text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50 ${
                    lockdown.active ? 'bg-primary-500 hover:bg-primary-400' : 'bg-red-600 hover:bg-red-500'
                }`}
            >
                <Icon className={'h-4 w-4 shrink-0'} />
                {t(lockdown.active ? 'lockdown.disable' : 'lockdown.enable')}
            </button>
        </Card>
    );
};

/**
 * Active schedules by their next run, with a countdown that ticks every second.
 */
const UpcomingSchedules = ({ link }: { link: (path: string) => string }) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [schedules, setSchedules] = useState<Schedule[] | null>(null);
    const [now, setNow] = useState(Date.now());

    const load = () =>
        getServerSchedules(uuid)
            .then(setSchedules)
            .catch(() => setSchedules([]));

    useEffect(() => {
        load();
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    const upcoming = (schedules || [])
        .filter((schedule) => schedule.isActive && schedule.nextRunAt)
        .sort((a, b) => a.nextRunAt!.getTime() - b.nextRunAt!.getTime());
    const inactive = (schedules || []).filter((schedule) => !schedule.isActive).length;

    // A schedule that just ran has a new next run: reload a few seconds later.
    const due = upcoming.some((schedule) => schedule.nextRunAt!.getTime() <= now - 5000);
    useEffect(() => {
        if (due) load();
    }, [due]);

    if (!schedules) return <p className={'text-sm text-gray-400'}>…</p>;

    return (
        <>
            {upcoming.length === 0 ? (
                <p className={'text-sm text-gray-400'}>{t('dashboard.no_schedules')}</p>
            ) : (
                <ul className={'divide-y divide-gray-700/60'}>
                    {upcoming.slice(0, 6).map((schedule) => {
                        const left = schedule.nextRunAt!.getTime() - now;

                        return (
                            <li key={schedule.id}>
                                <Link
                                    to={link(`/schedules/${schedule.id}`)}
                                    className={
                                        '-mx-2 flex items-center justify-between gap-3 rounded px-2 py-2 hover:bg-gray-700/50'
                                    }
                                >
                                    <div className={'min-w-0'}>
                                        <p className={'truncate text-sm text-gray-100'}>{schedule.name}</p>
                                        <p className={'text-xs text-gray-400'}>
                                            {formatDate(schedule.nextRunAt!)} ·{' '}
                                            {t('dashboard.schedule_tasks', { count: schedule.tasks.length })}
                                        </p>
                                    </div>
                                    {schedule.isProcessing || left <= 0 ? (
                                        <span
                                            className={
                                                'shrink-0 rounded bg-yellow-500/20 px-2 py-1 text-xs text-yellow-300'
                                            }
                                        >
                                            {t('dashboard.schedule_running')}
                                        </span>
                                    ) : (
                                        <span
                                            className={`shrink-0 rounded px-2 py-1 font-mono text-sm ${
                                                left < 5 * 60 * 1000
                                                    ? 'bg-primary-500/20 text-primary-300'
                                                    : 'bg-gray-700 text-gray-100'
                                            }`}
                                        >
                                            {countdown(left)}
                                        </span>
                                    )}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
            <div className={'mt-2 flex items-center justify-between text-xs'}>
                <span className={'text-gray-500'}>
                    {inactive > 0 && t('dashboard.schedules_inactive', { count: inactive })}
                </span>
                <Link to={link('/schedules')} className={'text-sm text-primary-400 hover:text-primary-300'}>
                    {t('dashboard.all_schedules')}
                </Link>
            </div>
        </>
    );
};

export default () => {
    const { t } = useTranslation('client/server');
    const match = useRouteMatch();
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const username = useStoreState((state) => state.user.data!.username);
    const [canSftp, canDatabases, canBackups, canActivity, canSchedules] = usePermissions([
        'file.sftp',
        'database.read',
        'backup.read',
        'activity.read',
        'schedule.read',
    ]);
    const [stats, setStats] = useState<Stats>({ cpu: 0, memory: 0, disk: 0, rx: 0, tx: 0, uptime: 0 });
    const cpuValue = useCpuValue();
    const [databaseCount, setDatabaseCount] = useState<number | null>(null);

    useEffect(() => {
        if (connected && instance) instance.send(SocketRequest.SEND_STATS);
    }, [connected, instance]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        try {
            const value = JSON.parse(data);
            setStats({
                cpu: value.cpu_absolute,
                memory: value.memory_bytes,
                disk: value.disk_bytes,
                rx: value.network.rx_bytes,
                tx: value.network.tx_bytes,
                uptime: value.uptime || 0,
            });
        } catch (e) {
            // Ignore broken frames.
        }
    });

    useEffect(() => {
        if (!canDatabases || server.featureLimits.databases === 0) return;
        getServerDatabases(server.uuid, false)
            .then((list) => setDatabaseCount(list.length))
            .catch(() => setDatabaseCount(null));
    }, [canDatabases]);

    const base = match.url.replace(/\/dashboard\/?$/, '');
    const link = (path: string) => (path === '/' ? base : `${base}${path}`);

    const allocation = server.allocations.find((item) => item.isDefault);
    const address = allocation ? `${allocation.alias || ip(allocation.ip)}:${allocation.port}` : null;
    const sftpUser = `${username}.${server.id}`;
    const sftpHost = ip(server.sftpDetails.ip);
    const sftpUrl = `sftp://${sftpUser}@${sftpHost}:${server.sftpDetails.port}`;

    const statusColor =
        status === 'running'
            ? 'bg-green-500'
            : status === 'starting' || status === 'stopping'
            ? 'bg-yellow-500'
            : 'bg-red-500';
    const running = status === 'running' || status === 'starting';
    const limitText = (bytes: number, limitMb: number) =>
        `${bytesToString(bytes)} / ${limitMb ? bytesToString(mbToBytes(limitMb)) : '∞'}`;

    return (
        <ServerContentBlock title={t('dashboard.title')}>
            {/* Banner of the egg */}
            <div className={'relative mb-4 h-44 overflow-hidden rounded-lg border border-gray-700 sm:h-56'}>
                {server.banner ? (
                    <img src={server.banner} alt={''} className={'absolute inset-0 h-full w-full object-cover'} />
                ) : (
                    <div className={'absolute inset-0 bg-gradient-to-br from-primary-700 via-gray-800 to-gray-900'} />
                )}
                <div className={'absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent'} />
                <div
                    className={
                        'absolute bottom-0 left-0 right-0 flex flex-wrap items-end justify-between gap-3 p-4 sm:p-6'
                    }
                >
                    <div className={'min-w-0'}>
                        <p className={'text-xs uppercase tracking-wider text-gray-300'}>{server.eggName}</p>
                        <h2 className={'truncate text-2xl font-semibold text-white sm:text-3xl'}>{server.name}</h2>
                        {server.description && (
                            <p className={'mt-1 line-clamp-2 max-w-2xl text-sm text-gray-200'}>{server.description}</p>
                        )}
                    </div>
                    <span
                        className={'flex items-center gap-2 rounded-full bg-black/50 px-3 py-1 text-sm text-gray-100'}
                    >
                        <span className={`h-2.5 w-2.5 rounded-full ${statusColor}`} />
                        {status ? t(`status.${status}`) : t('status.offline')}
                    </span>
                </div>
            </div>

            <div className={'grid grid-cols-1 gap-4 lg:grid-cols-3'}>
                {/* SFTP */}
                {canSftp && (
                    <Card title={t('dashboard.sftp')} icon={KeyRound}>
                        <Row
                            label={t('settings.sftp_address')}
                            value={`${sftpHost}:${server.sftpDetails.port}`}
                            copy={`${sftpHost}:${server.sftpDetails.port}`}
                        />
                        <Row label={t('settings.sftp_username')} value={sftpUser} copy={sftpUser} />
                        <p className={'mt-2 text-xs text-gray-400'}>{t('settings.sftp_password')}</p>
                        <a
                            href={sftpUrl}
                            className={
                                'mt-3 flex items-center justify-center gap-2 rounded bg-primary-500 px-3 py-2 text-sm font-medium text-white hover:bg-primary-400'
                            }
                        >
                            <ExternalLink className={'h-4 w-4'} />
                            {t('dashboard.sftp_connect')}
                        </a>
                    </Card>
                )}

                {/* Server details */}
                <Card title={t('dashboard.server')} icon={ServerIcon}>
                    <Row label={t('dashboard.uuid')} value={server.uuid} copy={server.uuid} />
                    <Row label={t('settings.server_id')} value={server.id} copy={server.id} />
                    <Row
                        label={t('details.uptime')}
                        value={
                            running && stats.uptime > 0 ? (
                                <UptimeDuration uptime={stats.uptime / 1000} />
                            ) : (
                                <span className={'text-gray-400'}>{t('status.offline')}</span>
                            )
                        }
                    />
                    <Row
                        label={t('details.address')}
                        value={address || t('details.not_available')}
                        copy={address || undefined}
                    />
                    <Row
                        label={t('settings.node')}
                        value={server.location ? `${server.node} (${server.location})` : server.node}
                    />
                    {server.createdAt && (
                        <Row label={t('dashboard.created')} value={formatDate(server.createdAt, 'date')} />
                    )}
                </Card>

                {/* Live resources */}
                <Card title={t('dashboard.resources')} icon={Gauge}>
                    <div className={'space-y-3'}>
                        <Meter
                            icon={Cpu}
                            label={t('details.cpu')}
                            value={running ? stats.cpu : 0}
                            max={server.limits.cpu || null}
                            text={`${cpuValue(running ? stats.cpu : 0, server.limits.cpu).toFixed(1)} % / ${
                                server.limits.cpu ? `${cpuValue(server.limits.cpu, server.limits.cpu)} %` : '∞'
                            }`}
                        />
                        <Meter
                            icon={MemoryStick}
                            label={t('details.memory')}
                            value={running ? stats.memory : 0}
                            max={server.limits.memory ? mbToBytes(server.limits.memory) : null}
                            text={limitText(running ? stats.memory : 0, server.limits.memory)}
                        />
                        <Meter
                            icon={HardDrive}
                            label={t('details.disk')}
                            value={stats.disk}
                            max={server.limits.disk ? mbToBytes(server.limits.disk) : null}
                            text={limitText(stats.disk, server.limits.disk)}
                        />
                        <div className={'flex justify-between border-t border-gray-700/60 pt-2 text-xs text-gray-300'}>
                            <span>
                                {t('details.inbound')}: <span className={'font-mono'}>{bytesToString(stats.rx)}</span>
                            </span>
                            <span>
                                {t('details.outbound')}: <span className={'font-mono'}>{bytesToString(stats.tx)}</span>
                            </span>
                        </div>
                    </div>
                </Card>

                {/* Upcoming schedules */}
                {canSchedules && (
                    <Card title={t('dashboard.schedules')} icon={CalendarClock} className={'lg:col-span-2'}>
                        <UpcomingSchedules link={link} />
                    </Card>
                )}

                {/* Lockdown and limits, next to the schedules */}
                <div className={'flex flex-col gap-4'}>
                    <LockdownCard />
                    <Card title={t('dashboard.limits')} icon={Network}>
                        <Row
                            label={t('dashboard.allocations')}
                            value={`${server.allocations.length} / ${
                                server.featureLimits.allocations || server.allocations.length
                            }`}
                        />
                        <Row
                            label={t('dashboard.databases')}
                            value={
                                <span className={'inline-flex items-center gap-1'}>
                                    <Database className={'h-3.5 w-3.5 text-gray-400'} />
                                    {databaseCount !== null
                                        ? `${databaseCount} / ${server.featureLimits.databases}`
                                        : `– / ${server.featureLimits.databases}`}
                                </span>
                            }
                        />
                        <Row
                            label={t('dashboard.backups')}
                            value={
                                <span className={'inline-flex items-center gap-1'}>
                                    <Archive className={'h-3.5 w-3.5 text-gray-400'} />
                                    {canBackups && server.featureLimits.backup_storage > 0 ? (
                                        <BackupStorage limit={server.featureLimits.backup_storage} />
                                    ) : (
                                        `– / ${bytesToString(server.featureLimits.backup_storage * 1024 * 1024)}`
                                    )}
                                </span>
                            }
                        />
                        <Row
                            label={t('dashboard.image')}
                            value={<span className={'font-mono text-xs'}>{server.dockerImage}</span>}
                        />
                    </Card>
                </div>

                {/* Players online (addon "player_stats") */}
                {server.playerStats && <PlayerStatsCard className={'lg:col-span-3'} />}

                {/* Recent activity */}
                {canActivity && (
                    <Card title={t('dashboard.activity')} icon={Clock} className={'lg:col-span-3'}>
                        <RecentActivity />
                        <Link
                            to={link('/activity')}
                            className={'mt-2 inline-block text-sm text-primary-400 hover:text-primary-300'}
                        >
                            {t('dashboard.all_activity')}
                        </Link>
                    </Card>
                )}
            </div>
        </ServerContentBlock>
    );
};
