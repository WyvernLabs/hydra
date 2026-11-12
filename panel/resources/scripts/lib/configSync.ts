import { Document, isMap, isScalar, Node, Pair, parseDocument, Scalar, YAMLMap } from 'yaml';

// Hydra: config sync (addon "config_sync"). Takes the values of an old YAML
// config and writes them into a new one, e.g. after a plugin update created a
// fresh config with new keys and another layout.
//
// Only the values are replaced, right in the text of the new config, so its
// comments, order, quotes and layout stay as they are. Maps are walked key by
// key; everything else (texts, numbers, lists, empty maps) is one value.

export type ConfigPath = string[];

export interface SyncResult {
    // The new config with the old values.
    text: string;
    // Keys whose value was taken over from the old config.
    replaced: ConfigPath[];
    // Keys that have the same value in both configs.
    unchanged: number;
    // Keys of the old config that the new config does not have (anymore).
    missing: ConfigPath[];
    // Keys of the new config that the old config does not have; they keep their new value.
    added: ConfigPath[];
    // Lines (0-based, inclusive) of every replaced key in "text".
    lines: Array<[number, number]>;
    // The values could not be written into the text exactly, so the config was
    // written anew; comments stay, the layout may differ a little.
    rewritten: boolean;
}

export class ConfigSyncError extends Error {
    constructor(public readonly which: 'old' | 'new', message: string) {
        super(message);
    }
}

interface Entry {
    path: ConfigPath;
    pair: Pair<Node, Node | null>;
    // Non-empty maps are walked, not replaced.
    container: boolean;
}

const id = (path: ConfigPath) => JSON.stringify(path);

export const formatPath = (path: ConfigPath) => path.join('.');

const keyOf = (pair: Pair<unknown, unknown>): string =>
    isScalar(pair.key) ? String((pair.key as Scalar).value) : String(pair.key);

const parse = (text: string, which: 'old' | 'new'): Document => {
    const doc = parseDocument(text, { keepSourceTokens: true });
    const error = doc.errors[0];
    if (error) {
        throw new ConfigSyncError(which, error.message.split('\n')[0]);
    }
    if (doc.contents !== null && !isMap(doc.contents)) {
        throw new ConfigSyncError(which, 'not_a_map');
    }

    return doc;
};

const entries = (doc: Document): Map<string, Entry> => {
    const list = new Map<string, Entry>();
    const walk = (map: YAMLMap<Node, Node | null>, prefix: ConfigPath) => {
        for (const pair of map.items) {
            const path = [...prefix, keyOf(pair)];
            const value = pair.value;
            const container = isMap(value) && value.items.length > 0;
            list.set(id(path), { path, pair, container });
            if (container) walk(value as YAMLMap<Node, Node | null>, path);
        }
    };
    if (isMap(doc.contents)) walk(doc.contents as YAMLMap<Node, Node | null>, []);

    return list;
};

const valueOf = (doc: Document, node: Node | null): string =>
    JSON.stringify(node === null || node === undefined ? null : node.toJS(doc));

// Start offset of every line, to turn offsets into line/column.
const lineStarts = (text: string): number[] => {
    const starts = [0];
    for (let i = 0; i < text.length; i++) {
        if (text[i] === '\n') starts.push(i + 1);
    }

    return starts;
};

const position = (starts: number[], offset: number): { line: number; col: number } => {
    let low = 0;
    let high = starts.length - 1;
    while (low < high) {
        const mid = (low + high + 1) >> 1;
        if (starts[mid] <= offset) low = mid;
        else high = mid - 1;
    }

    return { line: low, col: offset - starts[low] };
};

// Moves every line but the first by "shift" columns.
const shiftLines = (text: string, shift: number): string => {
    if (shift === 0) return text;
    const [first, ...rest] = text.split('\n');

    return [
        first,
        ...rest.map((line) => {
            if (line.trim() === '') return line;
            if (shift > 0) return ' '.repeat(shift) + line;
            const remove = Math.min(-shift, line.length - line.trimStart().length);
            return line.slice(remove);
        }),
    ].join('\n');
};

// Offset right behind the ":" of a pair, with the spaces after it.
const afterColon = (text: string, pair: Pair<Node, Node | null>): number => {
    const keyEnd = (pair.key as Node).range?.[1] ?? 0;
    const colon = text.indexOf(':', keyEnd);

    return colon === -1 ? keyEnd : colon + 1;
};

interface Edit {
    from: number;
    to: number;
    text: string;
}

// The text that replaces the value of "target" (new config) with the value of "source" (old config).
const edit = (
    oldText: string,
    oldStarts: number[],
    source: Entry,
    newText: string,
    newStarts: number[],
    target: Entry
): Edit => {
    const oldNode = source.pair.value;
    const newNode = target.pair.value;
    const oldKey = position(oldStarts, (source.pair.key as Node).range![0]);
    const newKey = position(newStarts, (target.pair.key as Node).range![0]);

    // The old value as written, or nothing for an empty value.
    let value = '';
    let oldStart = position(oldStarts, afterColon(oldText, source.pair));
    if (oldNode && oldNode.range) {
        value = oldText.slice(oldNode.range[0], oldNode.range[1]).replace(/\s+$/, '');
        oldStart = position(oldStarts, oldNode.range[0]);
    }
    const oldOwnLine = !!oldNode?.range && oldStart.line > oldKey.line;

    let from: number;
    let to: number;
    if (newNode && newNode.range && newNode.range[1] > newNode.range[0]) {
        from = newNode.range[0];
        to = newNode.range[1];
        // Keep line breaks and comments that belong to what follows.
        while (to > from && /\s/.test(newText[to - 1])) to--;
    } else {
        from = afterColon(newText, target.pair);
        to = from;
    }
    const newStart = position(newStarts, from);
    const newOwnLine = newStart.line > newKey.line;

    if (value === '') {
        // Empty old value: "key:" stays without a value.
        const start = afterColon(newText, target.pair);
        return { from: start, to: Math.max(start, to), text: '' };
    }

    if (oldOwnLine && newOwnLine) {
        // Both below their key (block lists): same relative indentation.
        return { from, to, text: shiftLines(value, newStart.col - oldStart.col) };
    }

    if (oldOwnLine) {
        // The old value is a block below its key, the new one is on the key line.
        const indent = newKey.col + (oldStart.col - oldKey.col);
        let start = from;
        while (start > 0 && newText[start - 1] === ' ') start--;

        return { from: start, to, text: '\n' + ' '.repeat(indent) + shiftLines(value, indent - oldStart.col) };
    }

    // The old value is on its key line (texts, numbers, flow lists, block text headers).
    const shift = newKey.col - oldKey.col;
    if (newOwnLine) {
        // Put it on the key line of the new config.
        return { from: afterColon(newText, target.pair), to, text: ' ' + shiftLines(value, shift) };
    }

    return { from, to, text: (to === from ? ' ' : '') + shiftLines(value, shift) };
};

