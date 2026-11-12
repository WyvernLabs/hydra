import React, { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Trash2 } from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import { BoardTag, createTag, deleteTag, updateTag } from '@/api/server/board';
import { COLORS, contrastText, DIALOG_FLASH, useBoard } from '@/components/server/board/context';
import ColorPicker from '@/components/server/board/ColorPicker';

// Hydra: the tags of the board with name and color, e.g. priorities.

const inputClass =
    'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-1 text-sm text-gray-100 outline-none focus:border-primary-400';

const TagRow = ({ tag }: { tag: BoardTag }) => {
    const { t } = useTranslation('client/board');
    const { run } = useBoard();
    const [name, setName] = useState(tag.name);
    const [color, setColor] = useState(tag.color);
    const timer = useRef<ReturnType<typeof setTimeout>>();

    useEffect(() => setName(tag.name), [tag.name]);
    useEffect(() => setColor(tag.color), [tag.color]);
    useEffect(() => () => clearTimeout(timer.current), []);

    const saveName = () => {
        const value = name.trim();
        if (!value) return setName(tag.name);
        if (value !== tag.name) run((uuid) => updateTag(uuid, tag.id, { name: value }), DIALOG_FLASH);
    };

    // The color field fires while the picker is dragged, so wait for a pause.
    const changeColor = (value: string | null) => {
        if (!value) return;
        setColor(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => {
            if (value !== tag.color) run((uuid) => updateTag(uuid, tag.id, { color: value }), DIALOG_FLASH);
        }, 400);
    };

    return (
        <div className={'rounded-lg border border-gray-700 bg-gray-800 p-3'}>
            <div className={'mb-2 flex items-center gap-2'}>
                <span
                    className={'shrink-0 rounded px-2 py-0.5 text-xs font-semibold'}
                    style={{ backgroundColor: color, color: contrastText(color) }}
                >
                    {name || tag.name}
                </span>
                <input
                    value={name}
                    maxLength={32}
                    aria-label={t('tags.name')}
                    onChange={(event) => setName(event.currentTarget.value)}
                    onBlur={saveName}
                    onKeyDown={(event) => event.key === 'Enter' && event.currentTarget.blur()}
                    className={inputClass}
                />
                <button
                    type={'button'}
                    title={t('tags.delete')}
                    aria-label={t('tags.delete')}
                    onClick={() => run((uuid) => deleteTag(uuid, tag.id), DIALOG_FLASH)}
                    className={'rounded p-1.5 text-gray-400 hover:bg-red-500/20 hover:text-red-400'}
                >
                    <Trash2 className={'h-4 w-4'} />
                </button>
            </div>
            <ColorPicker value={color} onChange={changeColor} />
        </div>
    );
};

export default ({ open, onClose }: { open: boolean; onClose: () => void }) => {
    const { t } = useTranslation('client/board');
    const { board, run } = useBoard();
    const [name, setName] = useState('');
    const [color, setColor] = useState<string>(COLORS[0]);
    const full = board.tags.length >= board.limits.tags;

    const add = (event: React.FormEvent) => {
        event.preventDefault();
        if (!name.trim() || full) return;
        run((uuid) => createTag(uuid, { name: name.trim(), color }), DIALOG_FLASH).then((ok) => {
            if (!ok) return;
            setName('');
            setColor(COLORS[(COLORS.indexOf(color) + 1) % COLORS.length]);
        });
    };

    return (
        <Dialog open={open} onClose={onClose} title={t('tags.title')} description={t('tags.help')}>
            <FlashMessageRender byKey={DIALOG_FLASH} className={'mb-3'} />
            <div className={'max-h-[50vh] space-y-2 overflow-y-auto pr-1'}>
                {board.tags.map((tag) => (
                    <TagRow key={tag.id} tag={tag} />
                ))}
                {board.tags.length === 0 && (
                    <p className={'py-3 text-center text-sm text-gray-400'}>{t('tags.empty')}</p>
                )}
            </div>
            {full ? (
                <p className={'mt-3 text-xs text-gray-400'}>{t('tags.full', { max: board.limits.tags })}</p>
            ) : (
                <form onSubmit={add} className={'mt-4 rounded-lg border border-dashed border-gray-600 p-3'}>
                    <div className={'mb-2 flex items-center gap-2'}>
                        <input
                            value={name}
                            maxLength={32}
                            placeholder={t('tags.new_placeholder')}
                            onChange={(event) => setName(event.currentTarget.value)}
                            className={inputClass}
                        />
                        <Button type={'submit'} size={Button.Sizes.Small} disabled={!name.trim()}>
                            <Plus className={'mr-1 h-4 w-4'} />
                            {t('tags.add')}
                        </Button>
                    </div>
                    <ColorPicker value={color} onChange={(value) => value && setColor(value)} />
                </form>
            )}
            <Dialog.Footer>
                <Button.Text onClick={onClose}>{t('actions.close')}</Button.Text>
            </Dialog.Footer>
        </Dialog>
    );
};
