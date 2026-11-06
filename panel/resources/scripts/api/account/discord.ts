import http from '@/api/http';

// Hydra: connecting the account to Discord (addon "discord").

export interface DiscordState {
    link: { discord_id: string; username: string | null; notify_tickets: boolean } | null;
    pending: { discord_id: string; expires_at: number } | null;
}

const BASE = '/api/client/account/discord';

export const getDiscord = (): Promise<DiscordState> => http.get(BASE).then(({ data }) => data);

export const sendDiscordCode = (discordId: string): Promise<DiscordState> =>
    http.post(`${BASE}/code`, { discord_id: discordId }).then(({ data }) => data);

export const confirmDiscordCode = (code: string): Promise<DiscordState> =>
    http.post(`${BASE}/confirm`, { code }).then(({ data }) => data);

export const cancelDiscordCode = (): Promise<DiscordState> => http.delete(`${BASE}/code`).then(({ data }) => data);

export const updateDiscord = (notifyTickets: boolean): Promise<DiscordState> =>
    http.patch(BASE, { notify_tickets: notifyTickets }).then(({ data }) => data);

export const unlinkDiscord = (): Promise<void> => http.delete(BASE).then(() => undefined);
