import { FileObject } from '@/api/server/files/loadDirectory';
import modes, { Mode } from '@/modes';

// Hydra: how the file manager opens a file.
export type FileKind = 'text' | 'image' | 'video' | 'audio' | 'nbt' | 'sqlite' | 'archive' | 'binary';

const extensions: Record<Exclude<FileKind, 'text' | 'binary'>, string[]> = {
    image: ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg'],
    video: ['mp4', 'm4v', 'webm', 'ogv', 'mov', 'mkv'],
    audio: ['mp3', 'ogg', 'oga', 'opus', 'wav', 'flac', 'm4a', 'aac'],
    nbt: ['nbt', 'dat', 'dat_old', 'schematic', 'schem', 'litematic', 'mcstructure'],
    sqlite: ['db', 'sqlite', 'sqlite3', 'db3'],
    archive: ['zip', 'tar', 'gz', 'tgz', 'bz2', 'xz', 'rar', '7z', 'jar', 'war'],
};

// Files without a type of their own that are text anyway.
const textExtensions = [
    'txt',
    'log',
    'md',
    'markdown',
    'yml',
    'yaml',
    'json',
    'json5',
    'mcmeta',
    'toml',
    'ini',
    'cfg',
    'conf',
    'properties',
    'lang',
    'env',
    'xml',
    'html',
    'htm',
    'css',
    'scss',
    'js',
    'ts',
    'mjs',
    'cjs',
    'jsx',
    'tsx',
    'php',
    'py',
    'rb',
    'lua',
    'sh',
    'bash',
    'bat',
    'cmd',
    'ps1',
    'java',
    'kt',
    'kts',
    'go',
    'rs',
    'c',
    'h',
    'cpp',
    'cs',
    'sql',
    'csv',
    'tsv',
    'sk',
    'mcfunction',
    'secret',
    'list',
    'csv',
    'gitignore',
    'dockerignore',
    'pteroignore',
    'editorconfig',
    'gradle',
    'vue',
    'svelte',
    'twig',
    'hbs',
    'nginx',
    'service',
];

export const extensionOf = (name: string): string => {
    const dot = name.lastIndexOf('.');

    return dot > 0 ? name.slice(dot + 1).toLowerCase() : '';
};

export const fileKind = (file: Pick<FileObject, 'name' | 'mimetype' | 'size'>): FileKind => {
    const ext = extensionOf(file.name);
    for (const [kind, list] of Object.entries(extensions)) {
        if (list.includes(ext)) return kind as FileKind;
    }
    if (textExtensions.includes(ext) || /^\.[^.]+$/.test(file.name)) return 'text';

    const mime = file.mimetype || '';
    if (/^text\//.test(mime) || /json|xml|javascript|yaml|x-sh|x-empty|x-php|toml|sql/.test(mime)) return 'text';
    if (/^image\//.test(mime)) return 'image';
    if (/^video\//.test(mime)) return 'video';
    if (/^audio\//.test(mime)) return 'audio';
    if (file.size === 0) return 'text';

    return 'binary';
};

/**
 * Editor mode by file name, plain text when nothing matches.
 */
export const modeForFile = (name: string): Mode => {
    const ext = extensionOf(name);
    const byFile = modes.find((mode) => mode.file?.test(name));
    if (byFile) return byFile;
    const byExt = modes.find((mode) => mode.ext?.includes(ext));
    if (byExt) return byExt;

    switch (ext) {
        case 'mcmeta':
        case 'json5':
            return modes.find((mode) => mode.name === 'JSON')!;
        case 'lang':
        case 'cfg':
        case 'env':
            return modes.find((mode) => mode.name === 'Properties')!;
    }

    return modes.find((mode) => mode.mime === 'text/plain')!;
};
