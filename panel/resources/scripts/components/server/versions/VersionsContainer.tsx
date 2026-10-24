import React, { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { ArrowLeft, Boxes, Download, Loader2, Package, Search, Skull, TriangleAlert } from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { Input } from '@/components/elements/inputs';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import {
    getBuilds,
    getSoftware,
    getVersions,
    InstalledSoftware,
    installBuild,
    SoftwareBuild,
    SoftwareCategory,
    SoftwareType,
    SoftwareVersion,
    softwareIcon,
} from '@/api/server/versions';

// Hydra: Minecraft version changer (addon "versions"). Software → version →
// build, then a dialog installs it, optionally on an emptied server and with
// the EULA accepted.

const FLASH = 'server:versions';

const Icon = ({ type, size }: { type: string; size: 'large' | 'small' }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [failed, setFailed] = useState(false);
    const box = size === 'large' ? 'h-16 w-16' : 'h-10 w-10';

    return failed ? (
        <div className={classNames(box, 'flex shrink-0 items-center justify-center rounded bg-gray-700 text-gray-400')}>
            <Package className={'h-1/2 w-1/2'} />
        </div>
    ) : (
        <img
            src={softwareIcon(uuid, type)}
            alt={''}
            loading={'lazy'}
            onError={() => setFailed(true)}
            className={classNames(box, 'shrink-0 rounded object-contain')}
        />
    );
};

const Section = ({ title, children }: { title: string; children: React.ReactNode }) => (
    <section className={'mb-6'}>
        <div className={'mb-2 flex items-center gap-3'}>
            <h3 className={'shrink-0 text-xs font-medium text-gray-400'}>{title}</h3>
            <div className={'h-px flex-1 bg-gray-600'} />
        </div>
        <div className={'grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4'}>{children}</div>
    </section>
);

const Card = ({
    onClick,
    active,
    children,
    className,
}: {
    onClick?: () => void;
    active?: boolean;
    children: React.ReactNode;
    className?: string;
}) => (
    <button
        type={'button'}
        onClick={onClick}
        className={classNames(
            'flex w-full items-center gap-3 rounded-lg border bg-gray-800/90 p-3 text-left backdrop-blur transition-colors hover:border-gray-500 hover:bg-gray-700/90',
            active ? 'border-primary-500' : 'border-gray-700',
            className
        )}
    >
        {children}
    </button>
);

const TypeCard = ({ type, current, onClick }: { type: SoftwareType; current: boolean; onClick: () => void }) => {
    const { t } = useTranslation('client/versions');

    return (
        <Card onClick={onClick} active={current}>
            <Icon type={type.key} size={'large'} />
            <div className={'min-w-0'}>
                <p className={'flex items-center gap-1.5 text-lg font-medium text-gray-50'}>
                    <span className={'truncate'}>{type.name}</span>
                    {type.deprecated && (
                        <span title={t('deprecated')}>
                            <Skull className={'h-4 w-4 text-red-400'} />
                        </span>
                    )}
                    {type.experimental && (
                        <span title={t('experimental')}>
                            <TriangleAlert className={'h-4 w-4 text-yellow-400'} />
                        </span>
                    )}
                </p>
                <p className={'text-sm text-gray-400'}>
                    {type.minecraftVersions > 0
                        ? t('minecraft_versions', { count: type.minecraftVersions })
                        : t('project_versions', { count: type.projectVersions })}
                </p>
                <p className={'text-sm text-gray-400'}>{t('builds', { count: type.builds })}</p>
            </div>
        </Card>
    );
};

const InstallDialog = ({
    type,
    version,
    build,
    onClose,
    onInstalled,
}: {
    type: SoftwareType;
    version: SoftwareVersion;
    build: SoftwareBuild | null;
    onClose: () => void;
    onInstalled: () => void;
}) => {
    const { t } = useTranslation('client/versions');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addFlash, clearFlashes } = useFlash();
    const [wipe, setWipe] = useState(false);
    const [eula, setEula] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (build) {
            setWipe(false);
            setEula(false);
            setError(null);
        }
    }, [build]);

    const install = () => {
        if (!build) return;
        setBusy(true);
        setError(null);
        clearFlashes(FLASH);
        installBuild(uuid, build.id, { wipe, eula })
            .then((result) => {
                addFlash({
                    key: FLASH,
                    type: 'success',
                    message:
                        t('installed', { name: type.name, version: version.id, build: build.name }) +
                        (result.image_changed && result.java ? ' ' + t('installed_java', { java: result.java }) : ''),
                });
                onInstalled();
                onClose();
            })
            .catch((error) => setError(httpErrorToHuman(error)))
            .then(() => setBusy(false));
    };

    return (
        <Dialog
            open={!!build}
            onClose={() => !busy && onClose()}
            preventExternalClose={busy}
            hideCloseIcon={busy}
            title={t('install_title', { name: type.name, version: version.id, build: build?.name || '' })}
        >
            <p className={'text-sm text-gray-300'}>{t('install_text')}</p>
            {version.java && (
                <p className={'mt-2 text-xs text-gray-400'}>{t('install_java', { java: version.java })}</p>
            )}
            <label className={'mt-4 flex items-start gap-3 rounded border border-gray-500 bg-gray-700/50 p-3'}>
                <Input.Checkbox
                    checked={wipe}
                    disabled={busy}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setWipe(event.currentTarget.checked)}
                    className={'mt-0.5'}
                />
                <span>
                    <span className={'block text-sm font-medium text-gray-100'}>{t('wipe')}</span>
                    <span className={classNames('block text-xs', wipe ? 'text-red-300' : 'text-gray-400')}>
                        {t('wipe_help')}
                    </span>
                </span>
            </label>
            <label className={'mt-2 flex items-start gap-3 rounded border border-gray-500 bg-gray-700/50 p-3'}>
                <Input.Checkbox
                    checked={eula}
                    disabled={busy}
                    onChange={(event: React.ChangeEvent<HTMLInputElement>) => setEula(event.currentTarget.checked)}
                    className={'mt-0.5'}
                />
                <span>
                    <span className={'block text-sm font-medium text-gray-100'}>{t('eula')}</span>
                    <span className={'block text-xs text-gray-400'}>
                        {t('eula_help')}{' '}
                        <a
                            href={'https://aka.ms/MinecraftEULA'}
                            target={'_blank'}
                            rel={'noreferrer noopener'}
                            className={'text-primary-300 underline'}
                        >
                            {t('eula_link')}
                        </a>
                    </span>
                </span>
            </label>
            {error && <p className={'mt-3 rounded bg-red-500/10 px-3 py-2 text-sm text-red-300'}>{error}</p>}
            {busy && (
                <p className={'mt-3 flex items-center gap-2 text-sm text-gray-300'}>
                    <Loader2 className={'h-4 w-4 animate-spin'} />
                    {t('installing')}
                </p>
            )}
            <Dialog.Footer>
                <Button.Text onClick={onClose} disabled={busy}>
                    {t('cancel')}
                </Button.Text>
                {wipe ? (
                    <Button.Danger onClick={install} disabled={busy}>
                        {t('install_wipe')}
                    </Button.Danger>
                ) : (
                    <Button onClick={install} disabled={busy}>
                        {t('install')}
                    </Button>
                )}
            </Dialog.Footer>
        </Dialog>
    );
};

