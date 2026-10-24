import http from '@/api/http';

// Hydra: link that shows an image, video or music file inline. It can be used
// more than once for half an hour, so players can load the file in parts.
export default async (uuid: string, file: string): Promise<string> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/files/preview`, { params: { file } });

    return data.attributes.url;
};
