import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Check, Hand, ListChecks, Plus, Tags, Trash2, Undo2, X } from 'lucide-react';
import { Dialog } from '@/components/elements/dialog';
import { Button } from '@/components/elements/button/index';
import FlashMessageRender from '@/components/FlashMessageRender';
import { formatDistanceToNow } from '@/lib/date';
import {
    BoardChecklistItem,
    claimCard,
    createChecklistItem,
    deleteCard,
    deleteChecklistItem,
    moveCard,
    progressOf,
    releaseCard,
    updateCard,
    updateChecklistItem,
} from '@/api/server/board';
import { contrastText, DIALOG_FLASH, findCard, useBoard } from '@/components/server/board/context';
import Avatar from '@/components/server/board/Avatar';

// Hydra: everything about one card ("block"): name, description, tags,
// checklist, claim, column and delete.

interface Props {
    card: number | null;
    onClose: () => void;
    onManageTags: () => void;
}

const fieldClass =
    'w-full rounded border border-gray-600 bg-gray-700 px-3 py-2 text-sm text-gray-100 outline-none focus:border-primary-400 disabled:opacity-70';

const ChecklistRow = ({ card, item }: { card: number; item: BoardChecklistItem }) => {
    const { t } = useTranslation('client/board');
    const { can, run } = useBoard();
    const [text, setText] = useState(item.text);
    const [editing, setEditing] = useState(false);

    useEffect(() => setText(item.text), [item.text]);

    const save = () => {
        setEditing(false);
        const value = text.trim();
        if (!value) return setText(item.text);
        if (value !== item.text) run((uuid) => updateChecklistItem(uuid, card, item.id, { text: value }), DIALOG_FLASH);
    };

    return (
        <li className={'group flex items-center gap-2 rounded px-1 py-1 hover:bg-gray-700/50'}>
            <button
                type={'button'}
                role={'checkbox'}
                aria-checked={item.done}
                aria-label={item.text}
                disabled={!can.update}
                onClick={() =>
                    run((uuid) => updateChecklistItem(uuid, card, item.id, { done: !item.done }), DIALOG_FLASH)
                }
                className={classNames(
                    'flex h-4 w-4 shrink-0 items-center justify-center rounded border',
                    item.done ? 'border-green-500 bg-green-500 text-white' : 'border-gray-400 bg-gray-800',
                    can.update ? 'cursor-pointer' : 'cursor-default'
                )}
            >
                {item.done && <Check className={'h-3 w-3'} strokeWidth={3} />}
            </button>
            {editing ? (
                <input
                    autoFocus
                    value={text}
                    maxLength={255}
                    onChange={(event) => setText(event.currentTarget.value)}
                    onBlur={save}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') event.currentTarget.blur();
                        if (event.key === 'Escape') {
                            event.stopPropagation();
                            setText(item.text);
                            setEditing(false);
                        }
                    }}
                    className={
                        'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-0.5 text-sm text-gray-100 outline-none focus:border-primary-400'
                    }
                />
            ) : (
                <span
                    onClick={() => can.update && setEditing(true)}
                    className={classNames(
                        'min-w-0 flex-1 break-words text-sm',
                        item.done ? 'text-gray-400 line-through' : 'text-gray-100',
                        can.update && 'cursor-text'
                    )}
                >
                    {item.text}
                </span>
            )}
            {can.update && (
                <button
                    type={'button'}
                    title={t('checklist.delete')}
                    aria-label={t('checklist.delete')}
                    onClick={() => run((uuid) => deleteChecklistItem(uuid, card, item.id), DIALOG_FLASH)}
                    className={
                        'rounded p-1 text-gray-500 opacity-0 hover:text-red-400 focus:opacity-100 group-hover:opacity-100'
                    }
                >
                    <X className={'h-3.5 w-3.5'} />
                </button>
            )}
        </li>
    );
};

