// Hydra: reads and writes .env files for the .env editor. Comments, empty
// lines, the order, "export" and the quotes of unchanged variables are kept.

export interface EnvLine {
    // The line as it was in the file.
    raw: string;
    // Null for comments and empty lines.
    key: string | null;
    value: string;
    exported: boolean;
    quote: '"' | "'" | '';
    // Comment after an unquoted or quoted value, with its leading spaces.
    comment: string;
}

const KEY = /^\s*(export\s+)?([A-Za-z_][A-Za-z0-9_.-]*)\s*=\s?(.*)$/;

const unescape = (value: string) =>
    value.replace(/\\([nrt"\\$])/g, (_, c: string) => ({ n: '\n', r: '\r', t: '\t' }[c] ?? c));

export const parseEnv = (content: string): EnvLine[] =>
    content
        .replace(/\r\n/g, '\n')
        .split('\n')
        .map((raw): EnvLine => {
            const match = raw.match(KEY);
            if (!match) return { raw, key: null, value: '', exported: false, quote: '', comment: '' };

            const [, exported, key, rest] = match;
            const first = rest.trimStart()[0];
            if (first === '"' || first === "'") {
                const body = rest.trimStart().slice(1);
                // The closing quote is the first one not escaped by a backslash.
                let end = -1;
                for (let i = 0; i < body.length; i++) {
                    if (first === '"' && body[i] === '\\') {
                        i++;
                        continue;
                    }
                    if (body[i] === first) {
                        end = i;
                        break;
                    }
                }
                if (end >= 0) {
                    const inner = body.slice(0, end);
                    return {
                        raw,
                        key,
                        value: first === '"' ? unescape(inner) : inner,
                        exported: !!exported,
                        quote: first,
                        comment: body.slice(end + 1),
                    };
                }
            }

            const hash = rest.search(/\s#/);
            return {
                raw,
                key,
                value: (hash >= 0 ? rest.slice(0, hash) : rest).trim(),
                exported: !!exported,
                quote: '',
                comment: hash >= 0 ? rest.slice(hash) : '',
            };
        });

const needsQuotes = (value: string) => value === '' || /[\s#"'\\$`]/.test(value);

const formatValue = (value: string, quote: EnvLine['quote']): string => {
    if (quote === "'" && !value.includes("'") && !value.includes('\n')) return `'${value}'`;
    if (quote === '"' || needsQuotes(value)) {
        if (value === '' && quote === '') return '';
        return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n')}"`;
    }

    return value;
};

export const formatEnvLine = (key: string, value: string, base?: Pick<EnvLine, 'exported' | 'quote' | 'comment'>) =>
    `${base?.exported ? 'export ' : ''}${key}=${formatValue(value, base?.quote ?? '')}${base?.comment ?? ''}`;

/**
 * The file with new values. Variables missing in "values" are removed, new
 * ones ("added", in order) are appended.
 */
export const stringifyEnv = (lines: EnvLine[], values: Record<string, string>, added: string[] = []): string => {
    const out: string[] = [];
    const written = new Set<string>();
    lines.forEach((line) => {
        if (line.key === null) {
            out.push(line.raw);
            return;
        }
        if (!(line.key in values) || written.has(line.key)) return;
        written.add(line.key);
        out.push(values[line.key] === line.value ? line.raw : formatEnvLine(line.key, values[line.key], line));
    });
    added.forEach((key) => {
        if (written.has(key) || !(key in values)) return;
        written.add(key);
        out.push(formatEnvLine(key, values[key]));
    });

    // Keep a single line break at the end of the file.
    return out.join('\n').replace(/\n*$/, '\n');
};

export const isValidKey = (key: string) => /^[A-Za-z_][A-Za-z0-9_.-]*$/.test(key);

// Values of these variables are hidden until shown.
export const isSecret = (key: string) =>
    /(SECRET|TOKEN|PASS|PASSWORD|PRIVATE|_KEY|^KEY$|API_?KEY|CREDENTIAL)/i.test(key);
