import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import FlashMessageRender from '@/components/FlashMessageRender';
import { useFlashKey } from '@/plugins/useFlash';
import {
    createPoolServer,
    POOL_RESOURCES,
    PoolResource,
    PoolResources,
    PoolServer,
    ResourcePool,
    updatePoolServer,
} from '@/api/account/pool';
import { floorToStep, formatPool, fromShown, POOL_UNITS, toShown } from '@/lib/pool';

// Hydra: creates a server from the resource pool or changes the resources of
// one (resource_pool addon).

interface Props {
    pool: ResourcePool;
    // The server to change, none to create a new one.
    server: PoolServer | null;
    open: boolean;
    onClose: () => void;
    onDone: () => void;
}

// What a new server gets unless less is free: one core, 1 GB memory, 5 GB disk, one port.
const DEFAULTS: Partial<PoolResources> = { cpu: 100, memory: 1024, disk: 5120, ports: 1 };

const REQUIRED: PoolResource[] = ['cpu', 'memory', 'disk', 'ports'];

const ResourceField = ({
    name,
    value,
    min,
    max,
    onChange,
}: {
    name: PoolResource;
    value: number;
    min: number;
    max: number;
    onChange: (value: number) => void;
}) => {
    const { t } = useTranslation('client/pool');
    const unit = POOL_UNITS[name];
    const impossible = max < min;

    return (
        <div>
            <div className={'flex items-baseline justify-between gap-2'}>
                <Label htmlFor={`pool_${name}`}>{t(`resources.${name}`)}</Label>
                <span className={impossible ? 'text-xs text-red-400' : 'text-xs text-gray-400'}>
                    {impossible ? t('not_enough_free') : t('max', { value: formatPool(name, max, t) })}
                </span>
            </div>
            <div className={'flex items-center gap-3'}>
                <input
                    type={'range'}
                    className={'min-w-0 flex-1'}
                    style={{ accentColor: 'rgb(var(--hydra-accent-500))' } as React.CSSProperties}
                    min={toShown(name, min)}
                    max={toShown(name, Math.max(min, max))}
                    step={unit.step}
                    value={toShown(name, value)}
                    disabled={impossible || max === min}
                    onChange={(event) => onChange(fromShown(name, Number(event.currentTarget.value)))}
                    aria-label={t(`resources.${name}`)}
                />
                <div className={'flex w-36 flex-none items-center gap-2'}>
                    <Input
                        id={`pool_${name}`}
                        type={'number'}
                        min={toShown(name, min)}
                        max={toShown(name, Math.max(min, max))}
                        step={unit.step}
                        value={toShown(name, value)}
                        disabled={impossible}
                        onChange={(event) => onChange(fromShown(name, Number(event.currentTarget.value) || 0))}
                    />
                    <span className={'w-10 flex-none text-xs text-gray-400'}>{t(`units.short_${unit.kind}`)}</span>
                </div>
            </div>
        </div>
    );
};

