import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronRight, Folder, FolderOpen, HardDrive, Loader2 } from 'lucide-react';
import classNames from 'classnames';
import { DRAG_TYPE, isExternalDrag, isInternalDrag, useIde } from '@/components/server/files/ide/context';
import { basename, joinPath } from '@/components/server/files/ide/paths';

// Hydra: left column of the file manager, the folders of the server as a tree.

const byName = (a: { name: string }, b: { name: string }) => a.name.localeCompare(b.name, undefined, { numeric: true });

const TreeNode = ({ path, depth }: { path: string; depth: number }) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const [dropping, setDropping] = useState(false);
    const open = ide.expanded.includes(path);
    const listing = ide.listings[path];
    const selected = ide.directory === path;
    const folders = (listing || []).filter((entry) => !entry.isFile).sort(byName);

    useEffect(() => {
        if (open && !listing && !ide.failed[path]) ide.load(path);
    }, [open, listing]);

    const onDragOver = (event: React.DragEvent) => {
        if (!isInternalDrag(event) && !isExternalDrag(event)) return;
        if (!ide.canWriteIn(path)) return;
        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = isInternalDrag(event) ? 'move' : 'copy';
        setDropping(true);
    };

    const onDrop = (event: React.DragEvent) => {
        setDropping(false);
        if (!ide.canWriteIn(path)) return;
        event.preventDefault();
        event.stopPropagation();
        if (isInternalDrag(event)) {
            ide.move(JSON.parse(event.dataTransfer.getData(DRAG_TYPE) || '[]'), path);
        } else if (isExternalDrag(event)) {
            ide.uploadTo(path, event.dataTransfer);
        }
    };

    return (
        <li>
            <div
                role={'treeitem'}
                aria-expanded={open}
                aria-selected={selected}
                tabIndex={selected ? 0 : -1}
                draggable={path !== '/' && ide.can.update}
                onDragStart={(event) => {
                    event.dataTransfer.setData(DRAG_TYPE, JSON.stringify([path]));
                    event.dataTransfer.effectAllowed = 'move';
                }}
                onDragOver={onDragOver}
                onDragLeave={() => setDropping(false)}
                onDrop={onDrop}
                onClick={() => {
                    ide.openDirectory(path);
                    if (!open) ide.toggleExpanded(path, true);
                }}
                onContextMenu={(event) => ide.showMenu(event, { type: 'folder', path })}
                onKeyDown={(event) => {
                    if (event.key === 'ArrowRight') ide.toggleExpanded(path, true);
                    if (event.key === 'ArrowLeft') ide.toggleExpanded(path, false);
                }}
                style={{ paddingLeft: `${depth * 0.875 + 0.25}rem` }}
                className={classNames(
                    'group flex cursor-pointer select-none items-center gap-1 rounded py-1 pr-2 text-sm outline-none',
                    selected ? 'bg-primary-500/20 text-gray-50' : 'text-gray-300 hover:bg-gray-700/60',
                    dropping && 'ring-2 ring-inset ring-primary-400'
                )}
            >
                <button
                    type={'button'}
                    tabIndex={-1}
                    aria-label={open ? t('tree.collapse') : t('tree.expand')}
                    onClick={(event) => {
                        event.stopPropagation();
                        ide.toggleExpanded(path);
                    }}
                    className={classNames(
                        'flex h-4 w-4 shrink-0 items-center justify-center rounded text-gray-400 hover:text-gray-100',
                        listing && folders.length === 0 && 'invisible'
                    )}
                >
                    {open && !listing && !ide.failed[path] ? (
                        <Loader2 className={'h-3 w-3 animate-spin'} />
                    ) : (
                        <ChevronRight className={classNames('h-3.5 w-3.5 transition-transform', open && 'rotate-90')} />
                    )}
                </button>
                {path === '/' ? (
                    <HardDrive className={'h-4 w-4 shrink-0 text-gray-400'} />
                ) : open ? (
                    <FolderOpen className={'h-4 w-4 shrink-0 text-primary-400'} />
                ) : (
                    <Folder className={'h-4 w-4 shrink-0 text-primary-400'} />
                )}
                <span className={classNames('truncate', ide.cut.includes(path) && 'opacity-50')}>
                    {path === '/' ? t('tree.root') : basename(path)}
                </span>
            </div>
            {open && listing && folders.length > 0 && (
                <ul role={'group'}>
                    {folders.map((folder) => (
                        <TreeNode key={folder.name} path={joinPath(path, folder.name)} depth={depth + 1} />
                    ))}
                </ul>
            )}
            {open && ide.failed[path] && (
                <p className={'py-1 text-xs text-red-400'} style={{ paddingLeft: `${(depth + 1) * 0.875 + 1.5}rem` }}>
                    {ide.failed[path]}
                </p>
            )}
        </li>
    );
};

export default () => (
    <ul role={'tree'} className={'p-1'}>
        <TreeNode path={'/'} depth={0} />
    </ul>
);
