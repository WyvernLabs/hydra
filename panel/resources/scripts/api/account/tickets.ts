import http from '@/api/http';

// Hydra: support tickets of the logged in user (tickets addon).

export type TicketStatus = 'open' | 'answered' | 'closed';
export type TicketPriority = 'low' | 'normal' | 'high' | 'urgent';

export interface Ticket {
    id: number;
    subject: string;
    // Name of the category in the language of the ticket.
    category: string | null;
    language: string | null;
    languageName: string | null;
    priority: TicketPriority;
    status: TicketStatus;
    server: { identifier: string; name: string } | null;
    lastReplyAt: Date | null;
    createdAt: Date;
}

export interface TicketMessage {
    id: number;
    // Rendered by the panel from Markdown, without raw HTML.
    html: string;
    staff: boolean;
    mine: boolean;
    author: { username: string; avatarUrl: string | null } | null;
    createdAt: Date;
}

export interface TicketWithMessages extends Ticket {
    messages: TicketMessage[];
}

export interface TicketPage {
    items: Ticket[];
    currentPage: number;
    lastPage: number;
}

const toTicket = (data: any): Ticket => ({
    id: data.id,
    subject: data.subject,
    category: data.category,
    language: data.language,
    languageName: data.language_name,
    priority: data.priority,
    status: data.status,
    server: data.server,
    lastReplyAt: data.last_reply_at ? new Date(data.last_reply_at) : null,
    createdAt: new Date(data.created_at),
});

export const getTickets = (status: 'open' | 'closed' | 'all', page = 1): Promise<TicketPage> =>
    http.get('/api/client/account/tickets', { params: { status, page } }).then(({ data }) => ({
        items: data.data.map(toTicket),
        currentPage: data.meta.current_page,
        lastPage: data.meta.last_page,
    }));

export const getTicket = (id: number): Promise<TicketWithMessages> =>
    http.get(`/api/client/account/tickets/${id}`).then(({ data }) => ({
        ...toTicket(data),
        messages: data.messages.map((message: any) => ({
            id: message.id,
            html: message.html,
            staff: message.staff,
            mine: message.mine,
            author: message.author ? { username: message.author.username, avatarUrl: message.author.avatar_url } : null,
            createdAt: new Date(message.created_at),
        })),
    }));

export interface NewTicket {
    subject: string;
    body: string;
    // ID of the category.
    category: string | null;
    language: string;
    priority: TicketPriority;
    server: string | null;
}

export const createTicket = (ticket: NewTicket): Promise<Ticket> =>
    http.post('/api/client/account/tickets', ticket).then(({ data }) => toTicket(data));

export const replyToTicket = (id: number, body: string): Promise<void> =>
    http.post(`/api/client/account/tickets/${id}/messages`, { body }).then(() => undefined);

export const closeTicket = (id: number): Promise<void> =>
    http.post(`/api/client/account/tickets/${id}/close`).then(() => undefined);

export const uploadTicketImage = (file: File): Promise<{ url: string; name: string }> => {
    const form = new FormData();
    form.append('image', file);

    return http.post('/api/client/account/tickets/attachments', form).then(({ data }) => data);
};

export const previewTicketMarkdown = (body: string): Promise<string> =>
    http.post('/api/client/account/tickets/preview', { body }).then(({ data }) => data.html);
