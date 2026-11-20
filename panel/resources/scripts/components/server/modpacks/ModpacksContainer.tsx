import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import {
    CheckCircle2,
    Circle,
    Download,
    ExternalLink,
    Loader2,
    PackageOpen,
    Search,
    TriangleAlert,
    X,
    XCircle,
} from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import { ModCategory, ModLoader, ModSort } from '@/api/server/mods';
import {
    getModpackOverview,
    getModpackStatus,
    getModpackVersions,
    installModpack,
    ModpackInstall,
    modpackIcon,
    ModpackMode,
    ModpackOverview,
    ModpackProject,
    ModpackSourceKey,
    ModpackVersion,
    searchModpacks,
} from '@/api/server/modpacks';
import {
    Badge,
    CategoryPicker,
    prettyCategory,
    ProjectIcon,
    SideButton,
    SideSection,
    VersionPicker,
} from '@/components/server/plugins/elements';

// Hydra: modpack installer (addon "modpacks"). Browse Modrinth and CurseForge,
// pick a version, and the panel installs the server software in the version
// the pack needs, its configs and its mods in the background.

const FLASH = 'server:modpacks';

const STEPS = ['prepare', 'clear', 'server', 'overrides', 'mods', 'finish'] as const;

interface Filters {
    source: ModpackSourceKey;
    loader: ModLoader | null;
    version: string | null;
    category: string | null;
}

const storageKey = (uuid: string) => `hydra:modpacks:${uuid}`;

const readStored = (uuid: string): Partial<Filters> & { dismissed?: number } => {
    try {
        return JSON.parse(localStorage.getItem(storageKey(uuid)) || '{}');
    } catch {
        return {};
    }
};

const writeStored = (uuid: string, value: object) => {
    try {
        localStorage.setItem(storageKey(uuid), JSON.stringify({ ...readStored(uuid), ...value }));
    } catch {
        // Storage blocked.
    }
};

const PackIcon = ({ url, size = 'large' }: { url: string | null; size?: 'large' | 'small' }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return <ProjectIcon src={url ? modpackIcon(uuid, url) : null} size={size} fallback={PackageOpen} />;
};

const useCategoryLabel = () => {
    const { t } = useTranslation('client/modpacks');

    return (category: ModCategory) => t(`categories.${category.slug}`, { defaultValue: prettyCategory(category) });
};

const LoaderBadges = ({ loaders }: { loaders: ModLoader[] }) => {
    const { t } = useTranslation('client/mods');

    return (
        <>
            {loaders.map((loader) => (
                <Badge key={loader} tone={'gray'}>
                    {t(`loaders.${loader}`)}
                </Badge>
            ))}
        </>
    );
};

