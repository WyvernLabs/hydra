import http from '@/api/http';

// Hydra: downloads from a link into a folder of the server (addon
// "link_download"). The node does the download in the background.

export interface RunningPull {
    identifier: string;
    // 0 to 1.
    progress: number;
}

export interface FinishedPull {
    identifier: string;
    path: string;
    success: boolean;
    // A code like "internal", "no_length" or "status:404 Not Found", or a message.
    error: string | null;
}

export interface Pulls {
    downloads: RunningPull[];
    finished: FinishedPull[];
}

export const startPull = (uuid: string, url: string, directory: string, filename?: string): Promise<void> =>
    http
        .post(`/api/client/servers/${uuid}/files/pull`, {
            url,
            directory,
            filename: filename || null,
            // Without a name the node takes it from the server's answer, else from the link.
            use_header: !filename,
            foreground: false,
        })
        .then(() => undefined);

export const getPulls = (uuid: string): Promise<Pulls> =>
    http.get(`/api/client/servers/${uuid}/files/pull`).then(({ data }) => ({
        downloads: data.downloads || [],
        finished: data.finished || [],
    }));

export const cancelPull = (uuid: string, identifier: string): Promise<void> =>
    http.delete(`/api/client/servers/${uuid}/files/pull/${identifier}`).then(() => undefined);
