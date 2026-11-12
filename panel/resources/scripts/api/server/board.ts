import http from '@/api/http';

// Hydra: Kanban board of a server (addon "board"). Every change answers with
// the whole board.

export interface BoardTag {
    id: number;
    name: string;
    color: string;
}

export interface BoardChecklistItem {
    id: number;
    text: string;
    done: boolean;
}

export interface BoardClaimer {
    id: number;
    username: string;
    avatar: string | null;
}

export interface BoardCard {
    id: number;
    name: string;
    description: string | null;
    tags: number[];
    checklist: BoardChecklistItem[];
    claimedBy: BoardClaimer | null;
    claimedAt: Date | null;
    createdAt: Date;
    updatedAt: Date;
}

export interface BoardColumn {
    id: number;
    name: string;
    color: string | null;
    cards: BoardCard[];
}

export interface Board {
    columns: BoardColumn[];
    tags: BoardTag[];
    limits: { columns: number; cards: number; tags: number; items: number };
    me: { id: number; releaseAny: boolean };
}

const transform = (data: any): Board => ({
    columns: (data.columns || []).map((column: any) => ({
        id: column.id,
        name: column.name,
        color: column.color,
        cards: (column.cards || []).map((card: any) => ({
            id: card.id,
            name: card.name,
            description: card.description,
            tags: card.tags || [],
            checklist: card.checklist || [],
            claimedBy: card.claimed_by,
            claimedAt: card.claimed_at ? new Date(card.claimed_at) : null,
            createdAt: new Date(card.created_at),
            updatedAt: new Date(card.updated_at),
        })),
    })),
    tags: data.tags || [],
    limits: data.limits,
    me: { id: data.me.id, releaseAny: data.me.release_any },
});

const url = (uuid: string, path = '') => `/api/client/servers/${uuid}/board${path}`;

export const getBoard = async (uuid: string): Promise<Board> => transform((await http.get(url(uuid))).data);

export interface ColumnValues {
    name?: string;
    color?: string | null;
}

export const createColumn = async (uuid: string, values: { name: string; color: string | null }) =>
    transform((await http.post(url(uuid, '/columns'), values)).data);

export const updateColumn = async (uuid: string, column: number, values: ColumnValues) =>
    transform((await http.patch(url(uuid, `/columns/${column}`), values)).data);

export const deleteColumn = async (uuid: string, column: number) =>
    transform((await http.delete(url(uuid, `/columns/${column}`))).data);

export const orderColumns = async (uuid: string, columns: number[]) =>
    transform((await http.put(url(uuid, '/columns/order'), { columns })).data);

export interface CardValues {
    name?: string;
    description?: string | null;
    tags?: number[];
}

export const createCard = async (uuid: string, column: number, values: CardValues) =>
    transform((await http.post(url(uuid, '/cards'), { column, ...values })).data);

export const updateCard = async (uuid: string, card: number, values: CardValues) =>
    transform((await http.patch(url(uuid, `/cards/${card}`), values)).data);

export const moveCard = async (uuid: string, card: number, column: number, position: number) =>
    transform((await http.put(url(uuid, `/cards/${card}/move`), { column, position })).data);

export const deleteCard = async (uuid: string, card: number) =>
    transform((await http.delete(url(uuid, `/cards/${card}`))).data);

export const claimCard = async (uuid: string, card: number) =>
    transform((await http.post(url(uuid, `/cards/${card}/claim`))).data);

export const releaseCard = async (uuid: string, card: number) =>
    transform((await http.delete(url(uuid, `/cards/${card}/claim`))).data);

export const createChecklistItem = async (uuid: string, card: number, text: string) =>
    transform((await http.post(url(uuid, `/cards/${card}/checklist`), { text })).data);

export const updateChecklistItem = async (
    uuid: string,
    card: number,
    item: number,
    values: { text?: string; done?: boolean }
) => transform((await http.patch(url(uuid, `/cards/${card}/checklist/${item}`), values)).data);

export const deleteChecklistItem = async (uuid: string, card: number, item: number) =>
    transform((await http.delete(url(uuid, `/cards/${card}/checklist/${item}`))).data);

export const createTag = async (uuid: string, values: { name: string; color: string }) =>
    transform((await http.post(url(uuid, '/tags'), values)).data);

export const updateTag = async (uuid: string, tag: number, values: { name?: string; color?: string }) =>
    transform((await http.patch(url(uuid, `/tags/${tag}`), values)).data);

export const deleteTag = async (uuid: string, tag: number) =>
    transform((await http.delete(url(uuid, `/tags/${tag}`))).data);

// Checklist progress of a card: done entries, all entries and percent.
export const progressOf = (card: BoardCard): { done: number; total: number; percent: number } => {
    const total = card.checklist.length;
    const done = card.checklist.filter((item) => item.done).length;

    return { done, total, percent: total === 0 ? 0 : Math.round((done / total) * 100) };
};