const VersionsDialog = ({
    project,
    source,
    canInstall,
    currentVersion,
    onClose,
    onPick,
}: {
    project: ModpackProject | null;
    source: ModpackSourceKey;
    canInstall: boolean;
    currentVersion: string | null;
    onClose: () => void;
    onPick: (version: ModpackVersion) => void;
}) => {
    const { t, i18n } = useTranslation('client/modpacks');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [versions, setVersions] = useState<ModpackVersion[] | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!project) return;
        setVersions(null);
        setError(null);
        getModpackVersions(uuid, source, project.id)
            .then(setVersions)
            .catch((e) => setError(httpErrorToHuman(e)));
    }, [project?.id, source]);

    return (
        <Dialog open={!!project} onClose={onClose} title={project?.name || ''}>
            {project && (
                <>
                    <div className={'flex items-start gap-3'}>
                        <PackIcon url={project.icon} />
                        <div className={'min-w-0 flex-1'}>
                            <p className={'text-sm text-gray-300'}>{project.description}</p>
                            <a
                                href={project.url}
                                target={'_blank'}
                                rel={'noreferrer noopener'}
                                className={
                                    'mt-1 inline-flex items-center gap-1 text-xs text-primary-300 hover:underline'
                                }
                            >
                                <ExternalLink className={'h-3 w-3'} />
                                {t('open_page')}
                            </a>
                        </div>
                    </div>
                    <h3 className={'mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400'}>
                        {t('versions')}
                    </h3>
                    {error ? (
                        <p className={'rounded bg-red-500/10 px-3 py-2 text-sm text-red-300'}>{error}</p>
                    ) : !versions ? (
                        <div className={'flex justify-center py-6'}>
                            <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                        </div>
                    ) : versions.length === 0 ? (
                        <p className={'py-4 text-center text-sm text-gray-500'}>{t('no_versions')}</p>
                    ) : (
                        <div className={'max-h-96 space-y-2 overflow-y-auto pr-1'}>
                            {versions.map((version) => (
                                <div
                                    key={version.id}
                                    className={classNames(
                                        'flex items-center gap-3 rounded border bg-gray-700/50 px-3 py-2',
                                        currentVersion === version.id ? 'border-primary-500' : 'border-gray-600'
                                    )}
                                >
                                    <div className={'min-w-0 flex-1'}>
                                        <p
                                            className={
                                                'flex flex-wrap items-center gap-1.5 text-sm font-medium text-gray-50'
                                            }
                                        >
                                            <span className={'truncate'}>{version.name}</span>
                                            {version.channel !== 'release' && (
                                                <Badge tone={'yellow'}>{t(`channels.${version.channel}`)}</Badge>
                                            )}
                                            {currentVersion === version.id && (
                                                <Badge tone={'accent'}>{t('current_badge')}</Badge>
                                            )}
                                            <LoaderBadges loaders={version.loaders} />
                                        </p>
                                        <p className={'truncate text-xs text-gray-400'}>
                                            {[
                                                version.game_versions.length ? version.game_versions.join(', ') : null,
                                                version.date ? formatDate(new Date(version.date), 'date') : null,
                                                version.size > 0 ? bytesToString(version.size, 1) : null,
                                                version.downloads > 0
                                                    ? t('downloads', {
                                                          count: version.downloads.toLocaleString(
                                                              i18n.language
                                                          ) as unknown as number,
                                                      })
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    {version.installable ? (
                                        canInstall && (
                                            <button
                                                type={'button'}
                                                onClick={() => onPick(version)}
                                                className={
                                                    'flex shrink-0 items-center gap-1.5 rounded bg-primary-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-400'
                                                }
                                            >
                                                <Download className={'h-4 w-4'} />
                                                {currentVersion === version.id ? t('reinstall') : t('install')}
                                            </button>
                                        )
                                    ) : (
                                        <a
                                            href={version.external_url || project.url}
                                            target={'_blank'}
                                            rel={'noreferrer noopener'}
                                            className={
                                                'flex shrink-0 items-center gap-1.5 rounded bg-gray-600 px-3 py-1.5 text-sm text-gray-100 hover:bg-gray-500'
                                            }
                                        >
                                            <ExternalLink className={'h-4 w-4'} />
                                            {t('open_page')}
                                        </a>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                    <Dialog.Footer>
                        <Button.Text onClick={onClose}>{t('progress.close')}</Button.Text>
                    </Dialog.Footer>
                </>
            )}
        </Dialog>
    );
};

const InstallDialog = ({
    target,
    profiles,
    hasCurrent,
    onClose,
    onConfirm,
}: {
    target: { project: ModpackProject; version: ModpackVersion } | null;
    profiles: boolean;
    hasCurrent: boolean;
    onClose: () => void;
    onConfirm: (mode: ModpackMode, eula: boolean) => Promise<void>;
}) => {
    const { t } = useTranslation('client/modpacks');
    const [mode, setMode] = useState<ModpackMode>('wipe');
    const [eula, setEula] = useState(false);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (target) {
            setMode(hasCurrent ? 'keep' : 'wipe');
            setEula(false);
        }
    }, [target?.version.id]);

    const modes: ModpackMode[] = profiles ? ['wipe', 'profile', 'keep'] : ['wipe', 'keep'];

    return (
        <Dialog
            open={!!target}
            onClose={() => !busy && onClose()}
            preventExternalClose={busy}
            title={target ? t('confirm.title', { name: target.project.name, version: target.version.name }) : ''}
        >
            {target && (
                <>
                    <p className={'text-sm text-gray-300'}>{t('confirm.text')}</p>
                    <h3 className={'mb-2 mt-4 text-xs font-semibold uppercase tracking-wide text-gray-400'}>
                        {t('confirm.mode')}
                    </h3>
                    <div className={'space-y-2'}>
                        {modes.map((key) => (
                            <label
                                key={key}
                                className={classNames(
                                    'flex cursor-pointer gap-3 rounded border px-3 py-2',
                                    mode === key ? 'border-primary-500 bg-primary-500/10' : 'border-gray-600'
                                )}
                            >
                                <input
                                    type={'radio'}
                                    name={'modpack-mode'}
                                    className={'mt-1'}
                                    checked={mode === key}
                                    onChange={() => setMode(key)}
                                />
                                <span>
                                    <span className={'block text-sm font-medium text-gray-50'}>
                                        {t(`confirm.modes.${key}`)}
                                    </span>
                                    <span className={'block text-xs text-gray-400'}>
                                        {t(`confirm.modes.${key}_help`)}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <label className={'mt-4 flex cursor-pointer items-center gap-2 text-sm text-gray-200'}>
                        <input type={'checkbox'} checked={eula} onChange={(e) => setEula(e.currentTarget.checked)} />
                        {t('confirm.eula')}
                        <a
                            href={'https://aka.ms/MinecraftEULA'}
                            target={'_blank'}
                            rel={'noreferrer noopener'}
                            className={'text-xs text-primary-300 hover:underline'}
                        >
                            ({t('confirm.eula_link')})
                        </a>
                    </label>
                    <Dialog.Footer>
                        <Button.Text onClick={onClose} disabled={busy}>
                            {t('actions.cancel', { ns: 'client/common', defaultValue: 'Cancel' })}
                        </Button.Text>
                        <Button
                            disabled={busy}
                            onClick={() => {
                                setBusy(true);
                                onConfirm(mode, eula).finally(() => setBusy(false));
                            }}
                        >
                            {busy && <Loader2 className={'mr-2 h-4 w-4 animate-spin'} />}
                            {t('confirm.start')}
                        </Button>
                    </Dialog.Footer>
                </>
            )}
        </Dialog>
    );
};

const ProgressPanel = ({ install, onDismiss }: { install: ModpackInstall; onDismiss: () => void }) => {
    const { t } = useTranslation('client/modpacks');
    const result = install.result || {};
    const current = install.step ? STEPS.indexOf(install.step) : -1;
    const finished = install.status === 'done' || install.status === 'failed';

    return (
        <div
            className={classNames(
                'mb-4 rounded-lg border bg-gray-800/90 p-4 backdrop-blur',
                install.status === 'failed'
                    ? 'border-red-500/60'
                    : install.status === 'done'
                    ? 'border-primary-500/60'
                    : 'border-gray-700'
            )}
        >
            <div className={'flex items-start gap-3'}>
                <PackIcon url={install.icon} size={'small'} />
                <div className={'min-w-0 flex-1'}>
                    <p className={'font-medium text-gray-50'}>
                        {t(
                            install.status === 'done'
                                ? 'progress.done_title'
                                : install.status === 'failed'
                                ? 'progress.failed_title'
                                : 'progress.title',
                            { name: install.name, version: install.version_name || '' }
                        )}
                    </p>
                    {result.server_build && (
                        <p className={'text-xs text-gray-400'}>
                            {t('progress.server', { build: result.server_build })}
                        </p>
                    )}
                </div>
                {finished && (
                    <button
                        type={'button'}
                        onClick={onDismiss}
                        className={'hydra-icon-btn'}
                        title={t('progress.close')}
                    >
                        <X className={'h-4 w-4'} />
                    </button>
                )}
            </div>

            {install.status === 'queued' && <p className={'mt-3 text-sm text-gray-400'}>{t('progress.queued')}</p>}
            {install.status !== 'queued' && install.status !== 'done' && (
                <ol className={'mt-3 grid gap-1.5 sm:grid-cols-2'}>
                    {STEPS.map((step, index) => {
                        const state =
                            index < current || install.status === 'done'
                                ? 'done'
                                : index === current
                                ? install.status === 'failed'
                                    ? 'failed'
                                    : 'active'
                                : 'open';
                        return (
                            <li
                                key={step}
                                className={classNames(
                                    'flex items-center gap-2 text-sm',
                                    state === 'open' ? 'text-gray-500' : 'text-gray-200'
                                )}
                            >
                                {state === 'done' ? (
                                    <CheckCircle2 className={'h-4 w-4 text-primary-400'} />
                                ) : state === 'active' ? (
                                    <Loader2 className={'h-4 w-4 animate-spin text-primary-300'} />
                                ) : state === 'failed' ? (
                                    <XCircle className={'h-4 w-4 text-red-400'} />
                                ) : (
                                    <Circle className={'h-4 w-4'} />
                                )}
                                {t(`progress.steps.${step}`)}
                                {step === 'mods' && install.total > 0 && index <= current && (
                                    <span className={'text-xs text-gray-400'}>
                                        {t('progress.mods', { done: install.progress, total: install.total })}
                                    </span>
                                )}
                            </li>
                        );
                    })}
                </ol>
            )}
            {install.step === 'mods' && install.status === 'running' && install.total > 0 && (
                <div className={'mt-3 h-1.5 overflow-hidden rounded bg-gray-700'}>
                    <div
                        className={'h-full bg-primary-500 transition-all'}
                        style={{ width: `${Math.round((install.progress / install.total) * 100)}%` }}
                    />
                </div>
            )}

            {install.status === 'failed' && install.error && (
                <p className={'mt-3 rounded bg-red-500/10 px-3 py-2 text-sm text-red-300'}>{install.error}</p>
            )}
            {install.status === 'done' && <p className={'mt-3 text-sm text-gray-300'}>{t('progress.done')}</p>}
            {result.exact_build === false && result.loader_version && (
                <p className={'mt-2 flex gap-2 text-xs text-yellow-300'}>
                    <TriangleAlert className={'h-3.5 w-3.5 shrink-0'} />
                    {t('progress.not_exact', { version: result.loader_version })}
                </p>
            )}
            {!!result.manual?.length && (
                <div className={'mt-3 text-xs text-yellow-200'}>
                    <p>{t('progress.manual', { count: result.manual.length })}</p>
                    <ul className={'mt-1 list-inside list-disc text-gray-300'}>
                        {result.manual.map((entry) => (
                            <li key={entry.name}>
                                {entry.url ? (
                                    <a
                                        href={entry.url}
                                        target={'_blank'}
                                        rel={'noreferrer noopener'}
                                        className={'hover:underline'}
                                    >
                                        {entry.name}
                                    </a>
                                ) : (
                                    entry.name
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {!!result.failed?.length && (
                <p className={'mt-3 text-xs text-red-300'}>
                    {t('progress.failed_files', { count: result.failed.length })} {result.failed.join(', ')}
                </p>
            )}
            {!!result.skipped?.length && finished && (
                <details className={'mt-3 text-xs text-gray-400'}>
                    <summary className={'cursor-pointer'}>
                        {t('progress.skipped', { count: result.skipped.length })}
                    </summary>
                    <p className={'mt-1'}>{result.skipped.join(', ')}</p>
                </details>
            )}
        </div>
    );
};

const ProjectCard = ({
    project,
    categories,
    current,
    onOpen,
    onCategory,
}: {
    project: ModpackProject;
    categories: ModCategory[];
    current: boolean;
    onOpen: () => void;
    onCategory: (key: string) => void;
}) => {
    const { t, i18n } = useTranslation('client/modpacks');
    const label = useCategoryLabel();
    const compact = useMemo(() => new Intl.NumberFormat(i18n.language, { notation: 'compact' }), [i18n.language]);
    const tags = project.categories
        .map((key) => categories.find((c) => c.key === key))
        .filter((c): c is ModCategory => !!c)
        .slice(0, 3);

    return (
        <div
            className={classNames(
                'flex flex-col gap-3 rounded-lg border bg-gray-800/90 p-3 backdrop-blur transition-colors hover:border-gray-500',
                current ? 'border-primary-500/60' : 'border-gray-700'
            )}
        >
            <button type={'button'} onClick={onOpen} className={'flex min-w-0 items-start gap-3 text-left'}>
                <PackIcon url={project.icon} />
                <div className={'min-w-0 flex-1'}>
                    <p className={'flex items-center gap-1.5 text-base font-medium leading-tight text-gray-50'}>
                        <span className={'truncate'}>{project.name}</span>
                        {current && <Badge tone={'accent'}>{t('current_badge')}</Badge>}
                        {project.reason === 'external' && (
                            <Badge tone={'gray'} title={t('external_help')}>
                                {t('external')}
                            </Badge>
                        )}
                    </p>
                    {project.author && (
                        <p className={'truncate text-xs text-gray-400'}>{t('by', { author: project.author })}</p>
                    )}
                    <p className={'mt-1 line-clamp-2 text-sm text-gray-300'}>{project.description}</p>
                </div>
            </button>
            {(tags.length > 0 || project.loaders.length > 0) && (
                <div className={'flex flex-wrap items-center gap-1'}>
                    <LoaderBadges loaders={project.loaders} />
                    {tags.map((category) => (
                        <button
                            key={category.key}
                            type={'button'}
                            onClick={() => onCategory(category.key)}
                            className={
                                'rounded bg-gray-700/80 px-1.5 py-0.5 text-2xs text-gray-300 hover:bg-gray-600 hover:text-gray-50'
                            }
                        >
                            {label(category)}
                        </button>
                    ))}
                </div>
            )}
            <div className={'mt-auto flex items-center gap-2 text-xs text-gray-400'}>
                <span className={'flex items-center gap-1'}>
                    <Download className={'h-3.5 w-3.5'} />
                    {compact.format(project.downloads)}
                </span>
                {project.game_version && <span>· {project.game_version}</span>}
                {project.updated && (
                    <span className={'truncate'}>
                        · {t('updated', { date: formatDate(new Date(project.updated), 'date') })}
                    </span>
                )}
            </div>
        </div>
    );
};

export default () => {
    const { t } = useTranslation('client/modpacks');
    const { t: tMods } = useTranslation('client/mods');
    const categoryLabel = useCategoryLabel();
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [canInstall] = usePermissions(['modpacks.install']);
    const { clearFlashes, clearAndAddHttpError } = useFlash();

    const [overview, setOverview] = useState<ModpackOverview | null>(null);
    const [filters, setFilters] = useState<Filters | null>(null);
    const [install, setInstall] = useState<ModpackInstall | null>(null);
    const [dismissed, setDismissed] = useState<number | null>(null);

    const [query, setQuery] = useState('');
    const [debounced, setDebounced] = useState('');
    const [sort, setSort] = useState<ModSort>('relevance');
    const [results, setResults] = useState<ModpackProject[] | null>(null);
    const [page, setPage] = useState(1);
    const [more, setMore] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const request = useRef(0);

    const [open, setOpen] = useState<ModpackProject | null>(null);
    const [target, setTarget] = useState<{ project: ModpackProject; version: ModpackVersion } | null>(null);

    const fail = (error: unknown) => clearAndAddHttpError({ key: FLASH, error });
    const active = !!install && (install.status === 'queued' || install.status === 'running');

    useEffect(() => {
        getModpackOverview(uuid)
            .then((data) => {
                setOverview(data);
                setInstall(data.install);
                const stored = readStored(uuid);
                setDismissed(stored.dismissed ?? null);
                const source = data.sources.find((s) => s.key === stored.source)?.key || 'modrinth';
                const categories = data.sources.find((s) => s.key === source)?.categories || [];
                setFilters({
                    source,
                    loader: stored.loader && data.loaders.includes(stored.loader) ? stored.loader : null,
                    version: stored.version ?? null,
                    category: categories.some((c) => c.key === stored.category) ? stored.category! : null,
                });
            })
            .catch(fail);
    }, [uuid]);

    useEffect(() => {
        if (filters) writeStored(uuid, filters);
    }, [filters]);

    // Progress while an install runs; the page refreshes what is installed when it ends.
    useEffect(() => {
        if (!active) return;
        const timer = setInterval(() => {
            getModpackStatus(uuid)
                .then((data) => {
                    setInstall(data);
                    if (data && (data.status === 'done' || data.status === 'failed')) {
                        getModpackOverview(uuid)
                            .then(setOverview)
                            .catch(() => undefined);
                    }
                })
                .catch(() => undefined);
        }, 2500);

        return () => clearInterval(timer);
    }, [active, uuid]);

    useEffect(() => {
        const timeout = setTimeout(() => setDebounced(query.trim()), 350);

        return () => clearTimeout(timeout);
    }, [query]);

    const source = overview?.sources.find((s) => s.key === filters?.source);
    const categories = source?.categories || [];

    useEffect(() => {
        if (!filters) return;
        const id = ++request.current;
        setResults(null);
        setPage(1);
        searchModpacks(uuid, { ...filters, q: debounced, sort, page: 1 })
            .then((data) => {
                if (id !== request.current) return;
                setResults(data.data);
                setMore(data.more);
            })
            .catch((error) => {
                if (id !== request.current) return;
                setResults([]);
                fail(error);
            });
    }, [filters?.source, filters?.loader, filters?.version, filters?.category, debounced, sort]);

    const loadMore = () => {
        if (!filters) return;
        const id = request.current;
        setLoadingMore(true);
        searchModpacks(uuid, { ...filters, q: debounced, sort, page: page + 1 })
            .then((data) => {
                if (id !== request.current) return;
                setResults((current) => [
                    ...(current || []),
                    ...data.data.filter((p) => !current?.some((c) => c.id === p.id)),
                ]);
                setMore(data.more);
                setPage((p) => p + 1);
            })
            .catch(fail)
            .then(() => setLoadingMore(false));
    };

    const setFilter = (next: Partial<Filters>) =>
        setFilters((current) => {
            if (!current) return current;
            const merged = { ...current, ...next };
            // Categories belong to a source.
            if (next.source && next.source !== current.source) merged.category = null;

            return merged;
        });

    const start = (mode: ModpackMode, eula: boolean): Promise<void> => {
        if (!target || !filters) return Promise.resolve();
        clearFlashes(FLASH);

        return installModpack(uuid, {
            source: filters.source,
            project: target.project.id,
            version_id: target.version.id,
            mode,
            eula,
        })
            .then((data) => {
                setInstall(data);
                setDismissed(null);
                setTarget(null);
                setOpen(null);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            })
            .catch((error) => {
                setTarget(null);
                fail(error);
            });
    };

    const dismiss = () => {
        if (!install) return;
        setDismissed(install.id);
        writeStored(uuid, { dismissed: install.id });
    };

    const current = overview?.current || null;

    return (
        <ServerContentBlock title={t('title')}>
            <FlashMessageRender byKey={FLASH} className={'mb-4'} />

            {!overview || !filters ? (
                <div className={'flex justify-center py-10'}>
                    <Loader2 className={'h-8 w-8 animate-spin text-gray-400'} />
                </div>
            ) : (
                <div className={'grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_260px]'}>
                    <div className={'min-w-0'}>
                        {install && (active || dismissed !== install.id) && (
                            <ProgressPanel install={install} onDismiss={dismiss} />
                        )}
                        {current && !active && (
                            <div
                                className={
                                    'mb-4 flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-800/90 p-3 backdrop-blur'
                                }
                            >
                                <PackIcon url={current.icon} size={'small'} />
                                <p className={'min-w-0 flex-1 truncate text-sm text-gray-200'}>
                                    {t('current', { name: current.name, version: current.version_name || '' })}
                                </p>
                                {current.url && (
                                    <a
                                        href={current.url}
                                        target={'_blank'}
                                        rel={'noreferrer noopener'}
                                        title={t('open_page')}
                                        className={'hydra-icon-btn'}
                                    >
                                        <ExternalLink className={'h-4 w-4'} />
                                    </a>
                                )}
                            </div>
                        )}

                        <div className={'mb-4 flex flex-wrap gap-2'}>
                            <div className={'relative min-w-[12rem] flex-1'}>
                                <Search className={'absolute left-3 top-2.5 h-4 w-4 text-gray-400'} />
                                <input
                                    type={'search'}
                                    value={query}
                                    onChange={(event) => setQuery(event.currentTarget.value)}
                                    placeholder={t('search')}
                                    className={
                                        'w-full rounded border-2 border-gray-600 bg-gray-700 py-1.5 pl-9 pr-3 text-sm text-gray-100 outline-none focus:border-primary-400'
                                    }
                                />
                            </div>
                            <select
                                value={sort}
                                onChange={(event) => setSort(event.currentTarget.value as ModSort)}
                                className={
                                    'rounded border-2 border-gray-600 bg-gray-700 px-3 py-1.5 text-sm text-gray-100 outline-none focus:border-primary-400'
                                }
                            >
                                {(['relevance', 'downloads', 'updated', 'newest'] as const).map((key) => (
                                    <option key={key} value={key}>
                                        {t(`sort.${key}`)}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {!results ? (
                            <div className={'flex justify-center py-10'}>
                                <Loader2 className={'h-8 w-8 animate-spin text-gray-400'} />
                            </div>
                        ) : results.length === 0 ? (
                            <p className={'py-10 text-center text-sm text-gray-500'}>{t('no_results')}</p>
                        ) : (
                            <>
                                <div className={'grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3'}>
                                    {results.map((project) => (
                                        <ProjectCard
                                            key={project.id}
                                            project={project}
                                            categories={categories}
                                            current={
                                                !!current &&
                                                current.source === filters.source &&
                                                current.project_id === project.id
                                            }
                                            onOpen={() => setOpen(project)}
                                            onCategory={(key) => setFilter({ category: key })}
                                        />
                                    ))}
                                </div>
                                {more && (
                                    <div className={'mt-4 flex justify-center'}>
                                        <Button.Text onClick={loadMore} disabled={loadingMore}>
                                            {loadingMore && <Loader2 className={'mr-2 h-4 w-4 animate-spin'} />}
                                            {t('load_more')}
                                        </Button.Text>
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <aside className={'lg:sticky lg:top-4 lg:self-start'}>
                        <div
                            className={
                                'space-y-6 rounded-xl border border-gray-700 bg-gray-800/90 px-3 py-5 backdrop-blur'
                            }
                        >
                            {overview.sources.length > 1 && (
                                <SideSection title={t('sidebar.source')}>
                                    {overview.sources.map((entry) => (
                                        <SideButton
                                            key={entry.key}
                                            active={filters.source === entry.key}
                                            onClick={() => setFilter({ source: entry.key })}
                                        >
                                            {entry.name}
                                        </SideButton>
                                    ))}
                                </SideSection>
                            )}
                            <SideSection title={t('sidebar.category')}>
                                {source?.error || categories.length === 0 ? (
                                    <p className={'flex gap-2 px-2 py-1 text-xs leading-relaxed text-gray-400'}>
                                        <TriangleAlert className={'mt-0.5 h-3.5 w-3.5 shrink-0'} />
                                        {source?.error || t('sidebar.categories_failed')}
                                    </p>
                                ) : (
                                    <div className={'max-h-80 overflow-y-auto pr-1'}>
                                        <CategoryPicker
                                            key={filters.source}
                                            categories={categories}
                                            value={filters.category}
                                            allLabel={t('sidebar.all_categories')}
                                            label={categoryLabel}
                                            onChange={(category) => setFilter({ category })}
                                        />
                                    </div>
                                )}
                            </SideSection>
                            <SideSection title={t('sidebar.version')}>
                                <div className={'max-h-80 overflow-y-auto pr-1'}>
                                    <VersionPicker
                                        versions={overview.minecraftVersions}
                                        value={filters.version}
                                        allLabel={t('sidebar.all_versions')}
                                        onChange={(version) => setFilter({ version })}
                                    />
                                </div>
                            </SideSection>
                            <SideSection title={t('sidebar.loader')}>
                                <SideButton active={!filters.loader} onClick={() => setFilter({ loader: null })}>
                                    {t('sidebar.all_loaders')}
                                </SideButton>
                                {overview.loaders.map((loader) => (
                                    <SideButton
                                        key={loader}
                                        active={filters.loader === loader}
                                        onClick={() => setFilter({ loader })}
                                    >
                                        {tMods(`loaders.${loader}`)}
                                    </SideButton>
                                ))}
                            </SideSection>
                        </div>
                    </aside>
                </div>
            )}

            {filters && (
                <VersionsDialog
                    project={open}
                    source={filters.source}
                    canInstall={canInstall && !active}
                    currentVersion={
                        open && current && current.source === filters.source && current.project_id === open.id
                            ? current.version_id
                            : null
                    }
                    onClose={() => setOpen(null)}
                    onPick={(version) => open && setTarget({ project: open, version })}
                />
            )}
            <InstallDialog
                target={target}
                profiles={!!overview?.profiles}
                hasCurrent={!!current && !!target && current.project_id === target.project.id}
                onClose={() => setTarget(null)}
                onConfirm={start}
            />
        </ServerContentBlock>
    );
};
