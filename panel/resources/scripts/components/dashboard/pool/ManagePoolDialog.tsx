import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
    Archive,
    Cpu,
    Database,
    ExternalLink,
    Globe,
    HardDrive,
    MemoryStick,
    Network,
    Pencil,
    Plus,
    Server as ServerIcon,
    Trash2,
    Waypoints,
} from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { POOL_RESOURCES, PoolResource, PoolServer, ResourcePool } from '@/api/account/pool';
import { formatPool } from '@/lib/pool';
import PoolServerDialog from '@/components/dashboard/pool/PoolServerDialog';
import DeletePoolServerDialog from '@/components/dashboard/pool/DeletePoolServerDialog';

// Hydra: the resource pool in a window of its own (resource_pool addon): what
// is still free on the left, the pool servers on the right.

type IconComponent = React.ComponentType<{ className?: string; style?: React.CSSProperties }>;

export const POOL_ICONS: Record<PoolResource, { icon: IconComponent; color: string }> = {
    cpu: { icon: Cpu, color: '#60a5fa' },
    memory: { icon: MemoryStick, color: '#34d399' },
    disk: { icon: HardDrive, color: '#fbbf24' },
    databases: { icon: Database, color: '#a78bfa' },
    ports: { icon: Network, color: '#f87171' },
    backups: { icon: Archive, color: '#22d3ee' },
    subdomains: { icon: Globe, color: '#2dd4bf' },
    proxies: { icon: Waypoints, color: '#f472b6' },
};

interface Props {
    pool: ResourcePool;
    open: boolean;
    onClose: () => void;
    onChanged: () => void;
}

const Tile = ({
    icon: Icon,
    color,
    label,
    value,
    hint,
}: {
    icon: IconComponent;
    color: string;
    label: string;
    value: string;
    hint?: string;
}) => (
    <div className={'flex items-center gap-3 rounded-lg border border-gray-700/60 bg-gray-800 p-3'}>
        <span className={'flex h-9 w-9 flex-none items-center justify-center rounded-md bg-gray-900'}>
            <Icon className={'h-4 w-4'} style={{ color }} />
        </span>
        <div className={'min-w-0'}>
            <p className={'text-2xs uppercase leading-tight tracking-wide text-gray-400 [overflow-wrap:anywhere]'}>
                {label}
            </p>
            <p className={'truncate text-base font-semibold text-gray-50'}>{value}</p>
            {hint && <p className={'truncate text-2xs text-gray-400'}>{hint}</p>}
        </div>
    </div>
);

