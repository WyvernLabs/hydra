// Hydra: reads and writes NBT files (Minecraft level.dat, player data,
// structures, schematics …) for the NBT editor of the file manager. Java files
// are big endian and usually gzip compressed, Bedrock files little endian with
// an 8 byte header in front of level.dat.

export enum TagType {
    End = 0,
    Byte = 1,
    Short = 2,
    Int = 3,
    Long = 4,
    Float = 5,
    Double = 6,
    ByteArray = 7,
    String = 8,
    List = 9,
    Compound = 10,
    IntArray = 11,
    LongArray = 12,
}

export type NbtTag =
    | { type: TagType.Byte | TagType.Short | TagType.Int | TagType.Float | TagType.Double; value: number }
    | { type: TagType.Long; value: bigint }
    | { type: TagType.String; value: string }
    | { type: TagType.ByteArray | TagType.IntArray; value: number[] }
    | { type: TagType.LongArray; value: bigint[] }
    | { type: TagType.List; itemType: TagType; value: NbtTag[] }
    | { type: TagType.Compound; value: NbtEntry[] };

// Entries of a compound keep the order of the file.
export interface NbtEntry {
    name: string;
    tag: NbtTag;
}

export type NbtCompression = 'gzip' | 'zlib' | 'none';

export interface NbtFile {
    name: string;
    root: NbtTag;
    compression: NbtCompression;
    littleEndian: boolean;
    // Version from the header of Bedrock's level.dat, null without header.
    bedrockHeader: number | null;
}

export class NbtError extends Error {}

const streamTransform = async (data: Uint8Array, stream: GenericTransformStream): Promise<Uint8Array> => {
    const response = new Response(new Blob([data]).stream().pipeThrough(stream));

    return new Uint8Array(await response.arrayBuffer());
};

const decompress = (data: Uint8Array, format: 'gzip' | 'deflate') =>
    streamTransform(data, new DecompressionStream(format));

const compress = (data: Uint8Array, format: 'gzip' | 'deflate') => streamTransform(data, new CompressionStream(format));

const detectCompression = (data: Uint8Array): NbtCompression => {
    if (data.length > 2 && data[0] === 0x1f && data[1] === 0x8b) return 'gzip';
    // zlib: compression method 8 and a header checksum that divides by 31.
    if (data.length > 2 && (data[0] & 0x0f) === 8 && ((data[0] << 8) | data[1]) % 31 === 0) return 'zlib';

    return 'none';
};

// Java writes strings as "modified UTF-8": NUL as two bytes and characters
// outside of the BMP as two encoded surrogates.
const decodeModifiedUtf8 = (bytes: Uint8Array): string => {
    let out = '';
    for (let i = 0; i < bytes.length; ) {
        const a = bytes[i++];
        if (a < 0x80) {
            out += String.fromCharCode(a);
        } else if ((a & 0xe0) === 0xc0) {
            out += String.fromCharCode(((a & 0x1f) << 6) | (bytes[i++] & 0x3f));
        } else {
            const b = bytes[i++];
            const c = bytes[i++];
            out += String.fromCharCode(((a & 0x0f) << 12) | ((b & 0x3f) << 6) | (c & 0x3f));
        }
    }

    return out;
};

const encodeModifiedUtf8 = (value: string): Uint8Array => {
    const out: number[] = [];
    for (let i = 0; i < value.length; i++) {
        const c = value.charCodeAt(i);
        if (c !== 0 && c < 0x80) {
            out.push(c);
        } else if (c < 0x800) {
            out.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f));
        } else {
            out.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
        }
    }

    return new Uint8Array(out);
};

class Reader {
    private view: DataView;
    offset = 0;

    constructor(private bytes: Uint8Array, private little: boolean) {
        this.view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    }

    private need(n: number) {
        if (this.offset + n > this.bytes.length) throw new NbtError('Unexpected end of file');
    }

    byte() {
        this.need(1);
        return this.view.getInt8(this.offset++);
    }

    ubyte() {
        this.need(1);
        return this.view.getUint8(this.offset++);
    }

    short() {
        this.need(2);
        const v = this.view.getInt16(this.offset, this.little);
        this.offset += 2;
        return v;
    }

    int() {
        this.need(4);
        const v = this.view.getInt32(this.offset, this.little);
        this.offset += 4;
        return v;
    }

    long() {
        this.need(8);
        const v = this.view.getBigInt64(this.offset, this.little);
        this.offset += 8;
        return v;
    }

    float() {
        this.need(4);
        const v = this.view.getFloat32(this.offset, this.little);
        this.offset += 4;
        return v;
    }

    double() {
        this.need(8);
        const v = this.view.getFloat64(this.offset, this.little);
        this.offset += 8;
        return v;
    }

