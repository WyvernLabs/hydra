import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Globe, Trash2, Waypoints } from 'lucide-react';
import { ServerContext } from '@/state/server';
import { useFlashKey } from '@/plugins/useFlash';
import Can from '@/components/elements/Can';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import CopyOnClick from '@/components/elements/CopyOnClick';
import { Allocation } from '@/api/server/getServer';
import {
    addDedicatedPort,
    createProxy,
    createSubdomain,
    deleteProxy,
    deleteSubdomain,
    NetworkExtras,
} from '@/api/server/network/hydra';

// Hydra: free ports on a dedicated node, subdomains and reverse proxies in the
// network tab.

const Card = ({
    icon: Icon,
    title,
    usage,
    children,
}: {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    usage?: string;
    children: React.ReactNode;
}) => (
    <div className={'mt-6 rounded-lg border border-gray-700 bg-gray-800 p-4'}>
        <div className={'mb-3 flex items-center gap-2'}>
            <Icon className={'h-4 w-4 text-accent-400'} />
            <h3 className={'flex-1 font-header text-base text-gray-50'}>{title}</h3>
            {usage && <span className={'text-xs text-gray-400'}>{usage}</span>}
        </div>
        {children}
    </div>
);

const portLabel = (allocations: Allocation[], id: number | null): string => {
    const a = allocations.find((x) => x.id === id);
    return a ? `${a.alias || a.ip}:${a.port}` : '—';
};

interface Props {
    extras: NetworkExtras;
    allocations: Allocation[];
    onChange: () => void;
    onAllocationAdded: (allocation: Allocation) => void;
}

