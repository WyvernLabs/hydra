import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { NavLink, useHistory, useLocation } from 'react-router-dom';
import classNames from 'classnames';
import {
    Archive,
    ArchiveRestore,
    ClipboardPaste,
    Copy,
    Download,
    ExternalLink,
    FilePlus,
    FolderPlus,
    FolderUp,
    KeyRound,
    Pencil,
    RefreshCw,
    Scissors,
    Trash2,
    Upload,
} from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { useStoreState } from '@/state/hooks';
import { usePermissions } from '@/plugins/usePermissions';
import { isPathAllowed } from '@/plugins/useAllowedPaths';
import useFlash from '@/plugins/useFlash';
import useFileTransfers from '@/plugins/useFileTransfers';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import renameFiles from '@/api/server/files/renameFiles';
import deleteFiles from '@/api/server/files/deleteFiles';
import createDirectory from '@/api/server/files/createDirectory';
import saveFileContents from '@/api/server/files/saveFileContents';
import copyFile from '@/api/server/files/copyFile';
import chmodFiles from '@/api/server/files/chmodFiles';
import { getTrash } from '@/api/server/files/trash';
import { httpErrorToHuman } from '@/api/http';
import { encodePathSegments, hashToPath } from '@/helpers';
import {
    EditorTab,
    IdeContext,
    IdeContextValue,
    isExternalDrag,
    MenuTarget,
} from '@/components/server/files/ide/context';
import { fileKind } from '@/components/server/files/ide/fileTypes';
import {
    ancestors,
    basename,
    dirname,
    isInside,
    joinPath,
    normalize,
    rebase,
} from '@/components/server/files/ide/paths';
import useUploads, {
    selectionFromDataTransfer,
    selectionFromInput,
    UploadSelection,
} from '@/components/server/files/ide/uploads';
import { FILES_REFRESH_EVENT } from '@/components/server/files/ide/events';
import FolderTree from '@/components/server/files/ide/FolderTree';
import FileList from '@/components/server/files/ide/FileList';
import EditorArea from '@/components/server/files/ide/EditorArea';
import ContextMenu, { MenuItem } from '@/components/server/files/ide/ContextMenu';
import TrashDialog from '@/components/server/files/ide/TrashDialog';
import { ChmodDialog, DeleteDialog, NameDialog, UnsavedDialog } from '@/components/server/files/ide/dialogs';
import '@/components/server/files/ide/ide.css';

// Hydra: the file manager as an IDE: folders on the left, the files of the
// selected folder next to them and the opened files as tabs on the right.

const WIDTHS_KEY = 'hydra:files:widths';

const readWidths = (): [number, number] => {
    try {
        const value = JSON.parse(localStorage.getItem(WIDTHS_KEY) || 'null');
        if (Array.isArray(value) && value.length === 2) return [Number(value[0]) || 240, Number(value[1]) || 300];
    } catch {
        // Only a convenience.
    }

    return [240, 300];
};

type DialogState =
    | { type: 'create'; directory: string; kind: 'file' | 'folder' }
    | { type: 'rename'; path: string }
    | { type: 'delete'; paths: string[] }
    | { type: 'chmod'; path: string; mode: string }
    | { type: 'unsaved'; id: string }
    | { type: 'trash' }
    | null;

let nextTabId = 0;

const Resizer = ({ onResize }: { onResize: (delta: number) => void }) => {
    const start = (event: React.PointerEvent) => {
        event.preventDefault();
        let last = event.clientX;
        const move = (e: PointerEvent) => {
            onResize(e.clientX - last);
            last = e.clientX;
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            document.body.style.cursor = '';
        };
        document.body.style.cursor = 'col-resize';
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    };

    return (
        <div
            role={'separator'}
            aria-orientation={'vertical'}
            onPointerDown={start}
            className={
                'hidden w-1 shrink-0 cursor-col-resize bg-gray-700/60 transition-colors hover:bg-primary-500 md:block'
            }
        />
    );
};

