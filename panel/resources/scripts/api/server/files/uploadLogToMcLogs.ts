import http from '@/api/http';

// Hydra: uploads the log file of the server to mclo.gs (addon) and returns the link.
export default (uuid: string): Promise<string> =>
    http.post(`/api/client/servers/${uuid}/files/mclogs`).then(({ data }) => data.url);
