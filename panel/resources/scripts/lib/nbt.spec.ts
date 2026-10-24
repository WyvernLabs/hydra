import { emptyTag, parseNumber, readNbt, TagType, writeNbt, NbtFile } from '@/lib/nbt';

const sample = (): NbtFile => ({
    name: 'Data',
    compression: 'none',
    littleEndian: false,
    bedrockHeader: null,
    root: {
        type: TagType.Compound,
        value: [
            { name: 'LevelName', tag: { type: TagType.String, value: 'Wörld \u0000 😀' } },
            { name: 'Time', tag: { type: TagType.Long, value: BigInt('-9223372036854775808') } },
            { name: 'Health', tag: { type: TagType.Float, value: Math.fround(19.5) } },
            {
                name: 'Pos',
                tag: { type: TagType.List, itemType: TagType.Double, value: [{ type: TagType.Double, value: 1.25 }] },
            },
            { name: 'Empty', tag: emptyTag(TagType.List) },
            { name: 'Bytes', tag: { type: TagType.ByteArray, value: [-128, 0, 127] } },
            { name: 'Ints', tag: { type: TagType.IntArray, value: [1, -2] } },
            { name: 'Longs', tag: { type: TagType.LongArray, value: [BigInt(5)] } },
        ],
    },
});

const roundTrip = async (file: NbtFile) => {
    const bytes = await writeNbt(file);
    return readNbt(bytes.buffer.slice(bytes.byteOffset, bytes.byteOffset + bytes.byteLength) as ArrayBuffer);
};

describe('@/lib/nbt', () => {
    it('reads what it writes (Java)', async () => {
        const read = await roundTrip(sample());
        expect(read.littleEndian).toBe(false);
        expect(read.root).toEqual(sample().root);
        expect(read.name).toBe('Data');
    });

    it('reads what it writes (Bedrock with header)', async () => {
        const file = { ...sample(), littleEndian: true, bedrockHeader: 10 };
        const read = await roundTrip(file);
        expect(read.littleEndian).toBe(true);
        expect(read.bedrockHeader).toBe(10);
        expect(read.root).toEqual(sample().root);
    });

    it('refuses other files', async () => {
        await expect(readNbt(new TextEncoder().encode('hello world').buffer as ArrayBuffer)).rejects.toThrow();
    });

    it('checks number ranges', () => {
        expect(parseNumber(TagType.Byte, '127')).toBe(127);
        expect(parseNumber(TagType.Byte, '128')).toBeNull();
        expect(parseNumber(TagType.Int, '1.5')).toBeNull();
        expect(parseNumber(TagType.Long, '9223372036854775807')).toBe(BigInt('9223372036854775807'));
        expect(parseNumber(TagType.Long, '9223372036854775808')).toBeNull();
        expect(parseNumber(TagType.Double, 'abc')).toBeNull();
    });
});
