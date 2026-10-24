import React, { useCallback, useEffect, useState } from 'react';
import { useHistory, useParams } from 'react-router-dom';
import getServerSchedule from '@/api/server/schedules/getServerSchedule';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import EditScheduleModal from '@/components/server/schedules/EditScheduleModal';
import NewTaskButton from '@/components/server/schedules/NewTaskButton';
import DeleteScheduleButton from '@/components/server/schedules/DeleteScheduleButton';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import ScheduleTaskRow from '@/components/server/schedules/ScheduleTaskRow';
import isEqual from 'react-fast-compare';
import { formatDate } from '@/lib/date';
import ScheduleTriggerText from '@/components/server/schedules/ScheduleTriggerText';
import { taskDepths } from '@/components/server/schedules/taskActions';
import RunScheduleButton from '@/components/server/schedules/RunScheduleButton';
import { useTranslation } from 'react-i18next';

interface Params {
    id: string;
}

const ActivePill = ({ active }: { active: boolean }) => {
    const { t } = useTranslation('client/server');

    return (
        <span
            css={[
                tw`rounded-full px-2 py-px text-xs ml-4 uppercase`,
                active ? tw`bg-green-600 text-green-100` : tw`bg-red-600 text-red-100`,
            ]}
        >
            {active ? t('schedules.active') : t('schedules.inactive')}
        </span>
    );
};

export default () => {
    const history = useHistory();
    const { t } = useTranslation('client/server');
    const { id: scheduleId } = useParams<Params>();

    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);

    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const [isLoading, setIsLoading] = useState(true);
    const [showEditModal, setShowEditModal] = useState(false);

    const schedule = ServerContext.useStoreState(
        (st) => st.schedules.data.find((s) => s.id === Number(scheduleId)),
        isEqual
    );
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);

    useEffect(() => {
        if (schedule?.id === Number(scheduleId)) {
            setIsLoading(false);
            return;
        }

        clearFlashes('schedules');
        getServerSchedule(uuid, Number(scheduleId))
            .then((schedule) => appendSchedule(schedule))
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ error, key: 'schedules' });
            })
            .then(() => setIsLoading(false));
    }, [scheduleId]);

    const toggleEditModal = useCallback(() => {
        setShowEditModal((s) => !s);
    }, []);

    // Hydra: tasks in order, indented inside the blocks of conditions.
    const sorted = (schedule?.tasks || []).slice().sort((a, b) => a.sequenceId - b.sequenceId);
    const depths = taskDepths(sorted);

    return (
        <PageContentBlock title={t('schedules.title')}>
            <FlashMessageRender byKey={'schedules'} css={tw`mb-4`} />
            {!schedule || isLoading ? (
                <Spinner size={'large'} centered />
            ) : (
                <>
                    <div css={tw`rounded shadow`}>
                        <div
                            css={tw`sm:flex items-center bg-neutral-900 p-3 sm:p-6 border-b-4 border-neutral-600 rounded-t`}
                        >
                            <div css={tw`flex-1`}>
                                <h3 css={tw`flex items-center text-neutral-100 text-2xl`}>
                                    {schedule.name}
                                    {schedule.isProcessing ? (
                                        <span
                                            css={tw`flex items-center rounded-full px-2 py-px text-xs ml-4 uppercase bg-neutral-600 text-white`}
                                        >
                                            <Spinner css={tw`w-3! h-3! mr-2`} />
                                            {t('schedules.processing')}
                                        </span>
                                    ) : (
                                        <ActivePill active={schedule.isActive} />
                                    )}
                                </h3>
                                <p css={tw`mt-1 text-sm text-neutral-200`}>
                                    {t('schedules.last_run', {
                                        date: schedule.lastRunAt
                                            ? formatDate(schedule.lastRunAt)
                                            : t('schedules.not_available'),
                                    })}
                                    <span css={tw`ml-4 pl-4 border-l-4 border-neutral-600 py-px`}>
                                        {t('schedules.next_run', {
                                            date: schedule.nextRunAt
                                                ? formatDate(schedule.nextRunAt)
                                                : t('schedules.not_available'),
                                        })}
                                    </span>
                                </p>
                            </div>
                            <div css={tw`flex sm:block mt-3 sm:mt-0`}>
                                <Can action={'schedule.update'}>
                                    <Button.Text className={'flex-1 mr-4'} onClick={toggleEditModal}>
                                        {t('schedules.edit')}
                                    </Button.Text>
                                    <NewTaskButton schedule={schedule} />
                                </Can>
                            </div>
                        </div>
                        {/* Hydra: when the schedule runs, as a sentence. */}
                        <div className={'my-4 rounded bg-neutral-700 p-4'}>
                            <p className={'text-2xs uppercase text-neutral-400'}>{t('schedules.trigger.runs')}</p>
                            <ScheduleTriggerText schedule={schedule} className={'mt-1 text-gray-100'} />
                        </div>
                        <div css={tw`bg-neutral-700 rounded-b`}>
                            {sorted.map((task, index) => (
                                <ScheduleTaskRow
                                    key={`${schedule.id}_${task.id}`}
                                    task={task}
                                    schedule={schedule}
                                    depth={depths[task.id]}
                                    first={index === 0}
                                    last={index === sorted.length - 1}
                                />
                            ))}
                        </div>
                    </div>
                    <EditScheduleModal visible={showEditModal} schedule={schedule} onModalDismissed={toggleEditModal} />
                    <div css={tw`mt-6 flex sm:justify-end`}>
                        <Can action={'schedule.delete'}>
                            <DeleteScheduleButton
                                scheduleId={schedule.id}
                                onDeleted={() => history.push(`/server/${id}/schedules`)}
                            />
                        </Can>
                        {schedule.tasks.length > 0 && (
                            <Can action={'schedule.update'}>
                                <RunScheduleButton schedule={schedule} />
                            </Can>
                        )}
                    </div>
                </>
            )}
        </PageContentBlock>
    );
};
