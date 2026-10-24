import React, { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Check, ChevronRight, ListTree, Loader2, Pencil, Plus, Save, Trash2, X } from 'lucide-react';
import getFileBytes from '@/api/server/files/getFileBytes';
import saveFileContents from '@/api/server/files/saveFileContents';
import { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import { useIde } from '@/components/server/files/ide/context';
import { useSaveShortcut, ViewerMessage, ViewerProps } from '@/components/server/files/ide/viewers/common';
import { childCount, emptyTag, NbtFile, NbtTag, parseNumber, readNbt, TagType, writeNbt } from '@/lib/nbt';

// Hydra: NBT files (level.dat, player data, structures …) as an editable
// tree. The tree is changed in place and redrawn through a version counter.

const TYPE_LABELS: Record<TagType, string> = {
    [TagType.End]: 'End',
    [TagType.Byte]: 'B',
    [TagType.Short]: 'S',
    [TagType.Int]: 'I',
    [TagType.Long]: 'L',
    [TagType.Float]: 'F',
    [TagType.Double]: 'D',
    [TagType.ByteArray]: '[B]',
    [TagType.String]: '"',
    [TagType.List]: '[ ]',
    [TagType.Compound]: '{ }',
    [TagType.IntArray]: '[I]',
    [TagType.LongArray]: '[L]',
};

const TYPE_COLORS: Partial<Record<TagType, string>> = {
    [TagType.String]: 'text-green-300 bg-green-500/10',
    [TagType.List]: 'text-amber-300 bg-amber-500/10',
    [TagType.Compound]: 'text-primary-300 bg-primary-500/10',
};

const CREATABLE = [
    TagType.Byte,
    TagType.Short,
    TagType.Int,
    TagType.Long,
    TagType.Float,
    TagType.Double,
    TagType.String,
    TagType.List,
    TagType.Compound,
    TagType.ByteArray,
    TagType.IntArray,
    TagType.LongArray,
];

const isNumeric = (type: TagType) => type >= TagType.Byte && type <= TagType.Double;
const isArray = (type: TagType) =>
    type === TagType.ByteArray || type === TagType.IntArray || type === TagType.LongArray;

const formatValue = (tag: NbtTag): string =>
    isNumeric(tag.type) || tag.type === TagType.String ? String(tag.value) : '';

interface NodeProps {
    name: string | number;
    tag: NbtTag;
    depth: number;
    readOnly: boolean;
    onChange: () => void;
    onRename?: (name: string) => boolean;
    onDelete?: () => void;
    // Replaces the tag in its parent, for changing values of primitives.
    onReplace: (tag: NbtTag) => void;
    defaultOpen?: boolean;
}

const ValueInput = ({
    tag,
    readOnly,
    onReplace,
}: {
    tag: NbtTag;
    readOnly: boolean;
    onReplace: (tag: NbtTag) => void;
}) => {
    const [text, setText] = useState(formatValue(tag));
    const [invalid, setInvalid] = useState(false);

    useEffect(() => setText(formatValue(tag)), [tag]);

    const commit = () => {
        if (text === formatValue(tag)) return;
        if (tag.type === TagType.String) {
            onReplace({ type: TagType.String, value: text });
            return;
        }
        const value = parseNumber(tag.type, text);
        if (value === null) {
            setInvalid(true);
            return;
        }
        setInvalid(false);
        onReplace({ ...tag, value } as NbtTag);
    };

    return (
        <input
            value={text}
            readOnly={readOnly}
            spellCheck={false}
            onChange={(event) => {
                setText(event.currentTarget.value);
                setInvalid(false);
            }}
            onBlur={commit}
            onKeyDown={(event) => {
                if (event.key === 'Enter') commit();
                if (event.key === 'Escape') setText(formatValue(tag));
            }}
            className={classNames(
                'min-w-0 flex-1 rounded border bg-gray-900/60 px-1.5 py-0.5 font-mono text-xs text-gray-100 outline-none',
                invalid ? 'border-red-500' : 'border-transparent hover:border-gray-600 focus:border-primary-400'
            )}
        />
    );
};

const ArrayEditor = ({
    tag,
    readOnly,
    onReplace,
}: {
    tag: NbtTag;
    readOnly: boolean;
    onReplace: (tag: NbtTag) => void;
}) => {
    const { t } = useTranslation('client/files');
    const [open, setOpen] = useState(false);
    const [text, setText] = useState('');
    const [invalid, setInvalid] = useState(false);

    const start = () => {
        setText((tag.value as Array<number | bigint>).map(String).join(', '));
        setInvalid(false);
        setOpen(true);
    };

    const commit = () => {
        const parts = text.split(/[\s,;]+/).filter(Boolean);
        const values = parts.map((part) => parseNumber(tag.type, part));
        if (values.some((v) => v === null)) {
            setInvalid(true);
            return;
        }
        onReplace({ ...tag, value: values } as NbtTag);
        setOpen(false);
    };

    if (!open) {
        return (
            <button type={'button'} onClick={start} className={'text-xs text-gray-400 hover:text-gray-100'}>
                {t('nbt.entries', { count: (tag.value as unknown[]).length })} ·{' '}
                {readOnly ? t('nbt.show') : t('nbt.edit')}
            </button>
        );
    }

    return (
        <div className={'flex min-w-0 flex-1 items-start gap-1'}>
            <textarea
                value={text}
                readOnly={readOnly}
                onChange={(event) => setText(event.currentTarget.value)}
                rows={4}
                className={classNames(
                    'min-w-0 flex-1 rounded border bg-gray-900/60 px-1.5 py-1 font-mono text-xs text-gray-100 outline-none',
                    invalid ? 'border-red-500' : 'border-gray-600 focus:border-primary-400'
                )}
            />
            {!readOnly && (
                <button
                    type={'button'}
                    title={t('nbt.apply')}
                    onClick={commit}
                    className={'p-1 text-gray-300 hover:text-primary-300'}
                >
                    <Check className={'h-4 w-4'} />
                </button>
            )}
            <button
                type={'button'}
                title={t('nbt.cancel')}
                onClick={() => setOpen(false)}
                className={'p-1 text-gray-400 hover:text-gray-100'}
            >
                <X className={'h-4 w-4'} />
            </button>
        </div>
    );
};

const AddChild = ({
    tag,
    onDone,
    onCancel,
}: {
    tag: NbtTag & { type: TagType.Compound | TagType.List };
    onDone: () => void;
    onCancel: () => void;
}) => {
    const { t } = useTranslation('client/files');
    const fixed = tag.type === TagType.List && tag.value.length > 0 ? tag.value[0].type : null;
    const [type, setType] = useState<TagType>(fixed ?? TagType.String);
    const [name, setName] = useState('');
    const [error, setError] = useState('');

    const add = () => {
        if (tag.type === TagType.Compound) {
            if (!name) return setError(t('nbt.name_required'));
            if (tag.value.some((entry) => entry.name === name)) return setError(t('nbt.name_taken'));
            tag.value.push({ name, tag: emptyTag(type) });
        } else {
            tag.itemType = type;
            tag.value.push(emptyTag(type));
        }
        onDone();
    };

    return (
        <div className={'flex flex-wrap items-center gap-1 py-1'}>
            <select
                value={type}
                disabled={fixed !== null}
                onChange={(event) => setType(Number(event.currentTarget.value) as TagType)}
                className={'rounded border border-gray-600 bg-gray-700 py-0.5 pl-2 pr-7 text-xs text-gray-100'}
            >
                {CREATABLE.map((type) => (
                    <option key={type} value={type}>
                        {t(`nbt.types.${type}`)}
                    </option>
                ))}
            </select>
            {tag.type === TagType.Compound && (
                <input
                    autoFocus
                    value={name}
                    placeholder={t('nbt.name')}
                    onChange={(event) => {
                        setName(event.currentTarget.value);
                        setError('');
                    }}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') add();
                        if (event.key === 'Escape') onCancel();
                    }}
                    className={
                        'w-40 rounded border border-gray-600 bg-gray-700 px-1.5 py-0.5 font-mono text-xs text-gray-100 outline-none focus:border-primary-400'
                    }
                />
            )}
            <button
                type={'button'}
                onClick={add}
                className={'rounded bg-primary-500 px-2 py-0.5 text-xs text-white hover:bg-primary-400'}
            >
                {t('nbt.add')}
            </button>
            <button type={'button'} onClick={onCancel} className={'px-1 text-xs text-gray-400 hover:text-gray-100'}>
                {t('nbt.cancel')}
            </button>
            {error && <span className={'text-xs text-red-400'}>{error}</span>}
        </div>
    );
};