export default ({ pool, server, open, onClose, onDone }: Props) => {
    const { t } = useTranslation('client/pool');
    const { clearFlashes, clearAndAddHttpError } = useFlashKey('pool:dialog');
    const [name, setName] = useState('');
    const [egg, setEgg] = useState<number>(pool.eggs[0]?.id || 0);
    const [location, setLocation] = useState<number | null>(null);
    const [environment, setEnvironment] = useState<Record<string, string>>({});
    const [values, setValues] = useState<PoolResources>({} as PoolResources);
    const [busy, setBusy] = useState(false);

    // Highest value of every resource: what is free plus what the server has.
    const limits = useMemo(() => {
        const result = {} as Record<PoolResource, { min: number; max: number }>;
        POOL_RESOURCES.forEach((key) => {
            const free = Math.max(0, pool.limits[key] - pool.used[key]) + (server?.resources[key] || 0);
            const min = Math.max(
                pool.minimum[key] || 0,
                server ? server.inUse[key as keyof PoolServer['inUse']] || 0 : 0
            );
            // What the server has now stays possible, even off the steps of the slider.
            result[key] = { min, max: Math.max(floorToStep(key, free), server?.resources[key] || 0) };
        });
        return result;
    }, [pool, server]);

    const shown = POOL_RESOURCES.filter((key) => {
        if (key === 'subdomains' && !pool.features.subdomains) return false;
        if (key === 'proxies' && !pool.features.proxies) return false;
        return REQUIRED.includes(key) || pool.limits[key] > 0 || (server?.resources[key] || 0) > 0;
    });

    useEffect(() => {
        if (!open) return;
        clearFlashes();
        setBusy(false);
        if (server) {
            setValues({ ...server.resources });
            return;
        }
        setName('');
        setEgg(pool.eggs[0]?.id || 0);
        setLocation(pool.locations.length > 1 ? pool.locations[0].id : null);
        const initial = {} as PoolResources;
        POOL_RESOURCES.forEach((key) => {
            const { min, max } = limits[key];
            initial[key] = Math.max(min, Math.min(DEFAULTS[key] ?? 0, max));
        });
        setValues(initial);
    }, [open, server]);

    const selectedEgg = pool.eggs.find((item) => item.id === egg);

    useEffect(() => {
        if (!selectedEgg) return;
        setEnvironment(Object.fromEntries(selectedEgg.variables.map((variable) => [variable.env, variable.default])));
    }, [egg]);

    const impossible = shown.some((key) => limits[key].max < limits[key].min);

    const nests = pool.eggs.reduce((groups, item) => {
        const nest = item.nest || '';
        (groups[nest] = groups[nest] || []).push(item);
        return groups;
    }, {} as Record<string, typeof pool.eggs>);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        clearFlashes();
        setBusy(true);
        // Hidden resources stay as they are (0 for a new server).
        const resources = Object.fromEntries(
            POOL_RESOURCES.map((key) => [key, shown.includes(key) ? values[key] : server?.resources[key] || 0])
        ) as PoolResources;
        const request = server
            ? updatePoolServer(server.identifier, resources)
            : createPoolServer({ ...resources, name, egg, location, environment });
        request
            .then(() => onDone())
            .catch((error) => {
                clearAndAddHttpError(error);
                setBusy(false);
            });
    };

    return (
        <Dialog
            open={open}
            onClose={() => !busy && onClose()}
            preventExternalClose={busy}
            hideCloseIcon={busy}
            wide
            title={server ? t('edit_title', { name: server.name }) : t('create_title')}
        >
            <form id={'poolServerForm'} onSubmit={submit} className={'space-y-4'}>
                <FlashMessageRender byKey={'pool:dialog'} />
                {!server && (
                    <>
                        <div>
                            <Label htmlFor={'poolName'}>{t('name')}</Label>
                            <Input
                                id={'poolName'}
                                value={name}
                                onChange={(event) => setName(event.currentTarget.value)}
                                maxLength={191}
                                required
                            />
                        </div>
                        <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                            <div className={pool.locations.length > 1 ? '' : 'sm:col-span-2'}>
                                <Label htmlFor={'poolEgg'}>{t('egg')}</Label>
                                <Select
                                    id={'poolEgg'}
                                    value={egg}
                                    onChange={(event) => setEgg(Number(event.currentTarget.value))}
                                >
                                    {Object.entries(nests).map(([nest, eggs]) => (
                                        <optgroup key={nest} label={nest}>
                                            {eggs.map((item) => (
                                                <option key={item.id} value={item.id}>
                                                    {item.name}
                                                </option>
                                            ))}
                                        </optgroup>
                                    ))}
                                </Select>
                            </div>
                            {pool.locations.length > 1 && (
                                <div>
                                    <Label htmlFor={'poolLocation'}>{t('location')}</Label>
                                    <Select
                                        id={'poolLocation'}
                                        value={location || ''}
                                        onChange={(event) => setLocation(Number(event.currentTarget.value))}
                                    >
                                        {pool.locations.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            )}
                        </div>
                    </>
                )}
                <div className={'grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2'}>
                    {shown.map((key) => (
                        <ResourceField
                            key={key}
                            name={key}
                            value={values[key] ?? 0}
                            min={limits[key].min}
                            max={limits[key].max}
                            onChange={(value) => setValues((current) => ({ ...current, [key]: value }))}
                        />
                    ))}
                </div>
                {server && <p className={'text-xs text-gray-400'}>{t('edit_notice')}</p>}
                {!server && selectedEgg && selectedEgg.variables.length > 0 && (
                    <div>
                        <h3 className={'mb-2 text-sm font-semibold text-gray-100'}>{t('variables')}</h3>
                        <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                            {selectedEgg.variables.map((variable) => (
                                <div key={variable.env}>
                                    <Label htmlFor={`poolVar_${variable.env}`}>
                                        {variable.name}
                                        {variable.required && ' *'}
                                    </Label>
                                    <Input
                                        id={`poolVar_${variable.env}`}
                                        value={environment[variable.env] ?? ''}
                                        onChange={(event) => {
                                            const value = event.currentTarget.value;
                                            setEnvironment((current) => ({ ...current, [variable.env]: value }));
                                        }}
                                    />
                                    {variable.description && (
                                        <p className={'mt-1 text-xs text-gray-400'}>{variable.description}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </form>
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={busy}>
                    {t('cancel')}
                </Button.Text>
                <Button type={'submit'} form={'poolServerForm'} disabled={busy || impossible || (!server && !egg)}>
                    {busy ? t('saving') : server ? t('save') : t('create')}
                </Button>
            </Dialog.Footer>
        </Dialog>
    );
};