const ToolbarButton = ({
    icon: Icon,
    label,
    onClick,
    disabled,
    badge,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    onClick: () => void;
    disabled?: boolean;
    badge?: number;
}) => (
    <button
        type={'button'}
        onClick={onClick}
        disabled={disabled}
        title={label}
        className={
            'relative flex items-center gap-1.5 rounded border border-gray-600 px-2.5 py-1.5 text-xs text-gray-200 hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40'
        }
    >
        <Icon className={'h-4 w-4'} />
        <span className={'hidden lg:inline'}>{label}</span>
        {!!badge && (
            <span
                className={
                    'absolute -right-1.5 -top-1.5 min-w-[1.1rem] rounded-full bg-primary-500 px-1 text-2xs leading-[1.1rem] text-white'
                }
            >
                {badge > 99 ? '99+' : badge}
            </span>
        )}
    </button>
);

export default () => {
    const { t } = useTranslation('client/files');
    const history = useHistory();
    const location = useLocation();
    // Old links to /files/new end up here as ?new=1 (see FileRedirect).
    const createOnStart = new URLSearchParams(location.search).has('new');
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const allowedPaths = ServerContext.useStoreState((state) => state.server.allowedPaths);
    const trashSettings = useStoreState((state) => state.settings.data!.trash);
    const [canRead, canCreate, canUpdate, canDelete, canDeletePermanent, canArchive] = usePermissions([
        'file.read-content',
        'file.create',
        'file.update',
        'file.delete',
        'file.delete-permanent',
        'file.archive',
    ]);
    const { addError, clearFlashes } = useFlash();
    const transfers = useFileTransfers();
    const upload = useUploads();

    const [listings, setListings] = useState<Record<string, FileObject[] | undefined>>({});
    const [failed, setFailed] = useState<Record<string, string | undefined>>({});
    const [expanded, setExpanded] = useState<string[]>(['/']);
    const [directory, setDirectory] = useState('/');
    const [selection, setSelection] = useState<string[]>([]);
    const [cut, setCut] = useState<string[]>([]);
    const [tabs, setTabs] = useState<EditorTab[]>([]);
    const [active, setActive] = useState<string | null>(null);
    const [menu, setMenu] = useState<{ x: number; y: number; target: MenuTarget } | null>(null);
    const [dialog, setDialog] = useState<DialogState>(null);
    const [trashCount, setTrashCount] = useState(0);
    const [widths, setWidths] = useState(readWidths);
    const [pane, setPane] = useState<'tree' | 'list' | 'editor'>('list');
    const [initialized, setInitialized] = useState(false);
    const listingsRef = useRef(listings);
    listingsRef.current = listings;
    const lastHash = useRef<string | null>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const folderInput = useRef<HTMLInputElement>(null);
    const uploadTarget = useRef('/');

    const flashError = (error: unknown) => {
        console.error(error);
        addError({ key: 'files', message: httpErrorToHuman(error) });
    };

    const canWriteIn = useCallback((path: string) => isPathAllowed(allowedPaths, path), [allowedPaths]);

    // ---- folders ------------------------------------------------------------

    const load = useCallback(
        async (dir: string, force = false) => {
            const path = normalize(dir);
            if (!force && listingsRef.current[path]) return listingsRef.current[path];
            try {
                const files = await loadDirectory(uuid, path);
                setListings((current) => ({ ...current, [path]: files }));
                setFailed((current) => ({ ...current, [path]: undefined }));
                return files;
            } catch (error) {
                setFailed((current) => ({ ...current, [path]: httpErrorToHuman(error) }));
                return undefined;
            }
        },
        [uuid]
    );

    const refresh = useCallback(
        (...dirs: string[]) => {
            Array.from(new Set(dirs.map(normalize))).forEach((dir) => {
                if (listingsRef.current[dir] || dir === '/') load(dir, true);
            });
        },
        [load]
    );

    const refreshAll = () => {
        Object.keys(listingsRef.current).forEach((dir) => load(dir, true));
        getTrash(uuid)
            .then((data) => setTrashCount(data.entries.length))
            .catch(() => undefined);
    };

    useEffect(() => {
        const onRefresh = () => refreshAll();
        window.addEventListener(FILES_REFRESH_EVENT, onRefresh);
        return () => window.removeEventListener(FILES_REFRESH_EVENT, onRefresh);
    }, [uuid]);

    const toggleExpanded = (path: string, open?: boolean) =>
        setExpanded((current) => {
            const isOpen = current.includes(path);
            const next = open ?? !isOpen;
            if (next === isOpen) return current;
            return next ? [...current, path] : current.filter((p) => p !== path);
        });

    const openDirectory = (path: string) => {
        const dir = normalize(path);
        setDirectory(dir);
        setSelection([]);
        setExpanded((current) =>
            Array.from(new Set([...current, ...ancestors(dirname(dir)), ...(dir === '/' ? ['/'] : [])]))
        );
        load(dir);
        setPane('list');
    };

    // ---- tabs ---------------------------------------------------------------

    const openFile: IdeContextValue['openFile'] = (path, file, forceText) => {
        const target = normalize(path);
        const existing = tabs.find((tab) => tab.path === target);
        if (existing && (!forceText || existing.forceText)) {
            setActive(existing.id);
            setPane('editor');
            return;
        }
        const info = file || listingsRef.current[dirname(target)]?.find((entry) => entry.name === basename(target));
        const kind = fileKind(info || { name: basename(target), mimetype: '', size: 1 });
        const tab: EditorTab = { id: `tab-${++nextTabId}`, path: target, kind, dirty: false, forceText };
        setTabs((current) => (existing ? current.map((t) => (t.id === existing.id ? tab : t)) : [...current, tab]));
        setActive(tab.id);
        setPane('editor');
    };

    const closeTab = (tabId: string, force = false) => {
        const tab = tabs.find((t) => t.id === tabId);
        if (!tab) return;
        if (tab.dirty && !force) {
            setDialog({ type: 'unsaved', id: tabId });
            return;
        }
        const index = tabs.indexOf(tab);
        const rest = tabs.filter((t) => t.id !== tabId);
        setTabs(rest);
        if (active === tabId) setActive(rest[Math.min(index, rest.length - 1)]?.id ?? null);
    };

    const setDirty = useCallback(
        (tabId: string, dirty: boolean) =>
            setTabs((current) =>
                current.some((t) => t.id === tabId && t.dirty !== dirty)
                    ? current.map((t) => (t.id === tabId ? { ...t, dirty } : t))
                    : current
            ),
        []
    );

    // Paths of open tabs and folders follow renamed and moved files.
    const followMove = (from: string, to: string) => {
        setTabs((current) =>
            current.map((tab) => (isInside(tab.path, from) ? { ...tab, path: rebase(tab.path, from, to) } : tab))
        );
        setExpanded((current) => current.map((p) => (p !== '/' && isInside(p, from) ? rebase(p, from, to) : p)));
        setDirectory((current) => (current !== '/' && isInside(current, from) ? rebase(current, from, to) : current));
    };

    const forgetRemoved = (path: string) => {
        setTabs((current) => {
            const rest = current.filter((tab) => !isInside(tab.path, path));
            if (rest.length !== current.length) {
                setActive((a) => (rest.some((tab) => tab.id === a) ? a : rest[rest.length - 1]?.id ?? null));
            }
            return rest;
        });
        setExpanded((current) => current.filter((p) => p === '/' || !isInside(p, path)));
        setDirectory((current) => (current !== '/' && isInside(current, path) ? dirname(path) : current));
    };

    // ---- actions ------------------------------------------------------------

    const move = (paths: string[], target: string) => {
        const to = normalize(target);
        const files = paths
            .map(normalize)
            .filter((from) => from !== '/' && dirname(from) !== to && !isInside(to, from))
            .map((from) => ({ from, to: joinPath(to, basename(from)) }));
        setCut([]);
        if (files.length === 0) return;
        clearFlashes('files');
        renameFiles(uuid, '/', files)
            .then(() => {
                files.forEach(({ from, to }) => followMove(from, to));
                setSelection([]);
                refresh(to, ...files.map(({ from }) => dirname(from)));
            })
            .catch((error) => {
                flashError(error);
                refresh(to, ...files.map(({ from }) => dirname(from)));
            });
    };

    const doDelete = (paths: string[]) => {
        setDialog(null);
        clearFlashes('files');
        const byDir = new Map<string, string[]>();
        paths.forEach((path) => byDir.set(dirname(path), [...(byDir.get(dirname(path)) || []), basename(path)]));
        // Remove them from the view right away, the listing is reloaded afterwards.
        setListings((current) => {
            const next = { ...current };
            byDir.forEach((names, dir) => {
                next[dir] = next[dir]?.filter((entry) => !names.includes(entry.name));
            });
            return next;
        });
        paths.forEach(forgetRemoved);
        setSelection([]);
        Promise.all(Array.from(byDir.entries()).map(([dir, names]) => deleteFiles(uuid, dir, names)))
            .then(() => {
                if (trashSettings.enabled) setTrashCount((count) => count + paths.length);
            })
            .catch(flashError)
            .then(() => refresh(...Array.from(byDir.keys())));
    };

    const doCreate = (dir: string, kind: 'file' | 'folder', name: string) => {
        setDialog(null);
        clearFlashes('files');
        const path = joinPath(dir, name);
        const request =
            kind === 'folder' ? createDirectory(uuid, dirname(path), basename(path)) : saveFileContents(uuid, path, '');
        request
            .then(() => {
                refresh(...ancestors(dirname(path)).filter((p) => isInside(p, dir)));
                if (kind === 'file') {
                    if (dirname(path) !== directory) openDirectory(dirname(path));
                    openFile(path, { name: basename(path), mimetype: 'text/plain', size: 0 });
                } else {
                    toggleExpanded(dir, true);
                }
            })
            .catch(flashError);
    };

    const doRename = (path: string, name: string) => {
        setDialog(null);
        if (name === basename(path)) return;
        clearFlashes('files');
        const dir = dirname(path);
        renameFiles(uuid, dir, [{ from: basename(path), to: name }])
            .then(() => {
                followMove(path, joinPath(dir, name));
                setSelection([joinPath(dir, name)]);
                refresh(dir);
            })
            .catch(flashError);
    };

    const uploadSelection = (dir: string, selection: UploadSelection) => {
        clearFlashes('files');
        upload(dir, selection)
            .catch(flashError)
            .then(() => {
                // New folders show up in the tree as well.
                const folders = new Set<string>([dir]);
                selection.items.forEach((item) => {
                    const parts = item.path.split('/');
                    parts.pop();
                    parts.forEach((_, i) => folders.add(joinPath(dir, parts.slice(0, i + 1).join('/'))));
                });
                refresh(...Array.from(folders));
            });
    };

    const uploadTo = (dir: string, transfer: DataTransfer) => {
        if (!canCreate) return;
        selectionFromDataTransfer(transfer)
            .then((selection) => uploadSelection(dir, selection))
            .catch(flashError);
    };

    const pickUpload = (dir: string, folder: boolean) => {
        uploadTarget.current = dir;
        (folder ? folderInput : fileInput).current?.click();
    };

    const archive = (paths: string[]) => {
        const dir = dirname(paths[0]);
        clearFlashes('files');
        transfers
            .archive(dir, paths.map(basename))
            .then(() => refresh(dir))
            .catch(flashError);
    };

    const showMenu = (event: React.MouseEvent, target: MenuTarget) => {
        event.preventDefault();
        event.stopPropagation();
        setMenu({ x: event.clientX, y: event.clientY, target });
    };

    const context: IdeContextValue = {
        uuid,
        can: {
            read: canRead,
            create: canCreate,
            update: canUpdate,
            delete: canDelete,
            deletePermanent: canDeletePermanent,
            archive: canArchive,
        },
        canWriteIn,
        trashEnabled: trashSettings.enabled,
        listings,
        failed,
        load,
        refresh,
        expanded,
        toggleExpanded,
        directory,
        openDirectory,
        selection,
        setSelection,
        cut,
        setCut,
        tabs,
        active,
        openFile,
        closeTab,
        setActive,
        setDirty,
        showMenu,
        move,
        uploadTo,
        pickUpload,
        remove: (paths) => paths.length > 0 && setDialog({ type: 'delete', paths }),
        rename: (path) => setDialog({ type: 'rename', path }),
        create: (dir, kind) => setDialog({ type: 'create', directory: dir, kind }),
    };

    // ---- start, address and leaving -------------------------------------------

    const resolveHash = async (hash: string) => {
        const path = normalize(hashToPath(hash));
        if (path === '/') {
            openDirectory('/');
            return;
        }
        const parent = await load(dirname(path));
        const entry = parent?.find((file) => file.name === basename(path));
        if (entry?.isFile) {
            openDirectory(dirname(path));
            setSelection([path]);
            openFile(path, entry);
        } else if (!parent) {
            // The folder is gone (e.g. an old link), start at the root.
            openDirectory('/');
        } else {
            openDirectory(entry ? path : dirname(path));
        }
    };

    useEffect(() => {
        clearFlashes('files');
        getTrash(uuid)
            .then((data) => setTrashCount(data.entries.length))
            .catch(() => undefined);
        resolveHash(location.hash).then(() => {
            if (createOnStart) {
                const path = normalize(hashToPath(location.hash));
                setDialog({ type: 'create', directory: path, kind: 'file' });
            }
            setInitialized(true);
        });
    }, [uuid]);

    // Hash links (e.g. to allowed folders) open the folder or file.
    useEffect(() => {
        if (!initialized || location.hash === lastHash.current) return;
        resolveHash(location.hash);
    }, [location.hash]);

    const activeTab = tabs.find((tab) => tab.id === active);
    useEffect(() => {
        if (!initialized) return;
        const hash = `#${encodePathSegments(activeTab?.path || directory)}`;
        lastHash.current = hash;
        if (location.search || location.hash !== hash) {
            history.replace(`/server/${id}/files${hash}`);
        }
    }, [initialized, directory, activeTab?.path]);

    const dirty = tabs.some((tab) => tab.dirty);
    useEffect(() => {
        if (!dirty) return;
        const warn = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    useEffect(() => {
        try {
            localStorage.setItem(WIDTHS_KEY, JSON.stringify(widths));
        } catch {
            // Only a convenience.
        }
    }, [widths]);

    // ---- context menu -------------------------------------------------------

    const menuItems = useMemo((): MenuItem[] => {
        if (!menu) return [];
        const { target } = menu;
        const items: MenuItem[] = [];
        const writable = canWriteIn(target.type === 'file' ? dirname(target.path) : target.path);

        if (target.type === 'file') {
            const paths = selection.includes(target.path) && selection.length > 1 ? selection : [target.path];
            const multiple = paths.length > 1;
            if (!multiple && canRead)
                items.push({
                    label: t('actions.open'),
                    icon: ExternalLink,
                    onClick: () => openFile(target.path, target.file),
                });
            if (!multiple && fileKind(target.file) !== 'text' && canRead) {
                items.push({
                    label: t('preview.edit_text'),
                    icon: Pencil,
                    onClick: () => openFile(target.path, target.file, true),
                });
            }
            if (!multiple && canRead) {
                items.push({
                    label: t('actions.download'),
                    icon: Download,
                    onClick: () =>
                        transfers.download(target.path, target.file.name, target.file.size).catch(flashError),
                });
            }
            if (canUpdate && writable) {
                if (!multiple)
                    items.push({
                        label: t('actions.rename'),
                        icon: Pencil,
                        shortcut: 'F2',
                        onClick: () => context.rename(target.path),
                        separated: true,
                    });
                items.push({
                    label: t('actions.cut'),
                    icon: Scissors,
                    shortcut: 'Ctrl+X',
                    onClick: () => setCut(paths),
                    separated: multiple,
                });
            }
            if (!multiple && canCreate && writable) {
                items.push({
                    label: t('actions.duplicate'),
                    icon: Copy,
                    onClick: () =>
                        copyFile(uuid, target.path)
                            .then(() => refresh(dirname(target.path)))
                            .catch(flashError),
                });
            }
            if (!multiple && target.file.isArchiveType() && canCreate && writable) {
                items.push({
                    label: t('actions.unarchive'),
                    icon: ArchiveRestore,
                    onClick: () =>
                        transfers
                            .unarchive(dirname(target.path), target.file.name)
                            .then(() => refresh(dirname(target.path)))
                            .catch(flashError),
                });
            }
            if (canArchive && writable)
                items.push({ label: t('actions.archive'), icon: Archive, onClick: () => archive(paths) });
            if (!multiple && canUpdate && writable) {
                items.push({
                    label: t('actions.permissions'),
                    icon: KeyRound,
                    onClick: () =>
                        setDialog({ type: 'chmod', path: target.path, mode: target.file.modeBits.slice(-3) }),
                });
            }
            if (canDelete && writable) {
                items.push({
                    label: t('actions.delete'),
                    icon: Trash2,
                    shortcut: 'Del',
                    danger: true,
                    separated: true,
                    onClick: () => context.remove(paths),
                });
            }
            return items;
        }

        const dir = target.path;
        if (canCreate && writable) {
            items.push({ label: t('actions.new_file'), icon: FilePlus, onClick: () => context.create(dir, 'file') });
            items.push({
                label: t('actions.new_folder'),
                icon: FolderPlus,
                onClick: () => context.create(dir, 'folder'),
            });
            items.push({
                label: t('actions.upload_files'),
                icon: Upload,
                onClick: () => pickUpload(dir, false),
                separated: true,
            });
            items.push({ label: t('actions.upload_folder'), icon: FolderUp, onClick: () => pickUpload(dir, true) });
        }
        if (cut.length > 0 && canUpdate && writable) {
            items.push({
                label: t('actions.paste', { count: cut.length }),
                icon: ClipboardPaste,
                shortcut: 'Ctrl+V',
                onClick: () => move(cut, dir),
                separated: true,
            });
        }
        if (target.type === 'folder' && dir !== '/') {
            const parentWritable = canWriteIn(dirname(dir));
            if (canUpdate && parentWritable) {
                items.push({
                    label: t('actions.rename'),
                    icon: Pencil,
                    onClick: () => context.rename(dir),
                    separated: true,
                });
                items.push({ label: t('actions.cut'), icon: Scissors, onClick: () => setCut([dir]) });
            }
            if (canArchive && parentWritable)
                items.push({ label: t('actions.archive'), icon: Archive, onClick: () => archive([dir]) });
            if (canUpdate && parentWritable) {
                const mode =
                    listings[dirname(dir)]?.find((entry) => entry.name === basename(dir))?.modeBits.slice(-3) || '755';
                items.push({
                    label: t('actions.permissions'),
                    icon: KeyRound,
                    onClick: () => setDialog({ type: 'chmod', path: dir, mode }),
                });
            }
            if (canDelete && parentWritable) {
                items.push({
                    label: t('actions.delete'),
                    icon: Trash2,
                    danger: true,
                    separated: true,
                    onClick: () => context.remove([dir]),
                });
            }
        }
        items.push({ label: t('actions.refresh'), icon: RefreshCw, onClick: () => refresh(dir), separated: true });

        return items;
    }, [menu, selection, cut, listings, canRead, canCreate, canUpdate, canDelete, canArchive]);

    // ---- render -------------------------------------------------------------

    const crumbs = ancestors(directory);
    const writableHere = canWriteIn(directory);
    const closingTab = dialog?.type === 'unsaved' ? tabs.find((tab) => tab.id === dialog.id) : undefined;

    return (
        <ServerContentBlock title={t('title')} showFlashKey={'files'} className={'!max-w-none'}>
            <IdeContext.Provider value={context}>
                {allowedPaths.length > 0 && (
                    <div className={'mb-3 border-l-4 border-primary-400 py-1.5 pl-4'}>
                        <p className={'text-sm text-gray-300'}>{t('restricted')}</p>
                        <div className={'mt-1.5 flex flex-wrap gap-2'}>
                            {allowedPaths.map((path) => (
                                <NavLink
                                    key={path}
                                    to={`/server/${id}/files#${encodePathSegments(path)}`}
                                    className={
                                        'rounded bg-gray-700 px-2 py-0.5 font-mono text-xs text-gray-100 hover:bg-gray-600'
                                    }
                                >
                                    {path}
                                </NavLink>
                            ))}
                        </div>
                    </div>
                )}
                <div className={'mb-3 flex flex-wrap items-center gap-2'}>
                    <nav
                        className={'flex min-w-0 flex-1 flex-wrap items-center gap-0.5 font-mono text-sm text-gray-300'}
                        aria-label={t('breadcrumbs')}
                    >
                        {crumbs.map((crumb, index) => (
                            <React.Fragment key={crumb}>
                                {index > 1 && <span className={'text-gray-600'}>/</span>}
                                <button
                                    type={'button'}
                                    onClick={() => openDirectory(crumb)}
                                    className={classNames(
                                        'rounded px-1 hover:bg-gray-700 hover:text-gray-50',
                                        index === crumbs.length - 1 && 'text-gray-50'
                                    )}
                                >
                                    {crumb === '/' ? t('tree.root') : basename(crumb)}
                                </button>
                            </React.Fragment>
                        ))}
                    </nav>
                    {canCreate && (
                        <>
                            <ToolbarButton
                                icon={FilePlus}
                                label={t('actions.new_file')}
                                disabled={!writableHere}
                                onClick={() => context.create(directory, 'file')}
                            />
                            <ToolbarButton
                                icon={FolderPlus}
                                label={t('actions.new_folder')}
                                disabled={!writableHere}
                                onClick={() => context.create(directory, 'folder')}
                            />
                            <ToolbarButton
                                icon={Upload}
                                label={t('actions.upload_files')}
                                disabled={!writableHere}
                                onClick={() => pickUpload(directory, false)}
                            />
                            <ToolbarButton
                                icon={FolderUp}
                                label={t('actions.upload_folder')}
                                disabled={!writableHere}
                                onClick={() => pickUpload(directory, true)}
                            />
                        </>
                    )}
                    {(trashSettings.enabled || trashCount > 0) && (
                        <ToolbarButton
                            icon={Trash2}
                            label={t('trash.title')}
                            badge={trashCount}
                            onClick={() => setDialog({ type: 'trash' })}
                        />
                    )}
                    <ToolbarButton icon={RefreshCw} label={t('actions.refresh')} onClick={refreshAll} />
                </div>
                <div className={'mb-2 flex gap-1 rounded-lg bg-gray-800 p-1 md:hidden'}>
                    {(['tree', 'list', 'editor'] as const).map((key) => (
                        <button
                            key={key}
                            type={'button'}
                            onClick={() => setPane(key)}
                            className={classNames(
                                'flex-1 rounded px-2 py-1 text-xs',
                                pane === key ? 'bg-gray-700 text-gray-50' : 'text-gray-400'
                            )}
                        >
                            {t(`panes.${key}`)}
                            {key === 'editor' && tabs.length > 0 && ` (${tabs.length})`}
                        </button>
                    ))}
                </div>
                <div
                    className={
                        'flex h-[calc(var(--hydra-vh)-15rem)] min-h-[28rem] overflow-hidden rounded-lg border border-gray-700 bg-gray-800/90 backdrop-blur'
                    }
                    onDragOver={(event) => {
                        // Files dropped anywhere else go into the open folder.
                        if (isExternalDrag(event) && canCreate && writableHere) event.preventDefault();
                    }}
                    onDrop={(event) => {
                        if (!isExternalDrag(event) || !canCreate || !writableHere) return;
                        event.preventDefault();
                        uploadTo(directory, event.dataTransfer);
                    }}
                    onKeyDown={(event) => {
                        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') event.preventDefault();
                    }}
                >
                    <div
                        style={{ width: widths[0] }}
                        className={classNames(
                            'shrink-0 overflow-auto max-md:!w-full',
                            pane !== 'tree' && 'max-md:hidden'
                        )}
                        onContextMenu={(event) => showMenu(event, { type: 'background', path: '/' })}
                    >
                        <FolderTree />
                    </div>
                    <Resizer
                        onResize={(delta) => setWidths(([a, b]) => [Math.max(160, Math.min(520, a + delta)), b])}
                    />
                    <div
                        style={{ width: widths[1] }}
                        className={classNames(
                            'shrink-0 overflow-hidden max-md:!w-full',
                            pane !== 'list' && 'max-md:hidden'
                        )}
                    >
                        <FileList />
                    </div>
                    <Resizer
                        onResize={(delta) => setWidths(([a, b]) => [a, Math.max(200, Math.min(640, b + delta))])}
                    />
                    <div className={classNames('min-w-0 flex-1', pane !== 'editor' && 'max-md:hidden')}>
                        <EditorArea />
                    </div>
                </div>

                <input
                    ref={fileInput}
                    type={'file'}
                    multiple
                    className={'hidden'}
                    onChange={(event) => {
                        if (event.currentTarget.files)
                            uploadSelection(uploadTarget.current, selectionFromInput(event.currentTarget.files));
                        event.currentTarget.value = '';
                    }}
                />
                <input
                    ref={folderInput}
                    type={'file'}
                    multiple
                    className={'hidden'}
                    // @ts-expect-error not in the React types
                    webkitdirectory={''}
                    onChange={(event) => {
                        if (event.currentTarget.files)
                            uploadSelection(uploadTarget.current, selectionFromInput(event.currentTarget.files));
                        event.currentTarget.value = '';
                    }}
                />

                {menu && menuItems.length > 0 && (
                    <ContextMenu x={menu.x} y={menu.y} items={menuItems} onClose={() => setMenu(null)} />
                )}

                <NameDialog
                    open={dialog?.type === 'create'}
                    title={
                        dialog?.type === 'create' && dialog.kind === 'folder'
                            ? t('dialogs.new_folder')
                            : t('dialogs.new_file')
                    }
                    label={t('dialogs.name')}
                    initial={''}
                    confirm={t('dialogs.create')}
                    allowSlash
                    onClose={() => setDialog(null)}
                    onSubmit={(name) => dialog?.type === 'create' && doCreate(dialog.directory, dialog.kind, name)}
                />
                <NameDialog
                    open={dialog?.type === 'rename'}
                    title={t('dialogs.rename')}
                    label={t('dialogs.name')}
                    initial={dialog?.type === 'rename' ? basename(dialog.path) : ''}
                    confirm={t('dialogs.rename_confirm')}
                    onClose={() => setDialog(null)}
                    onSubmit={(name) => dialog?.type === 'rename' && doRename(dialog.path, name)}
                />
                <DeleteDialog
                    open={dialog?.type === 'delete'}
                    names={dialog?.type === 'delete' ? dialog.paths.map(basename) : []}
                    trashEnabled={trashSettings.enabled}
                    onClose={() => setDialog(null)}
                    onConfirm={() => dialog?.type === 'delete' && doDelete(dialog.paths)}
                />
                <ChmodDialog
                    open={dialog?.type === 'chmod'}
                    name={dialog?.type === 'chmod' ? basename(dialog.path) : ''}
                    mode={dialog?.type === 'chmod' ? dialog.mode : '644'}
                    onClose={() => setDialog(null)}
                    onSubmit={(mode) => {
                        if (dialog?.type !== 'chmod') return;
                        const path = dialog.path;
                        setDialog(null);
                        chmodFiles(uuid, dirname(path), [{ file: basename(path), mode }])
                            .then(() => refresh(dirname(path)))
                            .catch(flashError);
                    }}
                />
                <UnsavedDialog
                    open={!!closingTab}
                    name={closingTab ? basename(closingTab.path) : ''}
                    onClose={() => setDialog(null)}
                    onDiscard={() => {
                        if (closingTab) closeTab(closingTab.id, true);
                        setDialog(null);
                    }}
                />
                <TrashDialog
                    open={dialog?.type === 'trash'}
                    onClose={() => setDialog(null)}
                    onChanged={setTrashCount}
                />
            </IdeContext.Provider>
        </ServerContentBlock>
    );
};