const findPair = (doc: Document, path: ConfigPath): Pair<Node, Node | null> | null => {
    let map: unknown = doc.contents;
    let found: Pair<Node, Node | null> | null = null;
    for (const key of path) {
        if (!isMap(map)) return null;
        found = (map as YAMLMap<Node, Node | null>).items.find((pair) => keyOf(pair) === key) || null;
        if (!found) return null;
        map = found.value;
    }

    return found;
};

const linesOf = (text: string, doc: Document, paths: ConfigPath[]): Array<[number, number]> => {
    const counter = lineStarts(text);

    return paths.flatMap((path) => {
        const pair = findPair(doc, path);
        const keyRange = (pair?.key as Node | undefined)?.range;
        if (!pair || !keyRange) return [];
        const end = pair.value?.range ? Math.max(keyRange[0], pair.value.range[1] - 1) : keyRange[1];
        let last = position(counter, end).line;
        // Trailing line breaks of block values belong to the next line.
        while (last > position(counter, keyRange[0]).line && text.slice(counter[last], end + 1).trim() === '') last--;

        return [[position(counter, keyRange[0]).line, last] as [number, number]];
    });
};

// Whether every replaced key of "text" now has the value of the old config.
const verify = (text: string, oldDoc: Document, old: Map<string, Entry>, paths: ConfigPath[]): Document | null => {
    try {
        const doc = parseDocument(text);
        if (doc.errors.length > 0) return null;
        for (const path of paths) {
            const pair = findPair(doc, path);
            if (!pair || valueOf(doc, pair.value) !== valueOf(oldDoc, old.get(id(path))!.pair.value)) return null;
        }

        return doc;
    } catch {
        return null;
    }
};

const indentOf = (text: string): number => {
    const match = text.match(/^( +)\S/m);

    return match ? Math.min(8, Math.max(2, match[1].length)) : 2;
};

export const syncConfig = (oldText: string, newText: string): SyncResult => {
    const oldDoc = parse(oldText, 'old');
    const newDoc = parse(newText, 'new');
    const old = entries(oldDoc);
    const fresh = entries(newDoc);

    const replace: Array<[Entry, Entry]> = [];
    const missing: ConfigPath[] = [];
    let unchanged = 0;

    for (const [key, source] of old) {
        if (source.container) {
            // Maps are compared key by key; a map that became a single value is missing.
            const target = fresh.get(key);
            if (target && !target.container) missing.push(source.path);
            continue;
        }
        const target = fresh.get(key);
        // Keys below a value of the old config that became a value in the new one are reported once.
        if (!target) {
            const parentGone = source.path.some((_, i) => {
                const parent = fresh.get(id(source.path.slice(0, i)));
                return i > 0 && parent && !parent.container;
            });
            if (!parentGone) missing.push(source.path);
            continue;
        }
        if (target.container) {
            // A value became a map: the old value has no place anymore.
            missing.push(source.path);
            continue;
        }
        if (valueOf(oldDoc, source.pair.value) === valueOf(newDoc, target.pair.value)) {
            unchanged++;
        } else {
            replace.push([source, target]);
        }
    }

    const added = [...fresh.values()]
        .filter((entry) => !entry.container && !old.has(id(entry.path)))
        .map((entry) => entry.path);
    const paths = replace.map(([source]) => source.path);

    // Write the old values into the text of the new config, from the end so offsets stay valid.
    const oldStarts = lineStarts(oldText);
    const newStarts = lineStarts(newText);
    let text = newText;
    let rewritten = false;
    try {
        const edits = replace
            .map(([source, target]) => edit(oldText, oldStarts, source, newText, newStarts, target))
            .sort((a, b) => b.from - a.from);
        for (const change of edits) {
            text = text.slice(0, change.from) + change.text + text.slice(change.to);
        }
    } catch {
        text = '';
    }

    let result = text ? verify(text, oldDoc, old, paths) : null;
    if (!result) {
        // Fallback: set the values in the document and write it anew.
        rewritten = true;
        const doc = parseDocument(newText);
        for (const [source] of replace) {
            doc.setIn(source.path, source.pair.value ? source.pair.value.clone() : null);
        }
        text = doc.toString({ indent: indentOf(newText), lineWidth: 0 });
        result = parseDocument(text);
    }

    return {
        text,
        replaced: paths,
        unchanged,
        missing,
        added,
        lines: linesOf(text, result, paths),
        rewritten,
    };
};

// Whether a file is a YAML file the sync can work on.
export const isYamlFile = (name: string): boolean => /\.ya?ml$/i.test(name);
