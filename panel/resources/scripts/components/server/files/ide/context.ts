import React, { createContext, useContext } from 'react';
import { FileObject } from '@/api/server/files/loadDirectory';
import { FileKind } from '@/components/server/files/ide/fileTypes';

// Hydra: shared state of the file manager (folder tree, file list, editor).

export interface EditorTab {
    // Stays the same when the file is renamed or moved.
    id: string;
    path: string;
    kind: FileKind;
    dirty: boolean;
    // Opened as text although the type says otherwise.
    forceText?: boolean;
}

export type MenuTarget =
    | { type: 'file'; path: string; file: FileObject }
    | { type: 'folder'; path: string }
    | { type: 'background'; path: string };

export interface IdeContextValue {
    uuid: string;
    can: {
        read: boolean;
        create: boolean;
        update: boolean;
        delete: boolean;
        archive: boolean;
    };
    // Whether the user may change things in this folder (folder restrictions).
    canWriteIn: (path: string) => boolean;
    trashEnabled: boolean;

    listings: Record<string, FileObject[] | undefined>;
    failed: Record<string, string | undefined>;
    load: (directory: string, force?: boolean) => Promise<FileObject[] | undefined>;
    refresh: (...directories: string[]) => void;

    expanded: string[];
    toggleExpanded: (path: string, open?: boolean) => void;
    directory: string;
    openDirectory: (path: string) => void;

    selection: string[];
    setSelection: (paths: string[]) => void;
    cut: string[];
    setCut: (paths: string[]) => void;

    tabs: EditorTab[];
    active: string | null;
    openFile: (path: string, file?: Pick<FileObject, 'name' | 'mimetype' | 'size'>, forceText?: boolean) => void;
    closeTab: (id: string, force?: boolean) => void;
    setActive: (id: string) => void;
    setDirty: (id: string, dirty: boolean) => void;

    // Actions with dialogs, defined by the container.
    showMenu: (event: React.MouseEvent, target: MenuTarget) => void;
    move: (paths: string[], target: string) => void;
    uploadTo: (directory: string, transfer: DataTransfer) => void;
    pickUpload: (directory: string, folder: boolean) => void;
    remove: (paths: string[], permanent?: boolean) => void;
    rename: (path: string) => void;
    create: (directory: string, kind: 'file' | 'folder') => void;
}

export const IdeContext = createContext<IdeContextValue | null>(null);

export const useIde = (): IdeContextValue => {
    const value = useContext(IdeContext);
    if (!value) throw new Error('useIde must be used inside the file manager');

    return value;
};

// Data type of files and folders dragged inside the file manager.
export const DRAG_TYPE = 'application/x-hydra-files';

export const isExternalDrag = (event: React.DragEvent | DragEvent): boolean =>
    !!event.dataTransfer && Array.from(event.dataTransfer.types).includes('Files');

export const isInternalDrag = (event: React.DragEvent | DragEvent): boolean =>
    !!event.dataTransfer && Array.from(event.dataTransfer.types).includes(DRAG_TYPE);
