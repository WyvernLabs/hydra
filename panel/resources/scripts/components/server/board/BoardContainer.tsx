import React, { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Columns3, Plus, RefreshCw, Tags } from 'lucide-react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import FlashMessageRender from '@/components/FlashMessageRender';
import Spinner from '@/components/elements/Spinner';
import { Button } from '@/components/elements/button/index';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import useFlash from '@/plugins/useFlash';
import { Board, BoardColumn, getBoard, moveCard, orderColumns } from '@/api/server/board';
import {
    BoardContext,
    BoardContextType,
    DIALOG_FLASH,
    FLASH,
    moveCardLocally,
} from '@/components/server/board/context';
import BoardColumnView, { CardDrop } from '@/components/server/board/BoardColumnView';
import ColumnDialog from '@/components/server/board/ColumnDialog';
import CardDialog from '@/components/server/board/CardDialog';
import TagsDialog from '@/components/server/board/TagsDialog';

// Hydra: Kanban board of a server (addon "board"). Columns and cards are moved
// by drag and drop; the board reloads itself so changes of others show up.

const DRAG_TYPE = 'application/x-hydra-board';
const REFRESH = 15000;

type Drag = { kind: 'card'; id: number } | { kind: 'column'; id: number };

export default () => {
    const { t } = useTranslation('client/board');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [columns, tags, create, update, remove, claim] = usePermissions([
        'board.columns',
        'board.tags',
        'board.create',
        'board.update',
        'board.delete',
        'board.claim',
    ]);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [board, setBoard] = useState<Board | null>(null);
    const [drag, setDrag] = useState<Drag | null>(null);
    const [cardTarget, setCardTarget] = useState<CardDrop | null>(null);
    const [columnTarget, setColumnTarget] = useState<number | null>(null);
    const [card, setCard] = useState<number | null>(null);
    const [columnDialog, setColumnDialog] = useState<{ column: BoardColumn | null } | null>(null);
    const [tagsOpen, setTagsOpen] = useState(false);
    // Card to show again after the tags were managed from its dialog.
    const [returnTo, setReturnTo] = useState<number | null>(null);
    const busy = useRef(0);
    const dragging = useRef(false);

    const load = useCallback(
        () =>
            getBoard(uuid)
                .then(setBoard)
                .catch((error) => clearAndAddHttpError({ key: FLASH, error })),
        [uuid]
    );

    useEffect(() => {
        clearFlashes(FLASH);
        load();
    }, [load]);

    // Changes of other people show up without reloading the page.
    useEffect(() => {
        const timer = setInterval(() => {
            if (!document.hidden && !dragging.current && busy.current === 0) load();
        }, REFRESH);

        return () => clearInterval(timer);
    }, [load]);

    const run: BoardContextType['run'] = (request, flash = FLASH) => {
        busy.current++;
        clearFlashes(flash);

        return request(uuid)
            .then((next) => {
                setBoard(next);
                return true;
            })
            .catch((error) => {
                clearAndAddHttpError({ key: flash, error });
                // Show what the panel stored, e.g. after a failed drag and drop.
                load();
                return false;
            })
            .finally(() => busy.current--);
    };

    const endDrag = () => {
        dragging.current = false;
        setDrag(null);
        setCardTarget(null);
        setColumnTarget(null);
    };

    const startDrag = (event: React.DragEvent, next: Drag) => {
        event.stopPropagation();
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData(DRAG_TYPE, JSON.stringify(next));
        dragging.current = true;
        // Start after the browser took its drag picture, so the source is not faded on it.
        setTimeout(() => setDrag(next), 0);
    };

    const onColumnOver = (event: React.DragEvent, index: number) => {
        if (drag?.kind !== 'column' || !board) return;
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        const rect = event.currentTarget.getBoundingClientRect();
        const right = event.clientX > rect.left + rect.width / 2;
        const others = board.columns.filter((c) => c.id !== drag.id);
        const self = board.columns[index];
        if (self.id === drag.id) {
            setColumnTarget(board.columns.slice(0, index).filter((c) => c.id !== drag.id).length);
            return;
        }
        setColumnTarget(others.findIndex((c) => c.id === self.id) + (right ? 1 : 0));
    };

    const onDrop = (event: React.DragEvent) => {
        if (!drag || !board) return;
        event.preventDefault();

        if (drag.kind === 'card' && cardTarget) {
            const { id } = drag;
            const { column, index } = cardTarget;
            setBoard(moveCardLocally(board, id, column, index));
            run((uuid) => moveCard(uuid, id, column, index));
        } else if (drag.kind === 'column' && columnTarget !== null) {
            const moved = board.columns.find((c) => c.id === drag.id)!;
            const order = board.columns.filter((c) => c.id !== drag.id);
            order.splice(columnTarget, 0, moved);
            if (order.some((c, i) => c.id !== board.columns[i].id)) {
                setBoard({ ...board, columns: order });
                run((uuid) =>
                    orderColumns(
                        uuid,
                        order.map((c) => c.id)
                    )
                );
            }
        }
        endDrag();
    };

    const context: BoardContextType | null = board && {
        uuid,
        board,
        can: { columns, tags, create, update, delete: remove, claim },
        run,
        preview: setBoard,
        openCard: (id) => {
            clearFlashes(DIALOG_FLASH);
            setCard(id);
        },
    };

    const cardCount = board ? board.columns.reduce((sum, c) => sum + c.cards.length, 0) : 0;
    const otherColumns = board && drag?.kind === 'column' ? board.columns.filter((c) => c.id !== drag.id) : null;

    return (
        <ServerContentBlock title={t('title')}>
            <div className={'mb-4 flex flex-wrap items-center gap-3'}>
                <div className={'min-w-0 flex-1'}>
                    <h2 className={'text-lg text-gray-50'}>{t('title')}</h2>
                    <p className={'text-xs text-gray-400'}>
                        {board ? t('summary', { columns: board.columns.length, cards: cardCount }) : t('loading')}
                    </p>
                </div>
                <button
                    type={'button'}
                    title={t('actions.refresh')}
                    aria-label={t('actions.refresh')}
                    onClick={() => load()}
                    className={'rounded p-2 text-gray-400 hover:bg-gray-700 hover:text-gray-100'}
                >
                    <RefreshCw className={'h-4 w-4'} />
                </button>
                {board && tags && board.limits.tags > 0 && (
                    <Button.Text size={Button.Sizes.Small} onClick={() => setTagsOpen(true)}>
                        <Tags className={'mr-1.5 h-4 w-4'} />
                        {t('tags.manage')}
                    </Button.Text>
                )}
                {board && columns && board.columns.length < board.limits.columns && (
                    <Button size={Button.Sizes.Small} onClick={() => setColumnDialog({ column: null })}>
                        <Plus className={'mr-1.5 h-4 w-4'} />
                        {t('column.create')}
                    </Button>
                )}
            </div>
            <FlashMessageRender byKey={FLASH} className={'mb-4'} />

            {!board || !context ? (
                <Spinner centered size={'large'} />
            ) : (
                <BoardContext.Provider value={context}>
                    {board.columns.length === 0 ? (
                        <div
                            className={
                                'hydra-float flex flex-col items-center gap-3 rounded-xl border border-dashed border-gray-600 bg-gray-800 px-6 py-12 text-center'
                            }
                        >
                            <Columns3 className={'h-10 w-10 text-gray-500'} />
                            <p className={'text-sm text-gray-300'}>{columns ? t('empty_manage') : t('empty')}</p>
                            {columns && (
                                <Button onClick={() => setColumnDialog({ column: null })}>
                                    <Plus className={'mr-1.5 h-4 w-4'} />
                                    {t('column.create_first')}
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div
                            onDragOver={(event) => {
                                if (drag) event.preventDefault();
                            }}
                            onDrop={onDrop}
                            className={'-mx-1 flex items-start gap-3 overflow-x-auto px-1 pb-4'}
                        >
                            {board.columns.map((column, index) => (
                                <React.Fragment key={column.id}>
                                    {otherColumns &&
                                        columnTarget !== null &&
                                        column.id !== drag!.id &&
                                        otherColumns.indexOf(column) === columnTarget && (
                                            <div className={'w-1 shrink-0 self-stretch rounded-full bg-primary-400'} />
                                        )}
                                    <BoardColumnView
                                        column={column}
                                        draggedCard={drag?.kind === 'card' ? drag.id : null}
                                        draggedColumn={drag?.kind === 'column' && drag.id === column.id}
                                        cardTarget={cardTarget}
                                        onEdit={() => setColumnDialog({ column })}
                                        onCardDragStart={(event, id) => startDrag(event, { kind: 'card', id })}
                                        onColumnDragStart={(event) =>
                                            startDrag(event, { kind: 'column', id: column.id })
                                        }
                                        onDragEnd={endDrag}
                                        onCardOver={setCardTarget}
                                        onColumnOver={(event) => onColumnOver(event, index)}
                                    />
                                </React.Fragment>
                            ))}
                            {otherColumns && columnTarget !== null && columnTarget >= otherColumns.length && (
                                <div className={'w-1 shrink-0 self-stretch rounded-full bg-primary-400'} />
                            )}
                        </div>
                    )}

                    <CardDialog
                        card={card}
                        onClose={() => setCard(null)}
                        onManageTags={() => {
                            setReturnTo(card);
                            setCard(null);
                            setTagsOpen(true);
                        }}
                    />
                    <ColumnDialog
                        open={columnDialog !== null}
                        column={columnDialog?.column || null}
                        onClose={() => setColumnDialog(null)}
                    />
                    <TagsDialog
                        open={tagsOpen}
                        onClose={() => {
                            setTagsOpen(false);
                            if (returnTo !== null) {
                                clearFlashes(DIALOG_FLASH);
                                setCard(returnTo);
                                setReturnTo(null);
                            }
                        }}
                    />
                </BoardContext.Provider>
            )}
        </ServerContentBlock>
    );
};
