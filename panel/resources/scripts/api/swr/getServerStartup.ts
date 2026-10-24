import useSWR, { ConfigInterface } from 'swr';
import http, { FractalResponseList } from '@/api/http';
import { rawDataToServerEggVariable } from '@/api/transformers';
import { ServerEggVariable } from '@/api/server/types';

interface Response {
    invocation: string;
    variables: ServerEggVariable[];
    dockerImages: Record<string, string>;
    // Hydra: console messages that mark the server as running, null for those of the egg.
    startupDone?: string[] | null;
    // Hydra: raw command with {{VARIABLES}}, the one of the egg and whether it may be changed.
    rawStartupCommand?: string;
    eggStartupCommand?: string;
    startupEditable?: boolean;
    eggStartupDone?: string[];
}

export default (uuid: string, initialData?: Response | null, config?: ConfigInterface<Response>) =>
    useSWR(
        [uuid, '/startup'],
        async (): Promise<Response> => {
            const { data } = await http.get(`/api/client/servers/${uuid}/startup`);

            const variables = ((data as FractalResponseList).data || []).map(rawDataToServerEggVariable);

            return {
                variables,
                invocation: data.meta.startup_command,
                dockerImages: data.meta.docker_images || {},
                startupDone: data.meta.startup_done || null,
                rawStartupCommand: data.meta.raw_startup_command || '',
                eggStartupCommand: data.meta.egg_startup_command || '',
                startupEditable: data.meta.startup_editable || false,
                eggStartupDone: data.meta.egg_startup_done || [],
            };
        },
        { initialData: initialData || undefined, errorRetryCount: 3, ...(config || {}) }
    );
