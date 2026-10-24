import { rawDataToServerSchedule, Schedule } from '@/api/server/schedules/getServerSchedules';
import http from '@/api/http';

type Data = Pick<Schedule, 'cron' | 'name' | 'onlyWhenOnline' | 'isActive'> & {
    id?: number;
    // Hydra: what starts the schedule, time based when left out.
    trigger?: Schedule['trigger'];
};

export default async (uuid: string, schedule: Data): Promise<Schedule> => {
    const { data } = await http.post(`/api/client/servers/${uuid}/schedules${schedule.id ? `/${schedule.id}` : ''}`, {
        is_active: schedule.isActive,
        only_when_online: schedule.onlyWhenOnline,
        name: schedule.name,
        minute: schedule.cron.minute,
        hour: schedule.cron.hour,
        day_of_month: schedule.cron.dayOfMonth,
        month: schedule.cron.month,
        day_of_week: schedule.cron.dayOfWeek,
        trigger: schedule.trigger?.type || 'cron',
        trigger_value: schedule.trigger?.value || null,
        trigger_regex: schedule.trigger?.regex || false,
        trigger_cooldown: schedule.trigger?.cooldown ?? 60,
    });

    return rawDataToServerSchedule(data.attributes);
};