export default ({ card: id, onClose, onManageTags }: Props) => {
    const { t } = useTranslation('client/board');
    const { board, can, run } = useBoard();
    const found = id === null ? null : findCard(board, id);
    const card = found?.card;
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [item, setItem] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);

    useEffect(() => {
        setName(card?.name || '');
        setDescription(card?.description || '');
    }, [card?.id, card?.name, card?.description]);

    useEffect(() => {
        setItem('');
        setConfirmDelete(false);
    }, [id]);

    // The card was deleted, e.g. by someone else.
    useEffect(() => {
        if (id !== null && !found) onClose();
    }, [id, found]);

    if (!card || !found) {
        return <Dialog open={false} onClose={onClose} />;
    }

    const progress = progressOf(card);
    const mine = card.claimedBy?.id === board.me.id;
    const canRelease = card.claimedBy && ((mine && can.claim) || board.me.releaseAny);
    const tagsOff = board.limits.tags === 0;
    const checklistOff = board.limits.items === 0;

    const saveName = () => {
        const value = name.trim();
        if (!value) return setName(card.name);
        if (value !== card.name) run((uuid) => updateCard(uuid, card.id, { name: value }), DIALOG_FLASH);
    };

    const saveDescription = () => {
        if (description.trim() !== (card.description || '')) {
            run((uuid) => updateCard(uuid, card.id, { description: description.trim() || null }), DIALOG_FLASH);
        }
    };

    const toggleTag = (tag: number) => {
        const tags = card.tags.includes(tag) ? card.tags.filter((t) => t !== tag) : [...card.tags, tag];
        run((uuid) => updateCard(uuid, card.id, { tags }), DIALOG_FLASH);
    };

    const addItem = (event: React.FormEvent) => {
        event.preventDefault();
        const text = item.trim();
        if (!text) return;
        run((uuid) => createChecklistItem(uuid, card.id, text), DIALOG_FLASH).then((ok) => ok && setItem(''));
    };

    const remove = () =>
        run((uuid) => deleteCard(uuid, card.id), DIALOG_FLASH).then((ok) => {
            if (ok) onClose();
        });

    return (
        <Dialog open onClose={onClose} wide title={t('card.title')}>
            <FlashMessageRender byKey={DIALOG_FLASH} className={'mb-3'} />
            <div className={'grid gap-5 md:grid-cols-3'}>
                <div className={'space-y-4 md:col-span-2'}>
                    <div>
                        <label htmlFor={'board-card-name'} className={'mb-1 block text-xs text-gray-300'}>
                            {t('card.name')}
                        </label>
                        <input
                            id={'board-card-name'}
                            value={name}
                            maxLength={191}
                            disabled={!can.update}
                            onChange={(event) => setName(event.currentTarget.value)}
                            onBlur={saveName}
                            onKeyDown={(event) => event.key === 'Enter' && event.currentTarget.blur()}
                            className={classNames(fieldClass, 'font-medium')}
                        />
                    </div>
                    <div>
                        <label htmlFor={'board-card-description'} className={'mb-1 block text-xs text-gray-300'}>
                            {t('card.description')}
                        </label>
                        <textarea
                            id={'board-card-description'}
                            value={description}
                            rows={5}
                            maxLength={10000}
                            disabled={!can.update}
                            placeholder={can.update ? t('card.description_placeholder') : t('card.no_description')}
                            onChange={(event) => setDescription(event.currentTarget.value)}
                            onBlur={saveDescription}
                            className={classNames(fieldClass, 'resize-y')}
                        />
                    </div>
                    {!checklistOff && (
                        <div>
                            <div className={'mb-1.5 flex items-center justify-between'}>
                                <p className={'flex items-center gap-1.5 text-xs text-gray-300'}>
                                    <ListChecks className={'h-3.5 w-3.5'} />
                                    {t('checklist.title')}
                                </p>
                                {progress.total > 0 && (
                                    <span className={'text-xs text-gray-400'}>
                                        {t('checklist.progress', { done: progress.done, total: progress.total })} ·{' '}
                                        {progress.percent} %
                                    </span>
                                )}
                            </div>
                            {progress.total > 0 && (
                                <div className={'mb-2 h-2 overflow-hidden rounded-full bg-gray-900/60'}>
                                    <div
                                        className={classNames(
                                            'h-full rounded-full transition-all',
                                            progress.percent === 100 ? 'bg-green-500' : 'bg-primary-500'
                                        )}
                                        style={{ width: `${progress.percent}%` }}
                                    />
                                </div>
                            )}
                            <ul className={'space-y-0.5'}>
                                {card.checklist.map((entry) => (
                                    <ChecklistRow key={entry.id} card={card.id} item={entry} />
                                ))}
                            </ul>
                            {progress.total === 0 && !can.update && (
                                <p className={'text-sm text-gray-400'}>{t('checklist.empty')}</p>
                            )}
                            {can.update &&
                                (progress.total >= board.limits.items ? (
                                    <p className={'mt-2 text-xs text-gray-400'}>
                                        {t('checklist.full', { max: board.limits.items })}
                                    </p>
                                ) : (
                                    <form onSubmit={addItem} className={'mt-2 flex gap-2'}>
                                        <input
                                            value={item}
                                            maxLength={255}
                                            placeholder={t('checklist.add_placeholder')}
                                            onChange={(event) => setItem(event.currentTarget.value)}
                                            className={classNames(fieldClass, 'py-1.5')}
                                        />
                                        <Button type={'submit'} size={Button.Sizes.Small} disabled={!item.trim()}>
                                            <Plus className={'h-4 w-4'} />
                                        </Button>
                                    </form>
                                ))}
                        </div>
                    )}
                </div>

                <div className={'space-y-4'}>
                    <div>
                        <p className={'mb-1.5 text-xs text-gray-300'}>{t('card.claim')}</p>
                        {card.claimedBy ? (
                            <div className={'rounded-lg border border-gray-600 bg-gray-800 p-2.5'}>
                                <div className={'flex items-center gap-2'}>
                                    <Avatar user={card.claimedBy} className={'h-7 w-7'} />
                                    <div className={'min-w-0'}>
                                        <p className={'truncate text-sm text-gray-100'}>
                                            {mine ? t('card.you') : card.claimedBy.username}
                                        </p>
                                        {card.claimedAt && (
                                            <p className={'text-2xs text-gray-400'}>
                                                {t('card.claimed_since', {
                                                    time: formatDistanceToNow(card.claimedAt, { addSuffix: true }),
                                                })}
                                            </p>
                                        )}
                                    </div>
                                </div>
                                {canRelease && (
                                    <Button.Text
                                        size={Button.Sizes.Small}
                                        className={'mt-2 w-full'}
                                        onClick={() => run((uuid) => releaseCard(uuid, card.id), DIALOG_FLASH)}
                                    >
                                        <Undo2 className={'mr-1.5 h-4 w-4'} />
                                        {t('card.release')}
                                    </Button.Text>
                                )}
                            </div>
                        ) : can.claim ? (
                            <Button
                                size={Button.Sizes.Small}
                                className={'w-full'}
                                onClick={() => run((uuid) => claimCard(uuid, card.id), DIALOG_FLASH)}
                            >
                                <Hand className={'mr-1.5 h-4 w-4'} />
                                {t('card.claim_action')}
                            </Button>
                        ) : (
                            <p className={'text-sm text-gray-400'}>{t('card.unclaimed')}</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor={'board-card-column'} className={'mb-1.5 block text-xs text-gray-300'}>
                            {t('card.column')}
                        </label>
                        <select
                            id={'board-card-column'}
                            value={found.column.id}
                            disabled={!can.update}
                            onChange={(event) => {
                                const target = Number(event.currentTarget.value);
                                const length = board.columns.find((c) => c.id === target)?.cards.length || 0;
                                run((uuid) => moveCard(uuid, card.id, target, length), DIALOG_FLASH);
                            }}
                            className={classNames(fieldClass, 'py-1.5')}
                        >
                            {board.columns.map((column) => (
                                <option key={column.id} value={column.id}>
                                    {column.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    {!tagsOff && (
                        <div>
                            <div className={'mb-1.5 flex items-center justify-between'}>
                                <p className={'flex items-center gap-1.5 text-xs text-gray-300'}>
                                    <Tags className={'h-3.5 w-3.5'} />
                                    {t('card.tags')}
                                </p>
                                {can.tags && (
                                    <button
                                        type={'button'}
                                        onClick={onManageTags}
                                        className={'text-xs text-primary-300 hover:text-primary-200'}
                                    >
                                        {t('tags.manage')}
                                    </button>
                                )}
                            </div>
                            <div className={'flex flex-wrap gap-1.5'}>
                                {board.tags.map((tag) => {
                                    const active = card.tags.includes(tag.id);
                                    if (!active && !can.update) return null;

                                    return (
                                        <button
                                            key={tag.id}
                                            type={'button'}
                                            disabled={!can.update}
                                            aria-pressed={active}
                                            onClick={() => toggleTag(tag.id)}
                                            className={classNames(
                                                'rounded px-2 py-0.5 text-xs font-semibold transition-opacity',
                                                !active && 'opacity-40 hover:opacity-80'
                                            )}
                                            style={{ backgroundColor: tag.color, color: contrastText(tag.color) }}
                                        >
                                            {tag.name}
                                        </button>
                                    );
                                })}
                                {board.tags.length === 0 && (
                                    <p className={'text-xs text-gray-400'}>{t('tags.empty')}</p>
                                )}
                            </div>
                        </div>
                    )}

                    <p className={'text-2xs text-gray-500'}>
                        {t('card.created', { time: formatDistanceToNow(card.createdAt, { addSuffix: true }) })}
                    </p>
                </div>
            </div>
            <Dialog.Footer>
                {can.delete &&
                    (confirmDelete ? (
                        <>
                            <span className={'mr-auto text-sm text-red-300'}>{t('card.delete_confirm')}</span>
                            <Button.Text onClick={() => setConfirmDelete(false)}>{t('actions.cancel')}</Button.Text>
                            <Button.Danger onClick={remove}>{t('card.delete')}</Button.Danger>
                        </>
                    ) : (
                        <Button.Text className={'mr-auto'} onClick={() => setConfirmDelete(true)}>
                            <Trash2 className={'mr-1.5 h-4 w-4'} />
                            {t('card.delete')}
                        </Button.Text>
                    ))}
                {!confirmDelete && <Button onClick={onClose}>{t('actions.close')}</Button>}
            </Dialog.Footer>
        </Dialog>
    );
};
