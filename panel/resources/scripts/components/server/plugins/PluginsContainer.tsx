import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useHistory } from 'react-router-dom';
import { useStoreState } from '@/state/hooks';
import { forgetFolder } from '@/components/hydra/serverNavigation';
import classNames from 'classnames';
import {
    ArrowUpCircle,
    Blocks,
    Download,
    ExternalLink,
    FolderX,
    Loader2,
    Power,
    RefreshCw,
    Search,
    Trash2,
    TriangleAlert,
} from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import { Dialog } from '@/components/elements/dialog';
import ConfirmationDialog from '@/components/elements/dialog/ConfirmationDialog';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { usePermissions } from '@/plugins/usePermissions';
import { httpErrorToHuman } from '@/api/http';
import { bytesToString } from '@/lib/formatters';
import { formatDate } from '@/lib/date';
import {
    deletePlugin,
    getInstalledPlugins,
    getPluginOverview,
    getPluginUpdates,
    getPluginVersions,
    InstalledPlugin,
    installPlugin,
    pluginIcon,
    PluginOverview,
    PluginProject,
    PluginSort,
    PluginSourceKey,
    PluginVersion,
    searchPlugins,
    togglePlugin,
} from '@/api/server/plugins';
import { Badge, ProjectIcon, SideButton, SideSection, VersionPicker } from '@/components/server/plugins/elements';

// Hydra: plugin manager (addon "plugins"). Browse Modrinth, Hangar and SpigotMC
// on the left, pick source, software and Minecraft version in the bar on the
// right, manage the jars of the plugins folder in the second tab.

const FLASH = 'server:plugins';

type Updates = Record<string, { version_id: string; version_name: string }>;

interface Filters {
    source: PluginSourceKey;
    software: string;
    version: string | null;
}

const storageKey = (uuid: string) => `hydra:plugins:${uuid}`;

const readStored = (uuid: string): Partial<Filters> => {
    try {
        return JSON.parse(localStorage.getItem(storageKey(uuid)) || '{}');
    } catch {
        return {};
    }
};

const PluginIcon = ({ url, size = 'large' }: { url: string | null; size?: 'large' | 'small' }) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    return <ProjectIcon src={url ? pluginIcon(uuid, url) : null} size={size} fallback={Blocks} />;
};

