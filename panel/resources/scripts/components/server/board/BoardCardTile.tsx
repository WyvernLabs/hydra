import React from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { AlignLeft, ListChecks } from 'lucide-react';
import { BoardCard, progressOf } from '@/api/server/board';
import { contrastText, useBoard } from '@/components/server/board/context';
import Avatar from '@/components/server/board/Avatar';

// Hydra: a card ("block") as shown in its column.

interface Props {
    card: BoardCard;
    dragging: boolean;
    onDragStart: (event: React.DragEvent) => void;
    onDragEnd: () => void;
    onDragOver: (event: React.DragEvent) => void;
}

export default ({ card, dragging, onDragStart, onDragEnd, onDragOver }: Props) => {
    const { t } = useTranslation('client/board');
    const { board, can, openCard } = useBoard();
    const tags = board.tags.filter((tag) => card.tags.includes(tag.id));
    const progress = progressOf(card);
    const mine = card.claimedBy?.id === board.me.id;

    return (
        <div
            role={'button'}
            tabIndex={0}
            draggable={can.update}
            onDragStart={onDragStart}
            onDragEnd={onDragEnd}
            onDragOver={onDragOver}
            onClick={() => openCard(card.id)}
            onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    openCard(card.id);
                }
            }}
            className={classNames(
                'hydra-float group rounded-lg border bg-gray-700 p-2.5 text-left shadow-sm outline-none transition-colors',
                'hover:border-gray-400 focus-visible:border-primary-400',
                can.update && 'cursor-grab active:cursor-grabbing',
                mine ? 'border-primary-500/70' : 'border-gray-600',
                dragging && 'opacity-40'
            )}
        >
            {tags.length > 0 && (
                <div className={'mb-1.5 flex flex-wrap gap-1'}>
                    {tags.map((tag) => (
                        <span
                            key={tag.id}
                            className={'rounded px-1.5 py-px text-2xs font-semibold'}
                            style={{ backgroundColor: tag.color, color: contrastText(tag.color) }}
                        >
                            {tag.name}
                        </span>
                    ))}
                </div>
            )}
            <p className={'break-words text-sm font-medium text-gray-50'}>{card.name}</p>
            {progress.total > 0 && (
                <div className={'mt-2'}>
                    <div className={'mb-1 flex items-center justify-between text-2xs text-gray-400'}>
                        <span className={'flex items-center gap-1'}>
                            <ListChecks className={'h-3 w-3'} />
                            {progress.done}/{progress.total}
                        </span>
                        <span>{progress.percent} %</span>
                    </div>
                    <div className={'h-1.5 overflow-hidden rounded-full bg-gray-900/60'}>
                        <div
                            className={classNames(
                                'h-full rounded-full transition-all',
                                progress.percent === 100 ? 'bg-green-500' : 'bg-primary-500'
                            )}
                            style={{ width: `${progress.percent}%` }}
                        />
                    </div>
                </div>
            )}
            {(card.description || card.claimedBy) && (
                <div className={'mt-2 flex items-center gap-2 text-2xs text-gray-400'}>
                    {card.description && (
                        <AlignLeft className={'h-3.5 w-3.5 shrink-0'} aria-label={t('card.has_description')} />
                    )}
                    {card.claimedBy && (
                        <span
                            className={'ml-auto flex min-w-0 items-center gap-1.5'}
                            title={t('card.claimed_by', { user: card.claimedBy.username })}
                        >
                            <span className={'truncate'}>{mine ? t('card.you') : card.claimedBy.username}</span>
                            <Avatar user={card.claimedBy} />
                        </span>
                    )}
                </div>
            )}
        </div>
    );
};