const NbtNode = ({ name, tag, depth, readOnly, onChange, onRename, onDelete, onReplace, defaultOpen }: NodeProps) => {
    const { t } = useTranslation('client/files');
    const [open, setOpen] = useState(!!defaultOpen);
    const [adding, setAdding] = useState(false);
    const [renaming, setRenaming] = useState(false);
    const [newName, setNewName] = useState(String(name));
    const container = tag.type === TagType.Compound || tag.type === TagType.List;
    const count = childCount(tag);

    const finishRename = () => {
        if (newName !== name && onRename && !onRename(newName)) return;
        setRenaming(false);
    };

    return (
        <div>
            <div
                className={'group flex items-center gap-1.5 rounded py-0.5 pr-2 hover:bg-gray-700/40'}
                style={{ paddingLeft: `${depth * 1.1 + 0.25}rem` }}
            >
                <button
                    type={'button'}
                    onClick={() => setOpen((v) => !v)}
                    className={classNames(
                        'flex h-4 w-4 shrink-0 items-center justify-center text-gray-400',
                        !container && 'invisible'
                    )}
                    aria-label={open ? t('tree.collapse') : t('tree.expand')}
                >
                    <ChevronRight className={classNames('h-3.5 w-3.5 transition-transform', open && 'rotate-90')} />
                </button>
                <span
                    className={classNames(
                        'w-8 shrink-0 rounded px-1 text-center font-mono text-2xs',
                        TYPE_COLORS[tag.type] || 'bg-gray-600/40 text-gray-300'
                    )}
                    title={t(`nbt.types.${tag.type}`)}
                >
                    {TYPE_LABELS[tag.type]}
                </span>
                {renaming ? (
                    <input
                        autoFocus
                        value={newName}
                        onChange={(event) => setNewName(event.currentTarget.value)}
                        onBlur={finishRename}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') finishRename();
                            if (event.key === 'Escape') setRenaming(false);
                        }}
                        className={
                            'w-40 rounded border border-primary-400 bg-gray-900 px-1 py-0.5 font-mono text-xs text-gray-100 outline-none'
                        }
                    />
                ) : (
                    <span
                        className={classNames(
                            'shrink-0 font-mono text-xs',
                            typeof name === 'number' ? 'text-gray-500' : 'text-gray-200'
                        )}
                        onDoubleClick={() => onRename && !readOnly && setRenaming(true)}
                    >
                        {typeof name === 'number' ? `[${name}]` : name || '""'}
                    </span>
                )}
                <span className={'shrink-0 text-gray-600'}>:</span>
                {container ? (
                    <button type={'button'} onClick={() => setOpen((v) => !v)} className={'text-xs text-gray-500'}>
                        {t('nbt.entries', { count: count || 0 })}
                    </button>
                ) : isArray(tag.type) ? (
                    <ArrayEditor tag={tag} readOnly={readOnly} onReplace={onReplace} />
                ) : (
                    <ValueInput tag={tag} readOnly={readOnly} onReplace={onReplace} />
                )}
                {!readOnly && (
                    <span className={'ml-auto flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100'}>
                        {container && (
                            <button
                                type={'button'}
                                title={t('nbt.add_child')}
                                onClick={() => {
                                    setOpen(true);
                                    setAdding(true);
                                }}
                                className={'rounded p-0.5 text-gray-400 hover:bg-gray-600 hover:text-gray-100'}
                            >
                                <Plus className={'h-3.5 w-3.5'} />
                            </button>
                        )}
                        {onRename && (
                            <button
                                type={'button'}
                                title={t('nbt.rename')}
                                onClick={() => {
                                    setNewName(String(name));
                                    setRenaming(true);
                                }}
                                className={'rounded p-0.5 text-gray-400 hover:bg-gray-600 hover:text-gray-100'}
                            >
                                <Pencil className={'h-3.5 w-3.5'} />
                            </button>
                        )}
                        {onDelete && (
                            <button
                                type={'button'}
                                title={t('nbt.delete')}
                                onClick={onDelete}
                                className={'rounded p-0.5 text-gray-400 hover:bg-red-500/20 hover:text-red-400'}
                            >
                                <Trash2 className={'h-3.5 w-3.5'} />
                            </button>
                        )}
                    </span>
                )}
            </div>
            {open && container && (
                <div>
                    {adding && (
                        <div style={{ paddingLeft: `${(depth + 1) * 1.1 + 1.5}rem` }}>
                            <AddChild
                                tag={tag as NbtTag & { type: TagType.Compound | TagType.List }}
                                onCancel={() => setAdding(false)}
                                onDone={() => {
                                    setAdding(false);
                                    onChange();
                                }}
                            />
                        </div>
                    )}
                    {tag.type === TagType.Compound &&
                        tag.value.map((entry, index) => (
                            <NbtNode
                                key={`${entry.name}-${index}`}
                                name={entry.name}
                                tag={entry.tag}
                                depth={depth + 1}
                                readOnly={readOnly}
                                onChange={onChange}
                                onReplace={(next) => {
                                    entry.tag = next;
                                    onChange();
                                }}
                                onRename={(value) => {
                                    if (!value || tag.value.some((other) => other !== entry && other.name === value))
                                        return false;
                                    entry.name = value;
                                    onChange();
                                    return true;
                                }}
                                onDelete={() => {
                                    tag.value.splice(index, 1);
                                    onChange();
                                }}
                            />
                        ))}
                    {tag.type === TagType.List &&
                        tag.value.map((item, index) => (
                            <NbtNode
                                key={index}
                                name={index}
                                tag={item}
                                depth={depth + 1}
                                readOnly={readOnly}
                                onChange={onChange}
                                onReplace={(next) => {
                                    tag.value[index] = next;
                                    onChange();
                                }}
                                onDelete={() => {
                                    tag.value.splice(index, 1);
                                    onChange();
                                }}
                            />
                        ))}
                </div>
            )}
        </div>
    );
};