const VersionsDialog = ({
    project,
    filters,
    installedVersion,
    canManage,
    onClose,
    onInstall,
}: {
    project: PluginProject | null;
    filters: Filters;
    installedVersion: string | null;
    canManage: boolean;
    onClose: () => void;
    onInstall: (project: PluginProject, version: PluginVersion | null) => Promise<void>;
}) => {
    const { t, i18n } = useTranslation('client/plugins');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [versions, setVersions] = useState<PluginVersion[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState<string | null>(null);

    useEffect(() => {
        if (!project) return;
        setVersions(null);
        setError(null);
        getPluginVersions(uuid, project.id, filters)
            .then(setVersions)
            .catch((e) => setError(httpErrorToHuman(e)));
    }, [project?.id, filters.source, filters.software, filters.version]);

    const install = (version: PluginVersion) => {
        if (!project) return;
        setBusy(version.id);
        onInstall(project, version).finally(() => setBusy(null));
    };

    return (
        <Dialog
            open={!!project}
            onClose={() => !busy && onClose()}
            preventExternalClose={!!busy}
            title={project?.name || ''}
        >
            {project && (
                <>
                    <div className={'flex items-start gap-3'}>
                        <PluginIcon url={project.icon} />
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
                    {filters.source === 'spigot' && (
                        <p className={'mb-2 text-xs text-gray-400'}>{t('spigot_latest')}</p>
                    )}
                    {error ? (
                        <p className={'rounded bg-red-500/10 px-3 py-2 text-sm text-red-300'}>{error}</p>
                    ) : !versions ? (
                        <div className={'flex justify-center py-6'}>
                            <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                        </div>
                    ) : versions.length === 0 ? (
                        <p className={'py-4 text-center text-sm text-gray-500'}>{t('no_versions')}</p>
                    ) : (
                        <div className={'max-h-80 space-y-2 overflow-y-auto pr-1'}>
                            {versions.map((version) => (
                                <div
                                    key={version.id}
                                    className={classNames(
                                        'flex items-center gap-3 rounded border bg-gray-700/50 px-3 py-2',
                                        installedVersion === version.id ? 'border-primary-500' : 'border-gray-600'
                                    )}
                                >
                                    <div className={'min-w-0 flex-1'}>
                                        <p
                                            className={
                                                'flex items-center gap-2 truncate text-sm font-medium text-gray-50'
                                            }
                                        >
                                            <span className={'truncate'}>{version.name}</span>
                                            {version.channel !== 'release' && (
                                                <Badge tone={'yellow'}>{t(`channels.${version.channel}`)}</Badge>
                                            )}
                                            {installedVersion === version.id && (
                                                <Badge tone={'accent'}>{t('installed_badge')}</Badge>
                                            )}
                                        </p>
                                        <p className={'truncate text-xs text-gray-400'}>
                                            {[
                                                version.date ? formatDate(new Date(version.date), 'date') : null,
                                                version.size > 0 ? bytesToString(version.size, 1) : null,
                                                version.game_versions.length ? version.game_versions.join(', ') : null,
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
                                        canManage && (
                                            <button
                                                type={'button'}
                                                disabled={!!busy}
                                                onClick={() => install(version)}
                                                className={
                                                    'flex shrink-0 items-center gap-1.5 rounded bg-primary-500 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-400 disabled:opacity-50'
                                                }
                                            >
                                                {busy === version.id ? (
                                                    <Loader2 className={'h-4 w-4 animate-spin'} />
                                                ) : (
                                                    <Download className={'h-4 w-4'} />
                                                )}
                                                {t('install')}
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
                        <Button.Text onClick={onClose} disabled={!!busy}>
                            {t('actions.close', { ns: 'client/common', defaultValue: 'Close' })}
                        </Button.Text>
                    </Dialog.Footer>
                </>
            )}
        </Dialog>
    );
};

const ProjectCard = ({
    project,
    installed,
    update,
    canManage,
    busy,
    onOpen,
    onQuickInstall,
}: {
    project: PluginProject;
    installed: InstalledPlugin | undefined;
    update: string | null;
    canManage: boolean;
    busy: boolean;
    onOpen: () => void;
    onQuickInstall: () => void;
}) => {
    const { t, i18n } = useTranslation('client/plugins');
    const compact = useMemo(() => new Intl.NumberFormat(i18n.language, { notation: 'compact' }), [i18n.language]);

    return (
        <div
            className={classNames(
                'flex flex-col gap-3 rounded-lg border bg-gray-800/90 p-3 backdrop-blur transition-colors hover:border-gray-500',
                installed ? 'border-primary-500/60' : 'border-gray-700'
            )}
        >
            <button type={'button'} onClick={onOpen} className={'flex min-w-0 items-start gap-3 text-left'}>
                <PluginIcon url={project.icon} />
                <div className={'min-w-0 flex-1'}>
                    <p className={'flex items-center gap-1.5 text-base font-medium leading-tight text-gray-50'}>
                        <span className={'truncate'}>{project.name}</span>
                        {installed && <Badge tone={'accent'}>{t('installed_badge')}</Badge>}
                        {project.reason === 'premium' && (
                            <Badge tone={'yellow'} title={t('premium_help')}>
                                {t('premium')}
                            </Badge>
                        )}
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
            <div className={'mt-auto flex items-center gap-2 text-xs text-gray-400'}>
                <span className={'flex items-center gap-1'}>
                    <Download className={'h-3.5 w-3.5'} />
                    {compact.format(project.downloads)}
                </span>
                {project.updated && (
                    <span className={'truncate'}>
                        · {t('updated', { date: formatDate(new Date(project.updated), 'date') })}
                    </span>
                )}
                <span className={'flex-1'} />
                {canManage && project.installable && (!installed || update) && (
                    <button
                        type={'button'}
                        disabled={busy}
                        onClick={onQuickInstall}
                        className={
                            'flex shrink-0 items-center gap-1 rounded bg-primary-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-400 disabled:opacity-50'
                        }
                    >
                        {busy ? (
                            <Loader2 className={'h-3.5 w-3.5 animate-spin'} />
                        ) : update ? (
                            <ArrowUpCircle className={'h-3.5 w-3.5'} />
                        ) : (
                            <Download className={'h-3.5 w-3.5'} />
                        )}
                        {update ? t('update') : t('install')}
                    </button>
                )}
                {!project.installable && (
                    <a
                        href={project.url}
                        target={'_blank'}
                        rel={'noreferrer noopener'}
                        className={
                            'flex shrink-0 items-center gap-1 rounded bg-gray-700 px-2.5 py-1 text-gray-200 hover:bg-gray-600'
                        }
                    >
                        <ExternalLink className={'h-3.5 w-3.5'} />
                        {t('open_page')}
                    </a>
                )}
            </div>
        </div>
    );
};

export default () => {
    const { t } = useTranslation('client/plugins');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const hideWithoutFolder = useStoreState((state) => state.settings.data!.folderPages?.plugins ?? true);
    const history = useHistory();
    const [canManage] = usePermissions(['plugins.manage']);
    const { addFlash, clearFlashes, clearAndAddHttpError } = useFlash();

    const [overview, setOverview] = useState<PluginOverview | null>(null);
    const [filters, setFilters] = useState<Filters | null>(null);
    const [tab, setTab] = useState<'browse' | 'installed'>('browse');

    const [query, setQuery] = useState('');
    const [debounced, setDebounced] = useState('');
    const [sort, setSort] = useState<PluginSort>('relevance');
    const [results, setResults] = useState<PluginProject[] | null>(null);
    const [page, setPage] = useState(1);
    const [more, setMore] = useState(false);
    const [loadingMore, setLoadingMore] = useState(false);
    const request = useRef(0);

    const [installed, setInstalled] = useState<InstalledPlugin[] | null>(null);
    const [updates, setUpdates] = useState<Updates | null>(null);
    const [open, setOpen] = useState<PluginProject | null>(null);
    const [busy, setBusy] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<InstalledPlugin | null>(null);

    const fail = (error: unknown) => clearAndAddHttpError({ key: FLASH, error });

    // Start: sources, software and the defaults of the server (or what the user picked before).
    useEffect(() => {
        getPluginOverview(uuid)
            .then((data) => {
                setOverview(data);
                const stored = readStored(uuid);
                const software = data.software.some((s) => s.key === stored.software)
                    ? stored.software!
                    : data.defaults.software;
                const source =
                    data.sources.find((s) => s.key === stored.source && s.software.includes(software))?.key ||
                    data.sources.find((s) => s.software.includes(software))?.key ||
                    'modrinth';
                setFilters({
                    source,
                    software,
                    version:
                        stored.version !== undefined
                            ? stored.version
                            : data.defaults.version || data.minecraftVersions[0] || null,
                });
            })
            .catch(fail);
    }, [uuid]);

    useEffect(() => {
        if (filters) {
            try {
                localStorage.setItem(storageKey(uuid), JSON.stringify(filters));
            } catch {
                // Storage blocked.
            }
        }
    }, [filters]);

    useEffect(() => {
        const timeout = setTimeout(() => setDebounced(query.trim()), 350);

        return () => clearTimeout(timeout);
    }, [query]);

    const family = overview?.software.find((s) => s.key === filters?.software)?.family || 'server';
    const effective = filters ? { ...filters, version: family === 'proxy' ? null : filters.version } : null;

    // Search: first page whenever something changes.
    useEffect(() => {
        if (!effective || !overview?.folder) return;
        const id = ++request.current;
        setResults(null);
        setPage(1);
        searchPlugins(uuid, { ...effective, q: debounced, sort, page: 1 })
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
    }, [effective?.source, effective?.software, effective?.version, debounced, sort, overview?.folder]);

    const loadMore = () => {
        if (!effective) return;
        const id = request.current;
        setLoadingMore(true);
        searchPlugins(uuid, { ...effective, q: debounced, sort, page: page + 1 })
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

    const loadInstalled = () =>
        getInstalledPlugins(uuid)
            .then(setInstalled)
            .catch((error) => {
                setInstalled([]);
                fail(error);
            });

    const loadUpdates = () => {
        if (!effective) return;
        setUpdates(null);
        getPluginUpdates(uuid, effective)
            .then(setUpdates)
            .catch(() => setUpdates({}));
    };

    useEffect(() => {
        if (overview?.folder) loadInstalled();
    }, [overview?.folder]);

    useEffect(() => {
        if (overview?.folder && effective) loadUpdates();
    }, [overview?.folder, effective?.software, effective?.version]);

    const byProject = useMemo(() => {
        const map: Record<string, InstalledPlugin> = {};
        (installed || []).forEach((entry) => {
            if (entry.plugin) map[`${entry.plugin.source}:${entry.plugin.project_id}`] = entry;
        });

        return map;
    }, [installed]);

    const install = (
        project: PluginProject,
        version: PluginVersion | null,
        source?: PluginSourceKey
    ): Promise<void> => {
        if (!effective) return Promise.resolve();
        const key = `${source || effective.source}:${project.id}`;
        const wasInstalled = !!byProject[key];
        setBusy(key);
        clearFlashes(FLASH);

        return installPlugin(uuid, project.id, version?.id || null, {
            ...effective,
            source: source || effective.source,
        })
            .then((result) => {
                addFlash({
                    key: FLASH,
                    type: 'success',
                    message: t(wasInstalled ? 'updated_msg' : 'installed_msg', {
                        name: result.name,
                        version: result.version_name,
                    }),
                });
                setOpen(null);
                loadInstalled();
                loadUpdates();
            })
            .catch(fail)
            .then(() => setBusy(null));
    };

    const updateInstalled = (entry: InstalledPlugin) => {
        if (!entry.plugin) return;
        install(
            {
                id: entry.plugin.project_id,
                slug: entry.plugin.project_id,
                name: entry.plugin.name,
                author: '',
                description: '',
                icon: entry.plugin.icon,
                downloads: 0,
                updated: null,
                url: entry.plugin.url || '',
                installable: true,
                reason: null,
            },
            null,
            entry.plugin.source
        );
    };

    const toggle = (entry: InstalledPlugin) => {
        setBusy(entry.file);
        clearFlashes(FLASH);
        togglePlugin(uuid, entry.file, !entry.enabled)
            .then(loadInstalled)
            .catch(fail)
            .then(() => setBusy(null));
    };

    const remove = () => {
        if (!deleting) return;
        const entry = deleting;
        setDeleting(null);
        setBusy(entry.file);
        clearFlashes(FLASH);
        deletePlugin(uuid, entry.file)
            .then(loadInstalled)
            .catch(fail)
            .then(() => setBusy(null));
    };

    const setFilter = (next: Partial<Filters>) =>
        setFilters((current) => {
            if (!current || !overview) return current;
            const merged = { ...current, ...next };
            // Keep a source that has plugins for the software.
            if (!overview.sources.find((s) => s.key === merged.source)?.software.includes(merged.software)) {
                merged.source = overview.sources.find((s) => s.software.includes(merged.software))?.key || 'modrinth';
            }

            return merged;
        });

    const updateCount = Object.keys(updates || {}).length;

    // Without the folder the page is gone: back to the start page of the server,
    // whose navigation asks again and drops the button.
    const leave = !!overview && !overview.folder && hideWithoutFolder;
    useEffect(() => {
        if (!leave) return;
        forgetFolder('plugins', uuid);
        history.replace(`/server/${serverId}`);
    }, [leave]);

    if (leave) {
        return (
            <ServerContentBlock title={t('title')}>
                <div className={'flex justify-center py-10'}>
                    <Loader2 className={'h-8 w-8 animate-spin text-gray-400'} />
                </div>
            </ServerContentBlock>
        );
    }

    if (overview && !overview.folder) {
        return (
            <ServerContentBlock title={t('title')}>
                <FlashMessageRender byKey={FLASH} className={'mb-4'} />
                <div
                    className={
                        'flex flex-col items-center gap-3 rounded-lg border border-gray-700 bg-gray-800/90 p-10 text-center'
                    }
                >
                    <FolderX className={'h-10 w-10 text-gray-500'} />
                    <p className={'text-lg font-medium text-gray-100'}>{t('no_folder_title')}</p>
                    <p className={'max-w-md text-sm text-gray-400'}>{t('no_folder')}</p>
                </div>
            </ServerContentBlock>
        );
    }

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
                        {/* Folder, tabs and the restart hint */}
                        <div
                            className={
                                'mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-700 bg-gray-800/90 p-3 backdrop-blur'
                            }
                        >
                            <div className={'flex rounded bg-gray-900/60 p-1'}>
                                {(['browse', 'installed'] as const).map((key) => (
                                    <button
                                        key={key}
                                        type={'button'}
                                        onClick={() => setTab(key)}
                                        className={classNames(
                                            'flex items-center gap-2 rounded px-3 py-1.5 text-sm font-medium transition-colors',
                                            tab === key
                                                ? 'bg-primary-500 text-white'
                                                : 'text-gray-300 hover:text-gray-50'
                                        )}
                                    >
                                        {t(`tabs.${key}`)}
                                        {key === 'installed' && installed && (
                                            <span className={'rounded bg-black/25 px-1.5 text-xs'}>
                                                {installed.length}
                                            </span>
                                        )}
                                        {key === 'installed' && updateCount > 0 && (
                                            <span className={'rounded bg-yellow-500/30 px-1.5 text-xs text-yellow-200'}>
                                                {t('updates_count', { count: updateCount })}
                                            </span>
                                        )}
                                    </button>
                                ))}
                            </div>
                            <span className={'flex-1'} />
                            <span className={'text-xs text-gray-400'}>
                                {t('folder', { folder: overview.folder })} · {t('restart_hint')}
                            </span>
                        </div>

                        {tab === 'browse' ? (
                            <>
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
                                        onChange={(event) => setSort(event.currentTarget.value as PluginSort)}
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
                                            {results.map((project) => {
                                                const key = `${filters.source}:${project.id}`;
                                                const entry = byProject[key];
                                                return (
                                                    <ProjectCard
                                                        key={project.id}
                                                        project={project}
                                                        installed={entry}
                                                        update={
                                                            entry ? updates?.[entry.file]?.version_name || null : null
                                                        }
                                                        canManage={canManage}
                                                        busy={busy === key}
                                                        onOpen={() => setOpen(project)}
                                                        onQuickInstall={() => install(project, null)}
                                                    />
                                                );
                                            })}
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
                            </>
                        ) : !installed ? (
                            <div className={'flex justify-center py-10'}>
                                <Loader2 className={'h-8 w-8 animate-spin text-gray-400'} />
                            </div>
                        ) : (
                            <>
                                <div className={'mb-3 flex items-center gap-2 text-xs text-gray-400'}>
                                    {updates === null ? (
                                        <>
                                            <Loader2 className={'h-3.5 w-3.5 animate-spin'} />
                                            {t('checking_updates')}
                                        </>
                                    ) : updateCount === 0 && installed.some((e) => e.plugin) ? (
                                        t('up_to_date')
                                    ) : null}
                                    <span className={'flex-1'} />
                                    <button
                                        type={'button'}
                                        onClick={() => {
                                            setInstalled(null);
                                            loadInstalled();
                                            loadUpdates();
                                        }}
                                        className={'flex items-center gap-1 hover:text-gray-200'}
                                    >
                                        <RefreshCw className={'h-3.5 w-3.5'} />
                                        {t('refresh')}
                                    </button>
                                </div>
                                {installed.length === 0 ? (
                                    <p className={'py-10 text-center text-sm text-gray-500'}>
                                        {t('nothing_installed')}
                                    </p>
                                ) : (
                                    <div className={'space-y-2'}>
                                        {installed.map((entry) => {
                                            const update = updates?.[entry.file];
                                            const working =
                                                busy === entry.file ||
                                                (!!entry.plugin &&
                                                    busy === `${entry.plugin.source}:${entry.plugin.project_id}`);
                                            return (
                                                <div
                                                    key={entry.file}
                                                    className={classNames(
                                                        'flex flex-wrap items-center gap-3 rounded-lg border border-gray-700 bg-gray-800/90 p-3 backdrop-blur',
                                                        !entry.enabled && 'opacity-60'
                                                    )}
                                                >
                                                    <PluginIcon url={entry.plugin?.icon || null} size={'small'} />
                                                    <div className={'min-w-0 flex-1'}>
                                                        <p
                                                            className={
                                                                'flex items-center gap-2 truncate font-medium text-gray-50'
                                                            }
                                                        >
                                                            <span className={'truncate'}>
                                                                {entry.plugin?.name ||
                                                                    entry.file.replace(/\.jar$/i, '')}
                                                            </span>
                                                            <Badge tone={entry.enabled ? 'accent' : 'gray'}>
                                                                {entry.enabled ? t('enabled') : t('disabled')}
                                                            </Badge>
                                                            {update && <Badge tone={'yellow'}>{t('update')}</Badge>}
                                                        </p>
                                                        <p className={'truncate text-xs text-gray-400'}>
                                                            {[
                                                                entry.plugin
                                                                    ? t('version_label', {
                                                                          version: entry.plugin.version_name,
                                                                      })
                                                                    : null,
                                                                entry.plugin
                                                                    ? overview.sources.find(
                                                                          (s) => s.key === entry.plugin!.source
                                                                      )?.name || entry.plugin.source
                                                                    : t('unknown_source'),
                                                                entry.file,
                                                                bytesToString(entry.size, 1),
                                                            ]
                                                                .filter(Boolean)
                                                                .join(' · ')}
                                                        </p>
                                                    </div>
                                                    <div className={'flex shrink-0 items-center gap-1.5'}>
                                                        {working && (
                                                            <Loader2 className={'h-4 w-4 animate-spin text-gray-400'} />
                                                        )}
                                                        {canManage && update && (
                                                            <button
                                                                type={'button'}
                                                                disabled={working}
                                                                onClick={() => updateInstalled(entry)}
                                                                className={
                                                                    'flex items-center gap-1 rounded bg-primary-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-400 disabled:opacity-50'
                                                                }
                                                            >
                                                                <ArrowUpCircle className={'h-3.5 w-3.5'} />
                                                                {t('update_to', { version: update.version_name })}
                                                            </button>
                                                        )}
                                                        {entry.plugin?.url && (
                                                            <a
                                                                href={entry.plugin.url}
                                                                target={'_blank'}
                                                                rel={'noreferrer noopener'}
                                                                title={t('open_page')}
                                                                className={'hydra-icon-btn'}
                                                            >
                                                                <ExternalLink className={'h-4 w-4'} />
                                                            </a>
                                                        )}
                                                        {canManage && (
                                                            <>
                                                                <button
                                                                    type={'button'}
                                                                    disabled={working}
                                                                    title={entry.enabled ? t('disable') : t('enable')}
                                                                    onClick={() => toggle(entry)}
                                                                    className={classNames(
                                                                        'hydra-icon-btn',
                                                                        entry.enabled && 'text-primary-300'
                                                                    )}
                                                                >
                                                                    <Power className={'h-4 w-4'} />
                                                                </button>
                                                                <button
                                                                    type={'button'}
                                                                    disabled={working}
                                                                    title={t('delete')}
                                                                    onClick={() => setDeleting(entry)}
                                                                    className={'hydra-icon-btn hover:text-red-400'}
                                                                >
                                                                    <Trash2 className={'h-4 w-4'} />
                                                                </button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    {/* Right: source, software, Minecraft version */}
                    <aside className={'lg:sticky lg:top-4 lg:self-start'}>
                        <div
                            className={
                                'space-y-6 rounded-xl border border-gray-700 bg-gray-800/90 px-3 py-5 backdrop-blur'
                            }
                        >
                            <SideSection title={t('sidebar.source')}>
                                {overview.sources.map((source) => {
                                    const supported = source.software.includes(filters.software);
                                    return (
                                        <SideButton
                                            key={source.key}
                                            active={filters.source === source.key}
                                            disabled={!supported}
                                            title={supported ? undefined : t('sidebar.unsupported')}
                                            onClick={() => setFilter({ source: source.key })}
                                        >
                                            {source.name}
                                        </SideButton>
                                    );
                                })}
                            </SideSection>
                            <SideSection title={t('sidebar.version')}>
                                {family === 'proxy' ? (
                                    <p className={'flex gap-2 px-2 py-1 text-xs leading-relaxed text-gray-400'}>
                                        <TriangleAlert className={'mt-0.5 h-3.5 w-3.5 shrink-0'} />
                                        {t('sidebar.proxy_hint')}
                                    </p>
                                ) : (
                                    <div className={'max-h-80 overflow-y-auto pr-1'}>
                                        <VersionPicker
                                            versions={overview.minecraftVersions}
                                            value={filters.version}
                                            allLabel={t('sidebar.all_versions')}
                                            onChange={(version) => setFilter({ version })}
                                        />
                                    </div>
                                )}
                            </SideSection>
                            {(['server', 'proxy'] as const).map((group) => (
                                <SideSection key={group} title={t(`sidebar.${group}_software`)}>
                                    {overview.software
                                        .filter((s) => s.family === group)
                                        .map((software) => (
                                            <SideButton
                                                key={software.key}
                                                active={filters.software === software.key}
                                                onClick={() => setFilter({ software: software.key })}
                                            >
                                                {t(`software.${software.key}`)}
                                            </SideButton>
                                        ))}
                                </SideSection>
                            ))}
                        </div>
                    </aside>
                </div>
            )}

            {filters && effective && (
                <VersionsDialog
                    project={open}
                    filters={effective}
                    installedVersion={
                        open ? byProject[`${filters.source}:${open.id}`]?.plugin?.version_id || null : null
                    }
                    canManage={canManage}
                    onClose={() => setOpen(null)}
                    onInstall={(project, version) => install(project, version)}
                />
            )}

            <ConfirmationDialog
                open={!!deleting}
                onClose={() => setDeleting(null)}
                title={t('delete_title', { name: deleting?.plugin?.name || deleting?.file || '' })}
                confirm={t('delete')}
                onConfirmed={remove}
            >
                {t('delete_text')}
            </ConfirmationDialog>
        </ServerContentBlock>
    );
};
