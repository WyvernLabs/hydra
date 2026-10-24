import React, { useState } from 'react';
import { Schedule, Task } from '@/api/server/schedules/getServerSchedules';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowCircleDown,
    faArrowDown,
    faArrowUp,
    faBroom,
    faClock,
    faCode,
    faCodeBranch,
    faFileArchive,
    faGlobe,
    faHourglassHalf,
    faPencilAlt,
    faStopCircle,
    faToggleOn,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import deleteScheduleTask from '@/api/server/schedules/deleteScheduleTask';
import createOrUpdateScheduleTask from '@/api/server/schedules/createOrUpdateScheduleTask';
import getServerSchedule from '@/api/server/schedules/getServerSchedule';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import TaskDetailsModal from '@/components/server/schedules/TaskDetailsModal';
import Can from '@/components/elements/Can';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import tw from 'twin.macro';
import ConfirmationModal from '@/components/elements/ConfirmationModal';
import Icon from '@/components/elements/Icon';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { CONTROL_ACTIONS, describeTask, TaskAction } from '@/components/server/schedules/taskActions';

interface Props {
    schedule: Schedule;
    task: Task;
    // Hydra: nesting inside "if" blocks, and whether the task can move up or down.
    depth?: number;
    first?: boolean;
    last?: boolean;
    // Hydra: the head of an "if" block: deleting removes these tasks too (the
    // whole block), and it does not move on its own.
    blockIds?: number[];
}

const icons: Record<TaskAction, any> = {
    command: faCode,
    power: faToggleOn,
    backup: faFileArchive,
    wait_state: faHourglassHalf,
    delete_files: faBroom,
    webhook: faGlobe,
    if: faCodeBranch,
    else: faCodeBranch,
    endif: faCodeBranch,
    stop: faStopCircle,
};