export const DedicatedPortForm = ({ extras, onAllocationAdded }: Pick<Props, 'extras' | 'onAllocationAdded'>) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const [port, setPort] = useState('');
    const [busy, setBusy] = useState(false);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes();
        setBusy(true);
        addDedicatedPort(uuid, Number(port))
            .then((allocation) => {
                setPort('');
                onAllocationAdded(allocation);
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    return (
        <Can action={'allocation.create'}>
            <form onSubmit={submit} className={'mt-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-end'}>
                <div className={'sm:w-48'}>
                    <Label>{t('network.dedicated.port')}</Label>
                    <Input
                        type={'number'}
                        min={extras.dedicated.minPort}
                        max={65535}
                        value={port}
                        onChange={(e) => setPort(e.currentTarget.value)}
                        placeholder={'25565'}
                        required
                    />
                </div>
                <Button type={'submit'} disabled={busy || !port}>
                    {t('network.dedicated.add')}
                </Button>
            </form>
            <p className={'mt-2 text-right text-xs text-gray-400'}>
                {t('network.dedicated.help', {
                    min: extras.dedicated.minPort,
                    reserved: extras.dedicated.reserved.join(', '),
                })}
            </p>
        </Can>
    );
};

export const SubdomainsCard = ({ extras, allocations, onChange }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const { limit, domains, services, items } = extras.subdomains;
    const [name, setName] = useState('');
    const [domain, setDomain] = useState(domains[0]?.id || 0);
    const [allocation, setAllocation] = useState<number>(allocations.find((a) => a.isDefault)?.id || 0);
    const [srv, setSrv] = useState(services[0] || '');
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<number | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes();
        setBusy(true);
        createSubdomain(uuid, { domain, name, allocation: allocation || null, srv: allocation && srv ? srv : null })
            .then(() => {
                setName('');
                onChange();
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    const remove = (id: number) => {
        clearFlashes();
        deleteSubdomain(uuid, id)
            .then(() => onChange())
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setRemoving(null));
    };

    return (
        <Card
            icon={Globe}
            title={t('network.subdomains.title')}
            usage={t('network.usage_short', { count: items.length, limit })}
        >
            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={t('network.subdomains.delete_title')}
                confirm={t('network.delete')}
                onConfirmed={() => removing !== null && remove(removing)}
            >
                {t('network.subdomains.delete_text')}
            </Dialog.Confirm>
            {items.length === 0 ? (
                <p className={'text-sm text-gray-400'}>{t('network.subdomains.none')}</p>
            ) : (
                <div className={'space-y-2'}>
                    {items.map((item) => (
                        <div key={item.id} className={'flex items-center gap-3 rounded bg-gray-700 px-3 py-2'}>
                            <CopyOnClick text={item.name}>
                                <code className={'flex-1 truncate text-sm text-gray-50'}>{item.name}</code>
                            </CopyOnClick>
                            <span className={'flex-1 text-xs text-gray-400'}>
                                {item.srv
                                    ? t('network.subdomains.srv_to', {
                                          service: t(`network.subdomains.services.${item.srv}`),
                                          port: portLabel(allocations, item.allocation),
                                      })
                                    : t('network.subdomains.address_only')}
                            </span>
                            <Can action={'allocation.delete'}>
                                <button
                                    type={'button'}
                                    className={'p-1 text-gray-400 hover:text-red-400'}
                                    title={t('network.delete')}
                                    onClick={() => setRemoving(item.id)}
                                >
                                    <Trash2 className={'h-4 w-4'} />
                                </button>
                            </Can>
                        </div>
                    ))}
                </div>
            )}
            {items.length < limit && domains.length > 0 && (
                <Can action={'allocation.create'}>
                    <form onSubmit={submit} className={'mt-4 grid grid-cols-1 gap-3 md:grid-cols-4 md:items-end'}>
                        <div className={'md:col-span-2'}>
                            <Label>{t('network.subdomains.name')}</Label>
                            <div className={'flex items-center gap-2'}>
                                <Input
                                    value={name}
                                    onChange={(e) => setName(e.currentTarget.value.toLowerCase())}
                                    placeholder={'play'}
                                    maxLength={63}
                                    required
                                />
                                <Select
                                    value={domain}
                                    onChange={(e) => setDomain(Number(e.currentTarget.value))}
                                    className={'!w-auto'}
                                >
                                    {domains.map((d) => (
                                        <option key={d.id} value={d.id}>
                                            .{d.name}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>{t('network.subdomains.port')}</Label>
                            <Select value={allocation} onChange={(e) => setAllocation(Number(e.currentTarget.value))}>
                                <option value={0}>{t('network.subdomains.no_port')}</option>
                                {allocations.map((a) => (
                                    <option key={a.id} value={a.id}>
                                        {portLabel(allocations, a.id)}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label>{t('network.subdomains.srv')}</Label>
                            <Select value={srv} onChange={(e) => setSrv(e.currentTarget.value)} disabled={!allocation}>
                                <option value={''}>{t('network.subdomains.services.none')}</option>
                                {services.map((s) => (
                                    <option key={s} value={s}>
                                        {t(`network.subdomains.services.${s}`)}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div className={'md:col-span-4 flex justify-end'}>
                            <Button type={'submit'} disabled={busy || !name}>
                                {busy ? t('network.working') : t('network.subdomains.create')}
                            </Button>
                        </div>
                    </form>
                </Can>
            )}
            <p className={'mt-3 text-xs text-gray-400'}>{t('network.subdomains.help')}</p>
        </Card>
    );
};

export const ProxiesCard = ({ extras, allocations, onChange }: Props) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('server:network');
    const { limit, target, items } = extras.proxies;
    const managedDomains = extras.subdomains.enabled ? extras.subdomains.domains : [];
    const [type, setType] = useState<'own' | 'subdomain'>(managedDomains.length ? 'subdomain' : 'own');
    const [domain, setDomain] = useState('');
    const [domainId, setDomainId] = useState(managedDomains[0]?.id || 0);
    const [name, setName] = useState('');
    const [allocation, setAllocation] = useState<number>(allocations[0]?.id || 0);
    const [busy, setBusy] = useState(false);
    const [removing, setRemoving] = useState<number | null>(null);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes();
        setBusy(true);
        createProxy(uuid, { allocation, type, domain, domainId, name })
            .then(() => {
                setDomain('');
                setName('');
                onChange();
            })
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setBusy(false));
    };

    const remove = (id: number) => {
        clearFlashes();
        deleteProxy(uuid, id)
            .then(() => onChange())
            .catch((error) => clearAndAddHttpError(error))
            .then(() => setRemoving(null));
    };

    return (
        <Card
            icon={Waypoints}
            title={t('network.proxies.title')}
            usage={t('network.usage_short', { count: items.length, limit })}
        >
            <Dialog.Confirm
                open={removing !== null}
                onClose={() => setRemoving(null)}
                title={t('network.proxies.delete_title')}
                confirm={t('network.delete')}
                onConfirmed={() => removing !== null && remove(removing)}
            >
                {t('network.proxies.delete_text')}
            </Dialog.Confirm>
            {items.length === 0 ? (
                <p className={'text-sm text-gray-400'}>{t('network.proxies.none')}</p>
            ) : (
                <div className={'space-y-2'}>
                    {items.map((item) => (
                        <div key={item.id} className={'flex items-center gap-3 rounded bg-gray-700 px-3 py-2'}>
                            <a
                                href={`https://${item.domain}`}
                                target={'_blank'}
                                rel={'noreferrer'}
                                className={'flex-1 truncate text-sm text-accent-300 hover:underline'}
                            >
                                https://{item.domain}
                            </a>
                            <span className={'text-xs text-gray-400'}>
                                → {t('network.proxies.port', { port: item.port })}
                            </span>
                            <Can action={'allocation.delete'}>
                                <button
                                    type={'button'}
                                    className={'p-1 text-gray-400 hover:text-red-400'}
                                    title={t('network.delete')}
                                    onClick={() => setRemoving(item.id)}
                                >
                                    <Trash2 className={'h-4 w-4'} />
                                </button>
                            </Can>
                        </div>
                    ))}
                </div>
            )}
            {items.length < limit && allocations.length > 0 && (
                <Can action={'allocation.create'}>
                    <form onSubmit={submit} className={'mt-4 space-y-3'}>
                        {managedDomains.length > 0 && (
                            <div className={'flex flex-wrap gap-4 text-sm text-gray-200'}>
                                <label className={'flex items-center gap-2'}>
                                    <input
                                        type={'radio'}
                                        checked={type === 'subdomain'}
                                        onChange={() => setType('subdomain')}
                                    />
                                    {t('network.proxies.type_subdomain')}
                                </label>
                                <label className={'flex items-center gap-2'}>
                                    <input type={'radio'} checked={type === 'own'} onChange={() => setType('own')} />
                                    {t('network.proxies.type_own')}
                                </label>
                            </div>
                        )}
                        <div className={'grid grid-cols-1 gap-3 md:grid-cols-3 md:items-end'}>
                            <div className={'md:col-span-2'}>
                                <Label>{t('network.proxies.domain')}</Label>
                                {type === 'own' ? (
                                    <Input
                                        value={domain}
                                        onChange={(e) => setDomain(e.currentTarget.value.toLowerCase())}
                                        placeholder={'map.example.com'}
                                        required
                                    />
                                ) : (
                                    <div className={'flex items-center gap-2'}>
                                        <Input
                                            value={name}
                                            onChange={(e) => setName(e.currentTarget.value.toLowerCase())}
                                            placeholder={'map'}
                                            maxLength={63}
                                            required
                                        />
                                        <Select
                                            value={domainId}
                                            onChange={(e) => setDomainId(Number(e.currentTarget.value))}
                                            className={'!w-auto'}
                                        >
                                            {managedDomains.map((d) => (
                                                <option key={d.id} value={d.id}>
                                                    .{d.name}
                                                </option>
                                            ))}
                                        </Select>
                                    </div>
                                )}
                            </div>
                            <div>
                                <Label>{t('network.proxies.target_port')}</Label>
                                <Select
                                    value={allocation}
                                    onChange={(e) => setAllocation(Number(e.currentTarget.value))}
                                >
                                    {allocations.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {portLabel(allocations, a.id)}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                        </div>
                        {type === 'own' && (
                            <p className={'text-xs text-gray-400'}>{t('network.proxies.own_help', { target })}</p>
                        )}
                        <div className={'flex justify-end'}>
                            <Button type={'submit'} disabled={busy || (type === 'own' ? !domain : !name)}>
                                {busy ? t('network.working') : t('network.proxies.create')}
                            </Button>
                        </div>
                    </form>
                </Can>
            )}
            <p className={'mt-3 text-xs text-gray-400'}>{t('network.proxies.help')}</p>
        </Card>
    );
};