export default () => {
    const { t } = useTranslation('client/versions');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearAndAddHttpError } = useFlash();
    const [categories, setCategories] = useState<SoftwareCategory[] | null>(null);
    const [current, setCurrent] = useState<InstalledSoftware | null>(null);
    const [type, setType] = useState<SoftwareType | null>(null);
    const [versions, setVersions] = useState<SoftwareVersion[] | null>(null);
    const [version, setVersion] = useState<SoftwareVersion | null>(null);
    const [builds, setBuilds] = useState<SoftwareBuild[] | null>(null);
    const [snapshots, setSnapshots] = useState(false);
    const [filter, setFilter] = useState('');
    const [installing, setInstalling] = useState<SoftwareBuild | null>(null);

    const fail = (error: unknown) => clearAndAddHttpError({ key: FLASH, error });

    const loadSoftware = () =>
        getSoftware(uuid)
            .then((data) => {
                setCategories(data.categories);
                setCurrent(data.current);
            })
            .catch(fail);

    useEffect(() => {
        loadSoftware();
    }, [uuid]);

    const openType = (next: SoftwareType) => {
        setType(next);
        setVersion(null);
        setBuilds(null);
        setVersions(null);
        setFilter('');
        setSnapshots(false);
        getVersions(uuid, next.key).then(setVersions).catch(fail);
        window.scrollTo({ top: 0 });
    };

    const openVersion = (next: SoftwareVersion) => {
        if (!type) return;
        setVersion(next);
        setBuilds(null);
        getBuilds(uuid, type.key, next.id).then(setBuilds).catch(fail);
        window.scrollTo({ top: 0 });
    };

    const types = useMemo(() => (categories || []).flatMap((category) => category.types), [categories]);
    const currentType = types.find((entry) => entry.key === current?.type);
    const hasSnapshots = (versions || []).some((entry) => entry.type !== 'RELEASE');
    const shownVersions = (versions || []).filter(
        (entry) =>
            (snapshots || entry.type === 'RELEASE') && entry.id.toLowerCase().includes(filter.trim().toLowerCase())
    );

    return (
        <ServerContentBlock title={t('title')}>
            <FlashMessageRender byKey={FLASH} className={'mb-4'} />

            <div
                className={
                    'mb-6 flex flex-wrap items-center gap-4 rounded-lg border border-gray-700 bg-gray-800/90 p-4 backdrop-blur'
                }
            >
                {current ? (
                    <>
                        <Icon type={current.type} size={'small'} />
                        <div className={'min-w-0 flex-1'}>
                            <p className={'text-xs uppercase tracking-wide text-gray-400'}>{t('current')}</p>
                            <p className={'text-gray-50'}>
                                {currentType?.name || current.type} {current.version}{' '}
                                <span className={'text-gray-400'}>· {t('build', { build: current.build })}</span>
                            </p>
                        </div>
                        <p className={'text-xs text-gray-500'}>{formatDate(new Date(current.installed_at))}</p>
                    </>
                ) : (
                    <>
                        <Package className={'h-8 w-8 text-gray-500'} />
                        <p className={'flex-1 text-sm text-gray-400'}>{t('current_unknown')}</p>
                    </>
                )}
            </div>

            {!categories ? (
                <div className={'flex justify-center py-10'}>
                    <Loader2 className={'h-8 w-8 animate-spin text-gray-400'} />
                </div>
            ) : !type ? (
                categories.map((category) => (
                    <Section key={category.id} title={t(`categories.${category.id}`, category.id)}>
                        {category.types.map((entry) => (
                            <TypeCard
                                key={entry.key}
                                type={entry}
                                current={entry.key === current?.type}
                                onClick={() => openType(entry)}
                            />
                        ))}
                    </Section>
                ))
            ) : !version ? (
                <>
                    <div className={'relative mb-4 max-w-sm'}>
                        <Search className={'absolute left-3 top-2.5 h-4 w-4 text-gray-400'} />
                        <input
                            type={'search'}
                            value={filter}
                            onChange={(event) => setFilter(event.currentTarget.value)}
                            placeholder={t('search')}
                            className={
                                'w-full rounded border-2 border-gray-600 bg-gray-700 py-1.5 pl-9 pr-3 text-sm text-gray-100 outline-none focus:border-primary-400'
                            }
                        />
                    </div>
                    <Section title={type.name}>
                        <Card onClick={() => setType(null)}>
                            <ArrowLeft className={'h-8 w-8 shrink-0 text-gray-100'} />
                            <span className={'font-medium text-gray-100'}>{t('back')}</span>
                        </Card>
                        {hasSnapshots && (
                            <Card onClick={() => setSnapshots((v) => !v)} active={snapshots}>
                                <Boxes className={'h-8 w-8 shrink-0 text-gray-100'} />
                                <span className={'font-medium text-gray-100'}>
                                    {snapshots ? t('hide_snapshots') : t('show_snapshots')}
                                </span>
                            </Card>
                        )}
                        {!versions ? (
                            <div className={'flex items-center justify-center p-4'}>
                                <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                            </div>
                        ) : (
                            shownVersions.map((entry) => (
                                <Card
                                    key={entry.id}
                                    onClick={() => openVersion(entry)}
                                    active={current?.type === type.key && current.version === entry.id}
                                >
                                    <Icon type={type.key} size={'small'} />
                                    <div className={'min-w-0 flex-1'}>
                                        <p className={'truncate text-lg font-medium leading-tight text-gray-50'}>
                                            {entry.id}
                                        </p>
                                        <p className={'text-xs font-medium uppercase text-gray-400'}>
                                            {t(`version_types.${entry.type}`, entry.type)}
                                        </p>
                                    </div>
                                    <span className={'shrink-0 text-sm text-gray-400'}>
                                        {t('builds', { count: entry.builds })}
                                    </span>
                                </Card>
                            ))
                        )}
                    </Section>
                    {versions && shownVersions.length === 0 && (
                        <p className={'py-6 text-center text-sm text-gray-500'}>{t('no_versions')}</p>
                    )}
                </>
            ) : (
                <Section title={`${type.name} ${version.id}`}>
                    <Card onClick={() => setVersion(null)}>
                        <ArrowLeft className={'h-8 w-8 shrink-0 text-gray-100'} />
                        <span className={'font-medium text-gray-100'}>{t('back')}</span>
                    </Card>
                    {!builds ? (
                        <div className={'flex items-center justify-center p-4'}>
                            <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                        </div>
                    ) : (
                        builds.map((build, index) => {
                            const installed = current?.build_id === build.id;
                            return (
                                <div
                                    key={build.id}
                                    className={classNames(
                                        'flex flex-col gap-2 rounded-lg border bg-gray-800/90 p-3 backdrop-blur',
                                        installed ? 'border-primary-500' : 'border-gray-700'
                                    )}
                                >
                                    <div className={'flex items-center gap-3'}>
                                        <Icon type={type.key} size={'small'} />
                                        <div className={'min-w-0 flex-1'}>
                                            <p
                                                className={
                                                    'flex items-center gap-2 truncate text-lg font-medium leading-tight text-gray-50'
                                                }
                                            >
                                                {build.name}
                                                {index === 0 && (
                                                    <span
                                                        className={
                                                            'rounded bg-primary-500/20 px-1.5 text-2xs uppercase text-primary-300'
                                                        }
                                                    >
                                                        {t('latest')}
                                                    </span>
                                                )}
                                                {build.experimental && (
                                                    <span title={t('experimental')}>
                                                        <TriangleAlert className={'h-4 w-4 text-yellow-400'} />
                                                    </span>
                                                )}
                                            </p>
                                            <p className={'text-xs text-gray-400'}>
                                                {build.created ? formatDate(new Date(build.created), 'date') : ''}
                                                {build.size > 0 && ` · ${bytesToString(build.size, 1)}`}
                                            </p>
                                        </div>
                                        <button
                                            type={'button'}
                                            onClick={() => setInstalling(build)}
                                            className={
                                                'flex shrink-0 items-center gap-1.5 rounded bg-primary-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-400'
                                            }
                                        >
                                            <Download className={'h-4 w-4'} />
                                            {installed ? t('reinstall') : t('install')}
                                        </button>
                                    </div>
                                    {build.changes.length > 0 && (
                                        <ul className={'list-inside list-disc text-xs text-gray-400'}>
                                            {build.changes.slice(0, 3).map((change, i) => (
                                                <li key={i} className={'truncate'} title={change}>
                                                    {change}
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            );
                        })
                    )}
                </Section>
            )}

            {type && version && (
                <InstallDialog
                    type={type}
                    version={version}
                    build={installing}
                    onClose={() => setInstalling(null)}
                    onInstalled={loadSoftware}
                />
            )}
        </ServerContentBlock>
    );
};
