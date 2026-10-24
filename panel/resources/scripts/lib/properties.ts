// Hydra: reads and writes .properties files (e.g. server.properties of
// Minecraft) without losing comments, empty lines or the order of the keys.

export interface PropertyLine {
    // Null for comments and empty lines, which are kept as they are.
    key: string | null;
    value: string;
    // Everything up to the value, e.g. "max-players=".
    prefix: string;
    raw: string;
}

const PROPERTY = /^(\s*)((?:\\.|[^:=\s\\])+)(\s*[=:]\s*|\s+)(.*)$/;

export const parseProperties = (content: string): PropertyLine[] =>
    content
        .replace(/\r\n?/g, '\n')
        .replace(/\n$/, '')
        .split('\n')
        .map((raw) => {
            const trimmed = raw.trimStart();
            const match = trimmed.startsWith('#') || trimmed.startsWith('!') ? null : PROPERTY.exec(raw);
            if (!match) {
                // A key without separator and value, e.g. "pvp", counts as empty.
                const key = trimmed.length > 0 && !trimmed.startsWith('#') && !trimmed.startsWith('!') ? trimmed : null;

                return { key, value: '', prefix: key ? `${raw}=` : raw, raw };
            }

            return { key: match[2], value: match[4], prefix: match[1] + match[2] + match[3], raw };
        });

/**
 * The file again, with the changed values put into their lines.
 */
export const stringifyProperties = (lines: PropertyLine[], values: Record<string, string>): string =>
    lines
        .map((line) =>
            line.key !== null && values[line.key] !== undefined && values[line.key] !== line.value
                ? line.prefix + values[line.key].replace(/\r?\n/g, ' ')
                : line.raw
        )
        .join('\n') + '\n';

export type PropertyType = 'boolean' | 'number' | 'text';

export const propertyType = (value: string): PropertyType =>
    value === 'true' || value === 'false' ? 'boolean' : /^-?\d+(\.\d+)?$/.test(value) ? 'number' : 'text';