export default ({ pool, open, onClose, onChanged }: Props) => {
    const { t } = useTranslation('client/pool');
    const [creating, setCreating] = useState(false);
    // The server stays set while a dialog closes, so its content does not jump.
    const [editing, setEditing] = useState<PoolServer | null>(null);
    const [editOpen, setEditOpen] = useState(false);
    const [deleting, setDeleting] = useState<PoolServer | null>(null);
    const [deleteOpen, setDeleteOpen] = useState(false);

    const shown = POOL_RESOURCES.filter((key) => {
        if (key === 'subdomains' && !pool.features.subdomains) return false;
        if (key === 'proxies' && !pool.features.proxies) return false;
        return pool.limits[key] > 0 || pool.used[key] > 0;
    });
    const serverLimit = pool.limits.servers;
    const serversLeft = serverLimit === null ? Infinity : serverLimit - pool.used.servers;
    const canCreate = serversLeft > 0 && pool.eggs.length > 0 && pool.locations.length > 0;

    const createButton = (
        <Button onClick={() => setCreating(true)} disabled={!canCreate}>
            <Plus className={'mr-1 h-4 w-4'} />
            {t('create')}
        </Button>
    );

    return (
        <>
            {/* Hidden while one of the dialogs below is open, it comes back after. */}
            <Dialog
                open={open && !creating && !editOpen && !deleteOpen}
                onClose={onClose}
                huge
                title={t('manage_title')}
            >
                <div className={'mt-2 grid grid-cols-1 gap-4 lg:grid-cols-[24rem_1fr]'}>
                    <div className={'space-y-4'}>
                        <section className={'rounded-lg border-l-4 border-blue-400 bg-gray-900/60 p-4'}>
                            <h3 className={'mb-3 text-xs font-semibold uppercase tracking-wide text-gray-300'}>
                                {t('available')}
                            </h3>
                            <div className={'grid grid-cols-2 gap-3'}>
                                {shown.map((key) => (
                                    <Tile
                                        key={key}
                                        icon={POOL_ICONS[key].icon}
                                        color={POOL_ICONS[key].color}
                                        label={t(`resources.${key}`)}
                                        value={formatPool(key, Math.max(0, pool.limits[key] - pool.used[key]), t)}
                                        hint={t('of', { value: formatPool(key, pool.limits[key], t) })}
                                    />
                                ))}
                            </div>
                        </section>
                        <section className={'rounded-lg border-l-4 border-blue-400 bg-gray-900/60 p-4'}>
                            <h3 className={'mb-3 text-xs font-semibold uppercase tracking-wide text-gray-300'}>
                                {t('servers_title')}
                            </h3>
                            <div className={'grid grid-cols-2 gap-3'}>
                                <Tile
                                    icon={ServerIcon}
                                    color={'#60a5fa'}
                                    label={t('servers_total')}
                                    value={String(pool.used.servers)}
                                />
                                <Tile
                                    icon={Plus}
                                    color={'#34d399'}
                                    label={t('servers_free')}
                                    value={serverLimit === null ? '∞' : String(Math.max(0, serversLeft))}
                                />
                            </div>
                        </section>
                    </div>

                    <div className={'min-w-0 space-y-3'}>
                        {pool.servers.length > 0 && (
                            <div className={'flex items-center justify-between gap-3'}>
                                <p className={'text-sm text-gray-400'}>
                                    {!canCreate &&
                                        (serversLeft <= 0 ? t('server_limit_reached') : t('nothing_to_pick'))}
                                </p>
                                {createButton}
                            </div>
                        )}
                        {pool.servers.map((server) => (
                            <div
                                key={server.uuid}
                                className={
                                    'flex items-center gap-4 rounded-lg border-l-4 border-green-500 bg-gray-900/60 p-4'
                                }
                            >
                                <ServerIcon className={'h-10 w-10 flex-none text-gray-400'} />
                                <div className={'min-w-0 flex-1'}>
                                    <div className={'flex flex-wrap items-center gap-2'}>
                                        <span className={'truncate font-semibold text-gray-50'}>{server.name}</span>
                                        {server.egg && (
                                            <span className={'rounded bg-gray-700 px-2 py-0.5 text-2xs text-gray-300'}>
                                                {server.egg}
                                            </span>
                                        )}
                                        {server.status === 'suspended' && (
                                            <span className={'rounded bg-red-500/20 px-2 py-0.5 text-2xs text-red-300'}>
                                                {t('suspended')}
                                            </span>
                                        )}
                                    </div>
                                    <div className={'mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-400'}>
                                        {shown.map((key) => {
                                            const Icon = POOL_ICONS[key].icon;
                                            return (
                                                <span key={key} className={'inline-flex items-center gap-1'}>
                                                    <Icon
                                                        className={'h-3.5 w-3.5'}
                                                        style={{ color: POOL_ICONS[key].color }}
                                                    />
                                                    {t(`resources.${key}`)}:
                                                    <strong className={'font-semibold text-gray-200'}>
                                                        {formatPool(key, server.resources[key], t)}
                                                    </strong>
                                                </span>
                                            );
                                        })}
                                    </div>
                                </div>
                                <div className={'flex flex-none items-center gap-1'}>
                                    <Link
                                        to={`/server/${server.identifier}`}
                                        className={'hydra-icon-btn'}
                                        title={t('open')}
                                        aria-label={t('open')}
                                        onClick={onClose}
                                    >
                                        <ExternalLink className={'h-4 w-4'} />
                                    </Link>
                                    {pool.canEdit && (
                                        <button
                                            type={'button'}
                                            className={'hydra-icon-btn'}
                                            onClick={() => {
                                                setEditing(server);
                                                setEditOpen(true);
                                            }}
                                            disabled={server.status === 'suspended'}
                                            title={t('edit')}
                                            aria-label={t('edit')}
                                        >
                                            <Pencil className={'h-4 w-4'} />
                                        </button>
                                    )}
                                    {pool.canDelete && (
                                        <button
                                            type={'button'}
                                            className={'hydra-icon-btn hover:text-red-400'}
                                            onClick={() => {
                                                setDeleting(server);
                                                setDeleteOpen(true);
                                            }}
                                            disabled={server.status === 'suspended'}
                                            title={t('delete')}
                                            aria-label={t('delete')}
                                        >
                                            <Trash2 className={'h-4 w-4'} />
                                        </button>
                                    )}
                                </div>
                            </div>
                        ))}
                        {pool.servers.length === 0 && (
                            <div
                                className={
                                    'flex flex-col items-center rounded-lg border-l-4 border-green-500 bg-gray-900/60 px-6 py-12 text-center'
                                }
                            >
                                <Plus className={'mb-3 h-10 w-10 text-gray-400'} />
                                <h3 className={'text-lg font-semibold text-gray-200'}>{t('empty_title')}</h3>
                                <p className={'mb-5 mt-1 text-sm text-gray-400'}>
                                    {canCreate ? t('empty_text') : t('nothing_to_pick')}
                                </p>
                                {createButton}
                            </div>
                        )}
                    </div>
                </div>
            </Dialog>

            <PoolServerDialog
                pool={pool}
                server={null}
                open={creating}
                onClose={() => setCreating(false)}
                onDone={() => {
                    setCreating(false);
                    onChanged();
                }}
            />
            <PoolServerDialog
                pool={pool}
                server={editing}
                open={editOpen && !!editing}
                onClose={() => setEditOpen(false)}
                onDone={() => {
                    setEditOpen(false);
                    onChanged();
                }}
            />
            <DeletePoolServerDialog
                server={deleting}
                open={deleteOpen && !!deleting}
                onClose={() => setDeleteOpen(false)}
                onDeleted={() => {
                    setDeleteOpen(false);
                    onChanged();
                }}
            />
        </>
    );
};
