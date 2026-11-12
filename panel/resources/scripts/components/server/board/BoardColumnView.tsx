import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { GripVertical, Pencil, Plus, Trash2 } from 'lucide-react';
import { BoardColumn, createCard, deleteColumn } from '@/api/server/board';
import { Dialog } from '@/components/elements/dialog';
import { FLASH, useBoard } from '@/components/server/board/context';
import BoardCardTile from '@/components/server/board/BoardCardTile';

// Hydra: one column of the board with its cards. Drag and drop is handled by
// the board, the column only reports where something is dragged over.

export type CardDrop = { column: number; index: number };

interface Props {
    column: BoardColumn;
    draggedCard: number | null;
    draggedColumn: boolean;
    cardTarget: CardDrop | null;
    onEdit: () => void;
    onCardDragStart: (event: React.DragEvent, card: number) => void;
    onColumnDragStart: (event: React.DragEvent) => void;
    onDragEnd: () => void;
    onCardOver: (target: CardDrop) => void;
    onColumnOver: (event: React.DragEvent) => void;
}

const Placeholder = () => <div className={'h-1 rounded-full bg-primary-400'} />;

export default ({
    column,
    draggedCard,
    draggedColumn,
    cardTarget,
    onEdit,
    onCardDragStart,
    onColumnDragStart,
    onDragEnd,
    onCardOver,
    onColumnOver,
}: Props) => {
    const { t } = useTranslation('client/board');
    const { board, can, run } = useBoard();
    const [adding, setAdding] = useState(false);
    const [name, setName] = useState('');
    const [confirmDelete, setConfirmDelete] = useState(false);
    const others = column.cards.filter((card) => card.id !== draggedCard);
    const target = cardTarget?.column === column.id ? cardTarget.index : null;
    const totalCards = board.columns.reduce((sum, c) => sum + c.cards.length, 0);
    const full = totalCards >= board.limits.cards;

    const add = (event: React.FormEvent) => {
        event.preventDefault();
        const value = name.trim();
        if (!value) return;
        run((uuid) => createCard(uuid, column.id, { name: value })).then((ok) => ok && setName(''));
    };

    // Where in the list a card dragged over "card" would land.
    const overCard = (event: React.DragEvent, card: number) => {
        if (draggedCard === null) return;
        event.preventDefault();
        event.stopPropagation();
        event.dataTransfer.dropEffect = 'move';
        if (card === draggedCard) {
            const index = column.cards.findIndex((c) => c.id === card);
            onCardOver({ column: column.id, index: column.cards.slice(0, index).filter((c) => c.id !== card).length });
            return;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        const below = event.clientY > rect.top + rect.height / 2;
        onCardOver({ column: column.id, index: others.findIndex((c) => c.id === card) + (below ? 1 : 0) });
    };

    return (
        <div
            onDragOver={onColumnOver}
            className={classNames(
                'hydra-float flex max-h-[calc(100vh-13rem)] w-72 shrink-0 flex-col rounded-xl border border-gray-700 bg-gray-800 shadow',
                draggedColumn && 'opacity-40'
            )}
            style={column.color ? { borderTopColor: column.color, borderTopWidth: 4 } : undefined}
        >
            <div
                draggable={can.columns}
                onDragStart={onColumnDragStart}
                onDragEnd={onDragEnd}
                className={classNames(
                    'group flex items-center gap-1.5 rounded-t-xl px-3 py-2',
                    can.columns && 'cursor-grab active:cursor-grabbing'
                )}
                style={column.color ? { backgroundColor: `${column.color}26` } : undefined}
            >
                {can.columns && <GripVertical className={'-ml-1.5 h-4 w-4 shrink-0 text-gray-500'} />}
                {column.color && (
                    <span className={'h-2.5 w-2.5 shrink-0 rounded-full'} style={{ backgroundColor: column.color }} />
                )}
                <h3 className={'min-w-0 flex-1 truncate text-sm font-semibold text-gray-50'} title={column.name}>
                    {column.name}
                </h3>
                <span className={'rounded-full bg-gray-900/50 px-2 text-2xs text-gray-300'}>{column.cards.length}</span>
                {can.columns && (
                    <>
                        <button
                            type={'button'}
                            title={t('column.edit')}
                            aria-label={t('column.edit')}
                            onClick={onEdit}
                            className={'rounded p-1 text-gray-400 hover:bg-gray-700 hover:text-gray-100'}
                        >
                            <Pencil className={'h-3.5 w-3.5'} />
                        </button>
                        <button
                            type={'button'}
                            title={t('column.delete')}
                            aria-label={t('column.delete')}
                            onClick={() => setConfirmDelete(true)}
                            className={'rounded p-1 text-gray-400 hover:bg-red-500/20 hover:text-red-400'}
                        >
                            <Trash2 className={'h-3.5 w-3.5'} />
                        </button>
                    </>
                )}
            </div>

            <div
                onDragOver={(event) => {
                    if (draggedCard === null) return;
                    event.preventDefault();
                    event.dataTransfer.dropEffect = 'move';
                    onCardOver({ column: column.id, index: others.length });
                }}
                className={'flex min-h-[3rem] flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2 pt-1'}
            >
                {column.cards.map((card) => (
                    <React.Fragment key={card.id}>
                        {target !== null && card.id !== draggedCard && others.indexOf(card) === target && (
                            <Placeholder />
                        )}
                        <BoardCardTile
                            card={card}
                            dragging={card.id === draggedCard}
                            onDragStart={(event) => onCardDragStart(event, card.id)}
                            onDragEnd={onDragEnd}
                            onDragOver={(event) => overCard(event, card.id)}
                        />
                    </React.Fragment>
                ))}
                {target !== null && target >= others.length && <Placeholder />}
                {column.cards.length === 0 && target === null && (
                    <p className={'py-3 text-center text-xs text-gray-500'}>{t('column.empty')}</p>
                )}
            </div>

            {can.create && (
                <div className={'border-t border-gray-700 p-2'}>
                    {adding ? (
                        <form onSubmit={add}>
                            <input
                                autoFocus
                                value={name}
                                maxLength={191}
                                disabled={full}
                                placeholder={
                                    full ? t('card.full', { max: board.limits.cards }) : t('card.new_placeholder')
                                }
                                onChange={(event) => setName(event.currentTarget.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Escape') {
                                        setAdding(false);
                                        setName('');
                                    }
                                }}
                                onBlur={() => !name.trim() && setAdding(false)}
                                className={
                                    'w-full rounded border border-gray-600 bg-gray-700 px-2 py-1.5 text-sm text-gray-100 outline-none focus:border-primary-400'
                                }
                            />
                            <p className={'mt-1 px-0.5 text-2xs text-gray-500'}>{t('card.new_help')}</p>
                        </form>
                    ) : (
                        <button
                            type={'button'}
                            onClick={() => setAdding(true)}
                            className={
                                'flex w-full items-center gap-1.5 rounded px-2 py-1.5 text-sm text-gray-400 hover:bg-gray-700 hover:text-gray-100'
                            }
                        >
                            <Plus className={'h-4 w-4'} />
                            {t('card.add')}
                        </button>
                    )}
                </div>
            )}

            <Dialog.Confirm
                open={confirmDelete}
                onClose={() => setConfirmDelete(false)}
                title={t('column.delete_title', { name: column.name })}
                confirm={t('column.delete')}
                onConfirmed={() => {
                    setConfirmDelete(false);
                    run((uuid) => deleteColumn(uuid, column.id), FLASH);
                }}
            >
                {column.cards.length > 0
                    ? t('column.delete_with_cards', { count: column.cards.length })
                    : t('column.delete_empty')}
            </Dialog.Confirm>
        </div>
    );
};
