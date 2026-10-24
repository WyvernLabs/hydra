import CodeMirror from '@/lib/codemirror';

// Hydra: search and replace of the text editor.

export interface SearchOptions {
    caseSensitive: boolean;
    wholeWord: boolean;
    regex: boolean;
}

export interface Match {
    from: CodeMirror.Position;
    to: CodeMirror.Position;
}

// More matches are not counted, the editor would get slow.
export const MAX_MATCHES = 10000;

const escapeRegex = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/**
 * The search as a global regular expression, null without query and
 * "invalid" for a broken regular expression.
 */
export const buildQuery = (query: string, options: SearchOptions): RegExp | null | 'invalid' => {
    if (!query) return null;
    let source = options.regex ? query : escapeRegex(query);
    if (options.wholeWord) source = `\\b(?:${source})\\b`;
    try {
        return new RegExp(source, options.caseSensitive ? 'g' : 'gi');
    } catch {
        return 'invalid';
    }
};

/**
 * Every match in the document, line by line.
 */
export const findMatches = (doc: CodeMirror.Doc, re: RegExp): Match[] => {
    const out: Match[] = [];
    const lines = doc.lineCount();
    for (let line = 0; line < lines && out.length < MAX_MATCHES; line++) {
        const text = doc.getLine(line);
        re.lastIndex = 0;
        let m: RegExpExecArray | null;
        while ((m = re.exec(text)) !== null) {
            if (m[0].length === 0) {
                re.lastIndex++;
                if (re.lastIndex > text.length) break;
                continue;
            }
            out.push({ from: { line, ch: m.index }, to: { line, ch: m.index + m[0].length } });
            if (out.length >= MAX_MATCHES) break;
        }
    }

    return out;
};

/**
 * Text that replaces a match. With regular expressions "$1" and so on refer
 * to the groups of the match.
 */
export const replacementFor = (matched: string, re: RegExp, replacement: string, options: SearchOptions): string => {
    if (!options.regex) return replacement;
    const single = new RegExp(re.source, re.flags.replace('g', ''));

    return matched.replace(single, replacement);
};

/**
 * Overlay mode that marks every match.
 */
export const matchOverlay = (re: RegExp) => ({
    token: (stream: CodeMirror.StringStream) => {
        re.lastIndex = stream.pos;
        const m = re.exec(stream.string);
        if (m && m.index === stream.pos && m[0].length > 0) {
            stream.pos += m[0].length;
            return 'hydra-match';
        }
        if (m && m[0].length > 0) {
            stream.pos = m.index;
        } else {
            stream.skipToEnd();
        }

        return null;
    },
});

export const comparePos = (a: CodeMirror.Position, b: CodeMirror.Position): number =>
    a.line === b.line ? a.ch - b.ch : a.line - b.line;
