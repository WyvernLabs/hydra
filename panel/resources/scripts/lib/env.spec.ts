import { parseEnv, stringifyEnv } from '@/lib/env';

const file = [
    '# Bot settings',
    'TOKEN="abc def" # keep secret',
    "export NAME='Hydra'",
    'PORT=3000 # port',
    '',
    'EMPTY=',
    'ESCAPED="line\\nnext \\"quoted\\""',
].join('\n');

describe('@/lib/env', () => {
    it('reads values', () => {
        const values = Object.fromEntries(
            parseEnv(file)
                .filter((l) => l.key)
                .map((l) => [l.key, l.value])
        );
        expect(values).toEqual({
            TOKEN: 'abc def',
            NAME: 'Hydra',
            PORT: '3000',
            EMPTY: '',
            ESCAPED: 'line\nnext "quoted"',
        });
    });

    it('keeps unchanged lines as they were', () => {
        const lines = parseEnv(file);
        const values = Object.fromEntries(lines.filter((l) => l.key).map((l) => [l.key!, l.value]));
        expect(stringifyEnv(lines, values)).toBe(file + '\n');
    });

    it('writes changed, removed and added variables', () => {
        const lines = parseEnv(file);
        const out = stringifyEnv(
            lines,
            {
                TOKEN: 'new "one"',
                NAME: 'Wyvern Labs',
                PORT: '25565',
                EMPTY: '',
                ESCAPED: 'line\nnext "quoted"',
                NEW: 'x y',
            },
            ['NEW']
        );
        expect(out).toContain('TOKEN="new \\"one\\"" # keep secret');
        expect(out).toContain("export NAME='Wyvern Labs'");
        expect(out).toContain('PORT=25565 # port');
        expect(out.trim().endsWith('NEW="x y"')).toBe(true);
        expect(stringifyEnv(lines, { PORT: '1' })).not.toContain('TOKEN');
    });
});