    string() {
        const length = this.view.getUint16(this.offset, this.little);
        this.need(2 + length);
        this.offset += 2;
        const bytes = this.bytes.subarray(this.offset, this.offset + length);
        this.offset += length;
        // Bedrock uses plain UTF-8.
        return this.little ? new TextDecoder().decode(bytes) : decodeModifiedUtf8(bytes);
    }

    length() {
        const n = this.int();
        if (n < 0 || n > this.bytes.length) throw new NbtError('Invalid length');
        return n;
    }

    payload(type: TagType, depth: number): NbtTag {
        if (depth > 512) throw new NbtError('Nested too deeply');

        switch (type) {
            case TagType.Byte:
                return { type, value: this.byte() };
            case TagType.Short:
                return { type, value: this.short() };
            case TagType.Int:
                return { type, value: this.int() };
            case TagType.Long:
                return { type, value: this.long() };
            case TagType.Float:
                return { type, value: this.float() };
            case TagType.Double:
                return { type, value: this.double() };
            case TagType.ByteArray: {
                const n = this.length();
                return { type, value: Array.from({ length: n }, () => this.byte()) };
            }
            case TagType.String:
                return { type, value: this.string() };
            case TagType.List: {
                const itemType = this.ubyte() as TagType;
                const n = this.length();
                if (itemType > TagType.LongArray || (itemType === TagType.End && n > 0)) {
                    throw new NbtError('Invalid list type');
                }
                return { type, itemType, value: Array.from({ length: n }, () => this.payload(itemType, depth + 1)) };
            }
            case TagType.Compound: {
                const value: NbtEntry[] = [];
                for (;;) {
                    const child = this.ubyte() as TagType;
                    if (child === TagType.End) break;
                    if (child > TagType.LongArray) throw new NbtError('Invalid tag type');
                    const name = this.string();
                    value.push({ name, tag: this.payload(child, depth + 1) });
                }
                return { type, value };
            }
            case TagType.IntArray: {
                const n = this.length();
                return { type, value: Array.from({ length: n }, () => this.int()) };
            }
            case TagType.LongArray: {
                const n = this.length();
                return { type, value: Array.from({ length: n }, () => this.long()) };
            }
            default:
                throw new NbtError('Invalid tag type');
        }
    }

    root(): { name: string; tag: NbtTag } {
        const type = this.ubyte() as TagType;
        if (type !== TagType.Compound && type !== TagType.List) throw new NbtError('Not an NBT file');
        const name = this.string();

        return { name, tag: this.payload(type, 0) };
    }

    get done() {
        return this.offset === this.bytes.length;
    }
}

class Writer {
    private buffer = new Uint8Array(1024);
    private view = new DataView(this.buffer.buffer);
    private offset = 0;

    constructor(private little: boolean) {}

    private ensure(n: number) {
        if (this.offset + n <= this.buffer.length) return;
        let size = this.buffer.length * 2;
        while (size < this.offset + n) size *= 2;
        const next = new Uint8Array(size);
        next.set(this.buffer);
        this.buffer = next;
        this.view = new DataView(next.buffer);
    }

    byte(v: number) {
        this.ensure(1);
        this.view.setInt8(this.offset++, v);
    }

    short(v: number) {
        this.ensure(2);
        this.view.setInt16(this.offset, v, this.little);
        this.offset += 2;
    }

    int(v: number) {
        this.ensure(4);
        this.view.setInt32(this.offset, v, this.little);
        this.offset += 4;
    }

    long(v: bigint) {
        this.ensure(8);
        this.view.setBigInt64(this.offset, v, this.little);
        this.offset += 8;
    }

    float(v: number) {
        this.ensure(4);
        this.view.setFloat32(this.offset, v, this.little);
        this.offset += 4;
    }

    double(v: number) {
        this.ensure(8);
        this.view.setFloat64(this.offset, v, this.little);
        this.offset += 8;
    }

    string(v: string) {
        const bytes = this.little ? new TextEncoder().encode(v) : encodeModifiedUtf8(v);
        if (bytes.length > 0xffff) throw new NbtError('String too long');
        this.ensure(2 + bytes.length);
        this.view.setUint16(this.offset, bytes.length, this.little);
        this.offset += 2;
        this.buffer.set(bytes, this.offset);
        this.offset += bytes.length;
    }

    payload(tag: NbtTag): void {
        switch (tag.type) {
            case TagType.Byte:
                return this.byte(tag.value);
            case TagType.Short:
                return this.short(tag.value);
            case TagType.Int:
                return this.int(tag.value);
            case TagType.Long:
                return this.long(tag.value);
            case TagType.Float:
                return this.float(tag.value);
            case TagType.Double:
                return this.double(tag.value);
            case TagType.ByteArray:
                this.int(tag.value.length);
                return tag.value.forEach((v) => this.byte(v));
            case TagType.String:
                return this.string(tag.value);
            case TagType.List:
                this.byte(tag.value.length === 0 ? tag.itemType : tag.value[0].type);
                this.int(tag.value.length);
                return tag.value.forEach((v) => this.payload(v));
            case TagType.Compound:
                tag.value.forEach(({ name, tag: child }) => {
                    this.byte(child.type);
                    this.string(name);
                    this.payload(child);
                });
                return this.byte(TagType.End);
            case TagType.IntArray:
                this.int(tag.value.length);
                return tag.value.forEach((v) => this.int(v));
            case TagType.LongArray:
                this.int(tag.value.length);
                return tag.value.forEach((v) => this.long(v));
        }
    }

