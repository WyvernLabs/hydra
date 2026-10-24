import React from 'react';
import { Schedule } from '@/api/server/schedules/getServerSchedules';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCalendarAlt } from '@fortawesome/free-solid-svg-icons';
import { formatDate } from '@/lib/date';
import tw from 'twin.macro';
import ScheduleTriggerText from '@/components/server/schedules/ScheduleTriggerText';
import { useTranslation } from 'react-i18next';

export default ({ schedule }: { schedule: Schedule }) => {
    const { t } = useTranslation('client/server');

    return (
        <>
            <div css={tw`hidden md:block`}>
                <FontAwesomeIcon icon={faCalendarAlt} fixedWidth />
            </div>
            <div css={tw`flex-1 md:ml-4`}>
                <p>{schedule.name}</p>
                <p css={tw`text-xs text-neutral-400`}>
                    {t('schedules.last_run', {
                        date: schedule.lastRunAt ? formatDate(schedule.lastRunAt) : t('schedules.never'),
                    })}
                </p>
            </div>
            <div>
                <p
                    css={[
                        tw`py-1 px-3 rounded text-xs uppercase text-white sm:hidden`,
                        schedule.isActive ? tw`bg-green-600` : tw`bg-neutral-400`,
                    ]}
                >
                    {schedule.isActive ? t('schedules.active') : t('schedules.inactive')}
                </p>
            </div>
            {/* Hydra: when the schedule runs, as a sentence. */}
            <ScheduleTriggerText
                schedule={schedule}
                className={'mt-4 w-full text-sm text-gray-200 sm:mx-8 sm:mt-0 sm:w-auto sm:max-w-xs sm:truncate'}
            />
            <div>
                <p
                    css={[
                        tw`py-1 px-3 rounded text-xs uppercase text-white hidden sm:block`,
                        schedule.isActive && !schedule.isProcessing ? tw`bg-green-600` : tw`bg-neutral-400`,
                    ]}
                >
                    {schedule.isProcessing
                        ? t('schedules.processing')
                        : schedule.isActive
                        ? t('schedules.active')
                        : t('schedules.inactive')}
                </p>
            </div>
        </>
    );
};
