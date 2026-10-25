import React, { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import {
    Database,
    File,
    FileArchive,
    FileAudio,
    FileCode,
    FileImage,
    FileQuestion,
    FileText,
    FileVideo,
    Loader2,
    Search,
    Upload,
} from 'lucide-react';
import { FileObject } from '@/api/server/files/loadDirectory';
import { bytesToString } from '@/lib/formatters';
import { formatDate, formatDistanceToNow } from '@/lib/date';
import { DRAG_TYPE, isExternalDrag, isInternalDrag, useIde } from '@/components/server/files/ide/context';
import { FileKind, fileKind } from '@/components/server/files/ide/fileTypes';
import { basename, joinPath } from '@/components/server/files/ide/paths';

// Hydra: middle column of the file manager, the files of the selected folder.

const icons: Record<FileKind, React.ComponentType<{ className?: string }>> = {
    text: FileText,
    image: FileImage,
    video: FileVideo,
    audio: FileAudio,
    nbt: FileCode,
    sqlite: Database,
    archive: FileArchive,
    binary: FileQuestion,
};

export const FileIcon = ({ file, className }: { file: FileObject; className?: string }) => {
    const Icon = file.isFile ? icons[fileKind(file)] || File : File;

    return <Icon className={className} />;
};

export default () => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const [filter, setFilter] = useState('');
    const [dropping, setDropping] = useState(false);
    const anchor = useRef<string | null>(null);
    const listing = ide.listings[ide.directory];
    const error = ide.failed[ide.directory];

    const files = useMemo(
        () =>
            (listing || [])
                .filter((entry) => entry.isFile)
                .filter((entry) => entry.name.toLowerCase().includes(filter.trim().toLowerCase()))
                .sort((a, b) => a.name.localeCompare(b.name, undefined, { numeric: true })),
        [listing, filter]
    );
    const paths = files.map((file) => joinPath(ide.directory, file.name));
    const writable = ide.canWriteIn(ide.directory);

    const onRowClick = (event: React.MouseEvent, file: FileObject, path: string) => {
        if (event.ctrlKey || event.metaKey) {
            ide.setSelection(
                ide.selection.includes(path) ? ide.selection.filter((p) => p !== path) : [...ide.selection, path]
            );
            anchor.current = path;
            return;
        }
        if (event.shiftKey && anchor.current && paths.includes(anchor.current)) {
            const [from, to] = [paths.indexOf(anchor.current), paths.indexOf(path)].sort((a, b) => a - b);
            ide.setSelection(paths.slice(from, to + 1));
            return;
        }
        anchor.current = path;
        ide.setSelection([path]);
        if (ide.can.read) ide.openFile(path, file);
    };

    const onKeyDown = (event: React.KeyboardEvent) => {
        const mod = event.ctrlKey || event.metaKey;
        if (event.target instanceof HTMLInputElement) return;
        if (mod && event.key.toLowerCase() === 'a') {
            event.preventDefault();
            ide.setSelection(paths);
        } else if (event.key === 'Delete' && ide.selection.length > 0 && ide.can.delete) {
            event.preventDefault();
            ide.remove(ide.selection);
        } else if (event.key === 'F2' && ide.selection.length === 1 && ide.can.update) {
            event.preventDefault();
            ide.rename(ide.selection[0]);
        } else if (mod && event.key.toLowerCase() === 'x' && ide.selection.length > 0 && ide.can.update) {
            event.preventDefault();
            ide.setCut(ide.selection);
        } else if (mod && event.key.toLowerCase() === 'v' && ide.cut.length > 0 && writable) {
            event.preventDefault();
            ide.move(ide.cut, ide.directory);
        } else if (event.key === 'Enter' && ide.selection.length === 1) {
            const file = files.find((f) => joinPath(ide.directory, f.name) === ide.selection[0]);
            if (file && ide.can.read) ide.openFile(ide.selection[0], file);
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const current = paths.indexOf(ide.selection[ide.selection.length - 1] ?? '');
            const next = Math.max(0, Math.min(paths.length - 1, current + (event.key === 'ArrowDown' ? 1 : -1)));
            if (paths[next]) {
                ide.setSelection([paths[next]]);
                anchor.current = paths[next];
                document.getElementById(`hydra-file-${paths[next]}`)?.scrollIntoView({ block: 'nearest' });
            }
        }
    };

    return (
        <div
            className={classNames('flex h-full flex-col', dropping && 'ring-2 ring-inset ring-primary-400')}
            onDragOver={(event) => {
                if (!writable || (!isExternalDrag(event) && !isInternalDrag(event))) return;
                event.preventDefault();
                event.dataTransfer.dropEffect = isInternalDrag(event) ? 'move' : 'copy';
                setDropping(true);
            }}
            onDragLeave={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget as Node)) setDropping(false);
            }}
            onDrop={(event) => {
                setDropping(false);
                if (!writable) return;
                event.preventDefault();
                event.stopPropagation();
                if (isInternalDrag(event)) {
                    ide.move(JSON.parse(event.dataTransfer.getData(DRAG_TYPE) || '[]'), ide.directory);
                } else if (isExternalDrag(event)) {
                    ide.uploadTo(ide.directory, event.dataTransfer);
                }
            }}
        >
            <div className={'border-b border-gray-700 p-2'}>
                <p className={'mb-1.5 truncate px-1 font-mono text-xs text-gray-400'} title={ide.directory}>
                    {ide.directory}
                </p>
                <div className={'relative'}>
                    <Search className={'absolute left-2 top-2 h-3.5 w-3.5 text-gray-400'} />
                    <input
                        type={'search'}
                        value={filter}
                        onChange={(event) => setFilter(event.currentTarget.value)}
                        placeholder={t('list.filter')}
                        className={
                            'w-full rounded border border-gray-600 bg-gray-700 py-1 pl-7 pr-2 text-xs text-gray-100 outline-none focus:border-primary-400'
                        }
                    />
                </div>
            </div>
            <div
                role={'listbox'}
                aria-multiselectable
                tabIndex={0}
                onKeyDown={onKeyDown}
                onContextMenu={(event) => ide.showMenu(event, { type: 'background', path: ide.directory })}
                onClick={(event) => {
                    if (event.target === event.currentTarget) ide.setSelection([]);
                }}
                className={'flex-1 overflow-y-auto p-1 outline-none'}
            >
                {error ? (
                    <p className={'p-4 text-center text-xs text-red-400'}>{error}</p>
                ) : !listing ? (
                    <div className={'flex justify-center p-6'}>
                        <Loader2 className={'h-5 w-5 animate-spin text-gray-400'} />
                    </div>
                ) : files.length === 0 ? (
                    <div
                        className={
                            'pointer-events-none flex flex-col items-center gap-2 p-6 text-center text-xs text-gray-500'
                        }
                    >
                        <Upload className={'h-6 w-6'} />
                        {filter
                            ? t('list.no_match')
                            : writable && ide.can.create
                            ? t('list.empty_drop')
                            : t('list.empty')}
                    </div>
                ) : (
                    files.map((file, index) => {
                        const path = paths[index];
                        const selected = ide.selection.includes(path);
                        const open = ide.tabs.some((tab) => tab.path === path);

                        return (
                            <div
                                key={file.key}
                                id={`hydra-file-${path}`}
                                role={'option'}
                                aria-selected={selected}
                                draggable={ide.can.update}
                                onDragStart={(event) => {
                                    const dragged = selected ? ide.selection : [path];
                                    event.dataTransfer.setData(DRAG_TYPE, JSON.stringify(dragged));
                                    event.dataTransfer.effectAllowed = 'move';
                                }}
                                onClick={(event) => onRowClick(event, file, path)}
                                onContextMenu={(event) => {
                                    event.stopPropagation();
                                    if (!selected) ide.setSelection([path]);
                                    ide.showMenu(event, { type: 'file', path, file });
                                }}
                                title={`${file.name}\n${bytesToString(file.size)} · ${formatDate(file.modifiedAt)}`}
                                className={classNames(
                                    'flex cursor-pointer select-none items-center gap-2 rounded px-2 py-1 text-sm',
                                    selected ? 'bg-primary-500/20 text-gray-50' : 'text-gray-300 hover:bg-gray-700/60',
                                    ide.cut.includes(path) && 'opacity-50'
                                )}
                            >
                                <FileIcon file={file} className={'h-4 w-4 shrink-0 text-gray-400'} />
                                <span className={classNames('min-w-0 flex-1 truncate', open && 'font-medium')}>
                                    {basename(path)}
                                </span>
                                <span className={'hidden shrink-0 text-2xs text-gray-500 xl:inline'}>
                                    {formatDistanceToNow(file.modifiedAt)}
                                </span>
                                <span className={'w-14 shrink-0 text-right text-2xs text-gray-500'}>
                                    {bytesToString(file.size, 1)}
                                </span>
                            </div>
                        );
                    })
                )}
            </div>
            {listing && (
                <p className={'border-t border-gray-700 px-3 py-1 text-2xs text-gray-500'}>
                    {ide.selection.length > 1
                        ? t('list.selected', { count: ide.selection.length })
                        : t('list.count', { count: files.length })}
                </p>
            )}
        </div>
    );
};