    bytes() {
        return this.buffer.slice(0, this.offset);
    }
}

const tryRead = (bytes: Uint8Array, little: boolean) => {
    const reader = new Reader(bytes, little);
    const root = reader.root();
    if (!reader.done) throw new NbtError('Trailing data');

    return root;
};

/**
 * Reads an NBT file in any of the formats Minecraft uses.
 */
export const readNbt = async (data: ArrayBuffer): Promise<NbtFile> => {
    let bytes = new Uint8Array(data);
    const compression = detectCompression(bytes);
    if (compression !== 'none') {
        bytes = await decompress(bytes, compression === 'gzip' ? 'gzip' : 'deflate');
    }

    try {
        const { name, tag } = tryRead(bytes, false);
        return { name, root: tag, compression, littleEndian: false, bedrockHeader: null };
    } catch {
        // Bedrock files are little endian, level.dat starts with version and length.
    }
    try {
        const { name, tag } = tryRead(bytes, true);
        return { name, root: tag, compression, littleEndian: true, bedrockHeader: null };
    } catch {
        // Try the header next.
    }
    if (bytes.length > 8) {
        const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
        if (view.getInt32(4, true) === bytes.length - 8) {
            const { name, tag } = tryRead(bytes.subarray(8), true);
            return { name, root: tag, compression, littleEndian: true, bedrockHeader: view.getInt32(0, true) };
        }
    }

    throw new NbtError('Not an NBT file');
};

/**
 * Writes the file back in the format it was read in.
 */
export const writeNbt = async (file: NbtFile): Promise<Uint8Array> => {
    const writer = new Writer(file.littleEndian);
    writer.byte(file.root.type);
    writer.string(file.name);
    writer.payload(file.root);
    let bytes = writer.bytes();

    if (file.bedrockHeader !== null) {
        const out = new Uint8Array(bytes.length + 8);
        const view = new DataView(out.buffer);
        view.setInt32(0, file.bedrockHeader, true);
        view.setInt32(4, bytes.length, true);
        out.set(bytes, 8);
        bytes = out;
    }

    if (file.compression === 'none') return bytes;

    return compress(bytes, file.compression === 'gzip' ? 'gzip' : 'deflate');
};

/**
 * A new tag of the type with an empty or zero value.
 */
export const emptyTag = (type: TagType): NbtTag => {
    switch (type) {
        case TagType.Long:
            return { type, value: BigInt(0) };
        case TagType.String:
            return { type, value: '' };
        case TagType.ByteArray:
        case TagType.IntArray:
            return { type, value: [] };
        case TagType.LongArray:
            return { type, value: [] };
        case TagType.List:
            return { type, itemType: TagType.End, value: [] };
        case TagType.Compound:
            return { type, value: [] };
        default:
            return { type: type as TagType.Byte, value: 0 };
    }
};

const ranges: Partial<Record<TagType, [number, number]>> = {
    [TagType.Byte]: [-128, 127],
    [TagType.Short]: [-32768, 32767],
    [TagType.Int]: [-2147483648, 2147483647],
};

const longRange: [bigint, bigint] = [BigInt('-9223372036854775808'), BigInt('9223372036854775807')];

/**
 * Parses a number typed by the user for the tag type, null if it does not fit.
 */
export const parseNumber = (type: TagType, input: string): number | bigint | null => {
    const text = input.trim();
    if (type === TagType.Long || type === TagType.LongArray) {
        if (!/^-?\d+$/.test(text)) return null;
        const value = BigInt(text);
        return value < longRange[0] || value > longRange[1] ? null : value;
    }
    if (type === TagType.Float || type === TagType.Double) {
        const value = Number(text);
        return text === '' || Number.isNaN(value) ? null : type === TagType.Float ? Math.fround(value) : value;
    }
    if (!/^-?\d+$/.test(text)) return null;
    const value = Number(text);
    const range = ranges[type === TagType.ByteArray ? TagType.Byte : type === TagType.IntArray ? TagType.Int : type];

    return range && (value < range[0] || value > range[1]) ? null : value;
};

/**
 * Number of direct children of a list, compound or array.
 */
export const childCount = (tag: NbtTag): number | null => {
    switch (tag.type) {
        case TagType.List:
        case TagType.Compound:
        case TagType.ByteArray:
        case TagType.IntArray:
        case TagType.LongArray:
            return tag.value.length;
        default:
            return null;
    }
};