export default ({ tab, active }: ViewerProps) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const running = ServerContext.useStoreState(
        (state) => state.status.value === 'running' || state.status.value === 'starting'
    );
    const [file, setFile] = useState<NbtFile | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [, setVersion] = useState(0);
    const readOnly = !ide.can.update || !ide.canWriteIn(tab.path);

    const load = useCallback(() => {
        setError(null);
        setFile(null);
        getFileBytes(ide.uuid, tab.path)
            .then(readNbt)
            .then(setFile)
            .catch((error) => setError(error?.response ? httpErrorToHuman(error) : String(error?.message || error)));
    }, [ide.uuid, tab.path]);

    useEffect(load, [ide.uuid]);

    const changed = () => {
        setVersion((v) => v + 1);
        ide.setDirty(tab.id, true);
    };

    const save = () => {
        if (!file || readOnly) return;
        setSaving(true);
        setSaveError(null);
        writeNbt(file)
            .then((bytes) => saveFileContents(ide.uuid, tab.path, new Blob([bytes])))
            .then(() => ide.setDirty(tab.id, false))
            .catch((error) => setSaveError(error?.response ? httpErrorToHuman(error) : String(error?.message || error)))
            .then(() => setSaving(false));
    };

    useSaveShortcut(active, () => {
        // Values are taken over on blur, so let the input finish first.
        (document.activeElement as HTMLElement | null)?.blur();
        setTimeout(save, 0);
    });

    if (error) {
        return (
            <ViewerMessage tab={tab} title={t('nbt.failed')} message={error} onRetry={load}>
                <button
                    type={'button'}
                    onClick={() => ide.openFile(tab.path, undefined, true)}
                    className={'rounded bg-gray-700 px-3 py-1.5 text-sm text-gray-100 hover:bg-gray-600'}
                >
                    {t('preview.edit_text')}
                </button>
            </ViewerMessage>
        );
    }
    if (!file) {
        return (
            <div className={'flex h-full items-center justify-center'}>
                <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
            </div>
        );
    }

    return (
        <div className={'flex h-full flex-col'}>
            {running && !readOnly && (
                <p className={'border-b border-amber-500/30 bg-amber-500/10 px-3 py-1.5 text-xs text-amber-200'}>
                    {t('nbt.running')}
                </p>
            )}
            <div className={'min-h-0 flex-1 overflow-auto p-2'}>
                <NbtNode
                    name={file.name}
                    tag={file.root}
                    depth={0}
                    readOnly={readOnly}
                    onChange={changed}
                    onReplace={(next) => {
                        file.root = next;
                        changed();
                    }}
                    defaultOpen
                />
            </div>
            <div
                className={
                    'flex flex-wrap items-center gap-3 border-t border-gray-700 px-2 py-1 text-2xs text-gray-400'
                }
            >
                <ListTree className={'h-3.5 w-3.5'} />
                {saveError ? (
                    <span className={'min-w-0 flex-1 truncate text-red-400'}>{saveError}</span>
                ) : (
                    <span className={'min-w-0 flex-1 truncate'}>
                        {t('nbt.format', {
                            compression: t(`nbt.compression.${file.compression}`),
                            edition: file.littleEndian ? 'Bedrock' : 'Java',
                        })}
                        {' · '}
                        {readOnly ? t('editor.read_only') : tab.dirty ? t('editor.unsaved') : t('editor.saved')}
                    </span>
                )}
                {!readOnly && (
                    <button
                        type={'button'}
                        onClick={save}
                        disabled={saving || !tab.dirty}
                        className={classNames(
                            'flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium disabled:cursor-not-allowed',
                            tab.dirty ? 'bg-primary-500 text-white hover:bg-primary-400' : 'bg-gray-700 text-gray-400'
                        )}
                    >
                        {saving ? (
                            <Loader2 className={'h-3.5 w-3.5 animate-spin'} />
                        ) : (
                            <Save className={'h-3.5 w-3.5'} />
                        )}
                        {t('editor.save')}
                    </button>
                )}
            </div>
        </div>
    );
};
