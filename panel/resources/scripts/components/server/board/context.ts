import { createContext, useContext } from 'react';
import { Board, BoardCard, BoardColumn } from '@/api/server/board';

// Hydra: shared state of the board page (addon "board").

export const FLASH = 'server:board';
export const DIALOG_FLASH = 'server:board:dialog';

// Colors offered for columns and tags; any other color can be picked as well.
export const COLORS = [
    '#ef4444',
    '#f97316',
    '#f59e0b',
    '#eab308',
    '#84cc16',
    '#22c55e',
    '#14b8a6',
    '#06b6d4',
    '#3b82f6',
    '#6366f1',
    '#a855f7',
    '#ec4899',
    '#64748b',
];

export interface BoardPermissions {
    columns: boolean;
    tags: boolean;
    create: boolean;
    update: boolean;
    delete: boolean;
    claim: boolean;
}

export interface BoardContextType {
    uuid: string;
    board: Board;
    can: BoardPermissions;
    // Sends a change; the answer replaces the board. Resolves false on errors,
    // which are shown as flash message under the given key.
    run: (request: (uuid: string) => Promise<Board>, flash?: string) => Promise<boolean>;
    // Shows a board right away, e.g. after a drag and drop before the answer.
    preview: (board: Board) => void;
    openCard: (card: number) => void;
}

export const BoardContext = createContext<BoardContextType | null>(null);

export const useBoard = (): BoardContextType => useContext(BoardContext)!;

export const findCard = (board: Board, id: number): { card: BoardCard; column: BoardColumn } | null => {
    for (const column of board.columns) {
        const card = column.cards.find((c) => c.id === id);
        if (card) return { card, column };
    }

    return null;
};

// Moves a card locally; "index" counts the cards of the target column without the moved card.
export const moveCardLocally = (board: Board, id: number, columnId: number, index: number): Board => {
    const found = findCard(board, id);
    if (!found) return board;

    const columns = board.columns.map((column) => ({
        ...column,
        cards: column.cards.filter((card) => card.id !== id),
    }));
    const target = columns.find((column) => column.id === columnId);
    if (!target) return board;
    target.cards.splice(Math.min(index, target.cards.length), 0, found.card);

    return { ...board, columns };
};

// Text color that stays readable on the given background color.
export const contrastText = (hex: string): string => {
    const value = parseInt(hex.slice(1), 16);
    const r = (value >> 16) & 255;
    const g = (value >> 8) & 255;
    const b = value & 255;

    return (r * 299 + g * 587 + b * 114) / 1000 > 150 ? '#111827' : '#ffffff';
};
