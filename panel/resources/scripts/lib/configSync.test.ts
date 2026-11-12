import { parse } from 'yaml';
import { ConfigSyncError, formatPath, syncConfig } from '@/lib/configSync';

const OLD = `# My plugin
prefix: '&6[Shop]'
debug: true
database:
  host: db.example.com
  port: 3307
worlds:
- survival
- nether
messages:
  welcome: |
    Hello
    and welcome!
removed-option: 5
limits: {}
`;

const NEW = `# =====================
#   MyPlugin 2.0 config
# =====================

# Prefix of every message.
prefix: "&a[Plugin]"   # colors allowed

debug: false

database:
    # Where the database runs.
    host: localhost
    port: 3306
    pool-size: 10

worlds: []

messages:
    welcome: "Welcome!"
    goodbye: "Bye!"

limits:
    max: 3
`;

describe('syncConfig', () => {
    const result = syncConfig(OLD, NEW);
    const data = parse(result.text);

    it('takes over the old values', () => {
        expect(data.prefix).toBe('&6[Shop]');
        expect(data.debug).toBe(true);
        expect(data.database).toEqual({ host: 'db.example.com', port: 3307, 'pool-size': 10 });
        expect(data.worlds).toEqual(['survival', 'nether']);
        expect(data.messages).toEqual({ welcome: 'Hello\nand welcome!\n', goodbye: 'Bye!' });
    });

    it('keeps the layout and comments of the new config', () => {
        expect(result.rewritten).toBe(false);
        expect(result.text).toContain('#   MyPlugin 2.0 config');
        expect(result.text).toContain("prefix: '&6[Shop]'   # colors allowed");
        expect(result.text).toContain('    # Where the database runs.\n    host: db.example.com\n');
        expect(result.text).toContain('    pool-size: 10\n');
        expect(result.text).toContain('worlds:\n- survival\n- nether\n\nmessages:');
        expect(result.text).toContain('    welcome: |\n      Hello\n      and welcome!\n    goodbye: "Bye!"');
    });

    it('reports what changed, what is gone and what is new', () => {
        expect(result.replaced.map(formatPath)).toEqual([
            'prefix',
            'debug',
            'database.host',
            'database.port',
            'worlds',
            'messages.welcome',
        ]);
        // An empty map in the old config whose new counterpart has keys is a value that became a map.
        expect(result.missing.map(formatPath)).toEqual(['removed-option', 'limits']);
        expect(result.added.map(formatPath)).toEqual(['database.pool-size', 'messages.goodbye', 'limits.max']);
        expect(result.unchanged).toBe(0);
    });

    it('marks the lines of every replaced key', () => {
        const lines = result.text.split('\n');
        const marked = result.lines.map(([from, to]) => lines.slice(from, to + 1).join('\n'));
        expect(marked).toEqual([
            "prefix: '&6[Shop]'   # colors allowed",
            'debug: true',
            '    host: db.example.com',
            '    port: 3307',
            'worlds:\n- survival\n- nether',
            '    welcome: |\n      Hello\n      and welcome!',
        ]);
    });

    it('counts equal values as unchanged', () => {
        const same = syncConfig('a: 1\nb: x\n', 'a: 1\nb: y\n');
        expect(same.unchanged).toBe(1);
        expect(same.replaced.map(formatPath)).toEqual(['b']);
        expect(same.text).toBe('a: 1\nb: x\n');
    });

    it('moves block lists into a deeper or shallower layout', () => {
        const deeper = syncConfig('group:\n  list:\n    - a\n    - b\n', 'group:\n      list:\n          - z\n');
        expect(deeper.text).toBe('group:\n      list:\n          - a\n          - b\n');

        const inline = syncConfig('list:\n  - a\n  - b\n', 'list: [z]\nother: 1\n');
        expect(parse(inline.text)).toEqual({ list: ['a', 'b'], other: 1 });
        expect(inline.rewritten).toBe(false);
    });

    it('handles empty values on both sides', () => {
        const empty = syncConfig('a:\nb: 2\n', 'a: 1\nb:\n');
        expect(parse(empty.text)).toEqual({ a: null, b: 2 });
    });

    it('reports a value that became a map once', () => {
        const res = syncConfig('sound: true\n', 'sound:\n  enabled: true\n  volume: 1\n');
        expect(res.missing.map(formatPath)).toEqual(['sound']);
        expect(res.added.map(formatPath)).toEqual(['sound.enabled', 'sound.volume']);

        const back = syncConfig('sound:\n  enabled: false\n  volume: 2\n', 'sound: true\n');
        expect(back.missing.map(formatPath)).toEqual(['sound']);
        expect(back.text).toBe('sound: true\n');
    });

    it('rejects broken YAML and tells which config', () => {
        expect(() => syncConfig('a: [', 'a: 1')).toThrow(ConfigSyncError);
        try {
            syncConfig('a: 1', 'a: [');
        } catch (error) {
            expect((error as ConfigSyncError).which).toBe('new');
        }
        expect(() => syncConfig('- a\n- b\n', 'a: 1')).toThrow('not_a_map');
    });
});
