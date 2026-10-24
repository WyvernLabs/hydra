// Hydra: path helpers of the file manager. Paths always start with "/" and
// never end with one, except for the root itself.

export const normalize = (path: string): string => {
    const parts: string[] = [];
    path.split('/').forEach((part) => {
        if (part === '' || part === '.') return;
        if (part === '..') parts.pop();
        else parts.push(part);
    });

    return '/' + parts.join('/');
};

export const joinPath = (...parts: string[]): string => normalize(parts.join('/'));

export const dirname = (path: string): string => {
    const normalized = normalize(path);

    return normalized.slice(0, normalized.lastIndexOf('/')) || '/';
};

export const basename = (path: string): string => {
    const normalized = normalize(path);

    return normalized.slice(normalized.lastIndexOf('/') + 1);
};

/**
 * Whether path is folder or inside it.
 */
export const isInside = (path: string, folder: string): boolean => {
    const p = normalize(path);
    const f = normalize(folder);

    return f === '/' || p === f || p.startsWith(f + '/');
};

/**
 * Replaces the folder "from" at the start of path with "to".
 */
export const rebase = (path: string, from: string, to: string): string =>
    normalize(normalize(to) + normalize(path).slice(normalize(from).length));

/**
 * Every folder from the root down to path, e.g. ["/", "/a", "/a/b"].
 */
export const ancestors = (path: string): string[] => {
    const out = ['/'];
    normalize(path)
        .split('/')
        .filter(Boolean)
        .reduce((current, part) => {
            const next = `${current === '/' ? '' : current}/${part}`;
            out.push(next);
            return next;
        }, '/');

    return out;
};