export default ({ schedule, task, depth = 0, first, last, blockIds }: Props) => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { t } = useTranslation('client/server');
    const { clearFlashes, addError } = useFlash();
    const [visible, setVisible] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);

    const onConfirmDeletion = () => {
        setIsLoading(true);
        clearFlashes('schedules');
        const ids = blockIds || [task.id];
        ids.reduce<Promise<unknown>>(
            (chain, id) => chain.then(() => deleteScheduleTask(uuid, schedule.id, id)),
            Promise.resolve()
        )
            .then(() => getServerSchedule(uuid, schedule.id))
            .then((updated) => appendSchedule(updated))
            .catch((error) => {
                console.error(error);
                setIsLoading(false);
                addError({ message: httpErrorToHuman(error), key: 'schedules' });
            });
    };

    // Hydra: moves the task one place up or down; the other tasks shift, so the
    // whole schedule is loaded again.
    const move = (by: number) => {
        setIsLoading(true);
        clearFlashes('schedules');
        createOrUpdateScheduleTask(uuid, schedule.id, task.id, {
            action: task.action,
            payload: task.payload,
            timeOffset: task.timeOffset,
            continueOnFailure: task.continueOnFailure,
            sequenceId: task.sequenceId + by,
        })
            .then(() => getServerSchedule(uuid, schedule.id))
            .then((updated) => appendSchedule(updated))
            .catch((error) => addError({ message: httpErrorToHuman(error), key: 'schedules' }))
            .then(() => setIsLoading(false));
    };

    const action = task.action as TaskAction;
    const control = CONTROL_ACTIONS.includes(action);
    const description = describeTask(t, task);
    const title = t(`schedules.tasks.titles.${action}`, { defaultValue: t('schedules.tasks.unknown') });

    return (
        <div
            css={tw`sm:flex items-center p-3 sm:px-6 border-b border-neutral-800`}
            className={classNames(control ? 'bg-neutral-800/60 sm:py-3' : 'sm:py-5')}
            style={{ paddingLeft: depth > 0 ? `${1.5 + depth * 1.75}rem` : undefined }}
        >
            <SpinnerOverlay visible={isLoading} fixed size={'large'} />
            <TaskDetailsModal
                schedule={schedule}
                task={task}
                visible={isEditing}
                onModalDismissed={() => setIsEditing(false)}
            />
            <ConfirmationModal
                title={t('schedules.tasks.delete_title')}
                buttonText={t('schedules.tasks.delete_button')}
                onConfirmed={onConfirmDeletion}
                visible={visible}
                onModalDismissed={() => setVisible(false)}
            >
                {blockIds ? t('schedules.tasks.delete_block_text') : t('schedules.tasks.delete_text')}
            </ConfirmationModal>
            <FontAwesomeIcon
                icon={icons[action] || faCode}
                css={tw`text-lg hidden md:block`}
                className={control ? 'text-accent-400' : 'text-white'}
                fixedWidth
            />
            <div css={tw`flex-none sm:flex-1 w-full sm:w-auto overflow-x-auto`}>
                <p
                    css={tw`md:ml-6 uppercase text-sm`}
                    className={control ? 'font-semibold text-accent-300' : 'text-neutral-200'}
                >
                    {title}
                    {control && description && (
                        <span className={'ml-2 normal-case font-normal text-gray-200'}>{description}</span>
                    )}
                </p>
                {!control && description && (
                    <div css={tw`md:ml-6 mt-2`}>
                        {task.action === 'backup' && (
                            <p css={tw`text-xs uppercase text-neutral-400 mb-1`}>{t('schedules.tasks.ignoring')}</p>
                        )}
                        <div
                            css={tw`font-mono bg-neutral-800 rounded py-1 px-2 text-sm w-auto inline-block whitespace-pre-wrap break-all`}
                        >
                            {description}
                        </div>
                    </div>
                )}
            </div>
            <div css={tw`mt-3 sm:mt-0 flex items-center w-full sm:w-auto`}>
                {task.continueOnFailure && (
                    <div css={tw`mr-6`}>
                        <div css={tw`flex items-center px-2 py-1 bg-yellow-500 text-yellow-800 text-sm rounded-full`}>
                            <Icon icon={faArrowCircleDown} css={tw`w-3 h-3 mr-2`} />
                            {t('schedules.tasks.continues_on_failure')}
                        </div>
                    </div>
                )}
                {task.timeOffset > 0 && (
                    <div css={tw`mr-6`}>
                        <div css={tw`flex items-center px-2 py-1 bg-neutral-600 text-sm rounded-full`}>
                            <Icon icon={faClock} css={tw`w-3 h-3 mr-2`} />
                            {t('schedules.tasks.seconds_later', { seconds: task.timeOffset })}
                        </div>
                    </div>
                )}
                <Can action={'schedule.update'}>
                    <div className={'ml-auto flex items-center sm:ml-0'}>
                        {!blockIds && (
                            <>
                                <button
                                    type={'button'}
                                    aria-label={t('schedules.tasks.move_up')}
                                    title={t('schedules.tasks.move_up')}
                                    disabled={first}
                                    className={
                                        'p-2 text-sm text-neutral-500 transition-colors hover:text-neutral-100 disabled:opacity-30'
                                    }
                                    onClick={() => move(-1)}
                                >
                                    <FontAwesomeIcon icon={faArrowUp} />
                                </button>
                                <button
                                    type={'button'}
                                    aria-label={t('schedules.tasks.move_down')}
                                    title={t('schedules.tasks.move_down')}
                                    disabled={last}
                                    className={
                                        'mr-2 p-2 text-sm text-neutral-500 transition-colors hover:text-neutral-100 disabled:opacity-30'
                                    }
                                    onClick={() => move(1)}
                                >
                                    <FontAwesomeIcon icon={faArrowDown} />
                                </button>
                            </>
                        )}
                        <button
                            type={'button'}
                            aria-label={t('schedules.tasks.edit_label')}
                            css={tw`block text-sm p-2 text-neutral-500 hover:text-neutral-100 transition-colors duration-150 mr-2`}
                            onClick={() => setIsEditing(true)}
                        >
                            <FontAwesomeIcon icon={faPencilAlt} />
                        </button>
                        <button
                            type={'button'}
                            aria-label={t('schedules.tasks.delete_label')}
                            css={tw`block text-sm p-2 text-neutral-500 hover:text-red-600 transition-colors duration-150`}
                            onClick={() => setVisible(true)}
                        >
                            <FontAwesomeIcon icon={faTrashAlt} />
                        </button>
                    </div>
                </Can>
            </div>
        </div>
    );
};
