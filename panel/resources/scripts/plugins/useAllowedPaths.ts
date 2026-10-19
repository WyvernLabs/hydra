import { ServerContext } from '@/state/server';

/**
 * Hydra: helpers for subusers that may only work in certain folders. Paths are
 * compared the same way the Panel and Wings do, so "/world/../plugins" counts
 * as "/plugins".
 */
export const normalizePath = (path: string): string => {
    const parts: string[] = [];
    path.replace(/\\/g, '/')
        .split('/')
        .forEach((part) => {
            if (part === '' || part === '.') return;
            if (part === '..') {
                parts.pop();
                return;
            }
            parts.push(part);
        });

    return '/' + parts.join('/');
};

/**
 * Whether the path is one of the folders or inside one of them.
 */
export const isPathAllowed = (allowed: string[], path: string): boolean => {
    if (allowed.length === 0) return true;

    const normalized = normalizePath(path);

    return allowed.some((folder) => normalized === folder || normalized.startsWith(folder + '/'));
};

/**
 * Whether every folder of "requested" lies within "allowed".
 */
export const coversPaths = (allowed: string[], requested: string[]): boolean => {
    if (allowed.length === 0) return true;
    if (requested.length === 0) return false;

    return requested.every((path) => isPathAllowed(allowed, path));
};

/**
 * The folders the current user is limited to on this server, and whether the
 * given folder is one they may change things in.
 */
export const useAllowedPaths = (directory?: string): { allowedPaths: string[]; canWrite: boolean } => {
    const allowedPaths = ServerContext.useStoreState((state) => state.server.allowedPaths);

    return {
        allowedPaths,
        canWrite: directory === undefined || isPathAllowed(allowedPaths, directory),
    };
};
