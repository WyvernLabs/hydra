import React from 'react';
import { useTranslation } from 'react-i18next';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { describeCron } from '@/lib/scheduleTime';

// Hydra: when a schedule runs, as a sentence.
export const useTriggerText = (schedule: Schedule): string => {
    const { t } = useTranslation('client/server');
    const { trigger } = schedule;

    if (trigger.type === 'state') {
        return t('schedules.trigger.describe_state', { state: t(`schedules.states.${trigger.value}`) });
    }
    if (trigger.type === 'console') {
        return t(trigger.regex ? 'schedules.trigger.describe_regex' : 'schedules.trigger.describe_console', {
            value: trigger.value,
        });
    }

    return describeCron(t, schedule.cron);
};

export default ({ schedule, className }: { schedule: Schedule; className?: string }) => (
    <p className={className}>{useTriggerText(schedule)}</p>
);
