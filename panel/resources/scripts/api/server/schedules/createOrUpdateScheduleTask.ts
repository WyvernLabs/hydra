import { rawDataToServerTask, Task } from '@/api/server/schedules/getServerSchedules';
import http from '@/api/http';

interface Data {
    action: string;
    payload: string;
    timeOffset: string | number;
    continueOnFailure: boolean;
    // Hydra: moves the task to this position.
    sequenceId?: number;
}

export default async (uuid: string, schedule: number, task: number | undefined, data: Data): Promise<Task> => {
    const { data: response } = await http.post(
        `/api/client/servers/${uuid}/schedules/${schedule}/tasks${task ? `/${task}` : ''}`,
        {
            action: data.action,
            payload: data.payload,
            continue_on_failure: data.continueOnFailure,
            time_offset: data.timeOffset,
            sequence_id: data.sequenceId,
        }
    );

    return rawDataToServerTask(response.attributes);
};
