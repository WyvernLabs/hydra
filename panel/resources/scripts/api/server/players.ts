import http from '@/api/http';

// Hydra: players online on a Minecraft Java server over time (addon "player_stats").

export type PlayerRange = 'day' | 'week' | 'month';

export interface PlayerHistory {
    range: PlayerRange;
    // Seconds every point covers.
    width: number;
    // Most players seen in the time of each point, null while the server did not answer.
    points: { time: Date; online: number | null }[];
    current: { online: number; max: number } | null;
    peak: number | null;
    average: number | null;
}

export const getPlayerHistory = (uuid: string, range: PlayerRange): Promise<PlayerHistory> =>
    http.get(`/api/client/servers/${uuid}/players`, { params: { range } }).then(({ data }) => ({
        range: data.range,
        width: data.width,
        points: data.points.map((point: { t: string; online: number | null }) => ({
            time: new Date(point.t),
            online: point.online,
        })),
        current: data.current,
        peak: data.peak,
        average: data.average,
    }));
