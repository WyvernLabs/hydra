// Hydra: texts of the admins in the console (Design > Console) may use
// Minecraft color codes like "&a" or "&l", turned into terminal colors here.

const CODES: Record<string, string> = {
    '0': '30',
    '1': '34',
    '2': '32',
    '3': '36',
    '4': '31',
    '5': '35',
    '6': '33',
    '7': '37',
    '8': '90',
    '9': '94',
    a: '92',
    b: '96',
    c: '91',
    d: '95',
    e: '93',
    f: '97',
    l: '1',
    o: '3',
    n: '4',
    m: '9',
    r: '0',
};

/**
 * Replaces "&a"-style codes with ANSI escape sequences.
 */
export const colorCodesToAnsi = (text: string): string =>
    text.replace(/&([0-9a-fk-or])/gi, (all, code: string) =>
        CODES[code.toLowerCase()] ? `\u001b[${CODES[code.toLowerCase()]}m` : all
    );

/**
 * Fills in {name}, {id}, {address} and {node}.
 */
export const fillPlaceholders = (text: string, values: Record<string, string>): string =>
    text.replace(/\{(name|id|address|node)\}/g, (all, key: string) => values[key] ?? all);
