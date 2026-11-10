import useSWR, { ConfigInterface, responseInterface } from 'swr';
import { ActivityLog, Transformers } from '@definitions/user';
import { AxiosError } from 'axios';
import http, { PaginatedResult, QueryBuilderParams, withQueryBuilderParams } from '@/api/http';
import { toPaginatedSet } from '@definitions/helpers';
import useFilteredObject from '@/plugins/useFilteredObject';
import { useServerSWRKey } from '@/plugins/useSWRKey';
import { ServerContext } from '@/state/server';

// Hydra: "actor" (user ID), "action" (exact event) and "search" (free text) filter the log.
export type ActivityLogFilters = QueryBuilderParams<'ip' | 'event' | 'actor' | 'action' | 'search', 'timestamp'>;

export interface ActivityFilterOptions {
    actors: { id: number; username: string }[];
    events: { event: string; label: string }[];
}

// Hydra: users and actions that appear in the log of the server.
export const getActivityFilterOptions = (uuid: string): Promise<ActivityFilterOptions> =>
    http.get(`/api/client/servers/${uuid}/activity/filters`).then(({ data }) => ({
        actors: data.actors || [],
        events: data.events || [],
    }));

const useActivityLogs = (
    filters?: ActivityLogFilters,
    config?: ConfigInterface<PaginatedResult<ActivityLog>, AxiosError>
): responseInterface<PaginatedResult<ActivityLog>, AxiosError> => {
    const uuid = ServerContext.useStoreState((state) => state.server.data?.uuid);
    const key = useServerSWRKey(['activity', useFilteredObject(filters || {})]);

    return useSWR<PaginatedResult<ActivityLog>>(
        key,
        async () => {
            const { data } = await http.get(`/api/client/servers/${uuid}/activity`, {
                params: {
                    ...withQueryBuilderParams(filters),
                    include: ['actor'],
                },
            });

            return toPaginatedSet(data, Transformers.toActivityLog);
        },
        { revalidateOnMount: false, ...(config || {}) }
    );
};

export { useActivityLogs };
