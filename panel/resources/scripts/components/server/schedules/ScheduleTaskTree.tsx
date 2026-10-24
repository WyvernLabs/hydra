import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus } from 'lucide-react';
import { Schedule, Task } from '@/api/server/schedules/getServerSchedules';
import Can from '@/components/elements/Can';
import ScheduleTaskRow from '@/components/server/schedules/ScheduleTaskRow';
import TaskDetailsModal from '@/components/server/schedules/TaskDetailsModal';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import createOrUpdateScheduleTask from '@/api/server/schedules/createOrUpdateScheduleTask';
import getServerSchedule from '@/api/server/schedules/getServerSchedule';

// Hydra: the tasks of a schedule as blocks: an "if" is a box with its "then"
// and "otherwise" branches inside, each with a button for a new task there.

type Node =
    | { kind: 'task'; task: Task }
    | { kind: 'if'; task: Task; then: Node[]; elseTask: Task | null; otherwise: Node[]; endTask: Task | null };

/**
 * Turns the ordered list of tasks into blocks. Tasks after an unfinished
 * block belong to it, like when the schedule runs.
 */
export const buildTaskTree = (tasks: Task[]): Node[] => {
    let index = 0;

    const read = (inside: boolean): Node[] => {
        const nodes: Node[] = [];
        while (index < tasks.length) {
            const task = tasks[index];
            if (inside && (task.action === 'else' || task.action === 'endif')) {
                return nodes;
            }
            index++;
            if (task.action !== 'if') {
                // A stray "else" or "end" outside of a block is shown as a plain task.
                nodes.push({ kind: 'task', task });
                continue;
            }

            const block: Node = { kind: 'if', task, then: read(true), elseTask: null, otherwise: [], endTask: null };
            if (tasks[index]?.action === 'else') {
                block.elseTask = tasks[index++];
                block.otherwise = read(true);
            }
            if (tasks[index]?.action === 'endif') {
                block.endTask = tasks[index++];
            }
            nodes.push(block);
        }
        return nodes;
    };

    return read(false);
};

// Every task of a block, for deleting it as a whole.
const blockIds = (node: Node): number[] =>
    node.kind === 'task'
        ? [node.task.id]
        : [
              node.task.id,
              ...node.then.flatMap(blockIds),
              ...(node.elseTask ? [node.elseTask.id] : []),
              ...node.otherwise.flatMap(blockIds),
              ...(node.endTask ? [node.endTask.id] : []),
          ];

const NewFunctionButton = ({ schedule, sequenceId }: { schedule: Schedule; sequenceId?: number }) => {
    const { t } = useTranslation('client/server');
    const [open, setOpen] = useState(false);

    return (
        <Can action={'schedule.update'}>
            <TaskDetailsModal
                schedule={schedule}
                sequenceId={sequenceId}
                visible={open}
                onModalDismissed={() => setOpen(false)}
            />
            <button
                type={'button'}
                onClick={() => setOpen(true)}
                className={
                    'm-3 flex w-[calc(100%-1.5rem)] items-center justify-center gap-1.5 rounded border border-dashed border-gray-600 py-2 text-sm text-gray-400 transition-colors hover:border-primary-500 hover:text-gray-100'
                }
            >
                <Plus className={'h-4 w-4'} />
                {t('schedules.tasks.new_function')}
            </button>
        </Can>
    );
};

/**
 * Adds the "otherwise" branch to a block that has none, e.g. one made before
 * blocks got both branches; a missing end of the block is added as well.
 */
const AddOtherwiseButton = ({ schedule, endTask }: { schedule: Schedule; endTask: Task | null }) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);
    const { clearFlashes, addError } = useFlash();
    const [busy, setBusy] = useState(false);

    const add = () => {
        clearFlashes('schedules');
        setBusy(true);
        const control = { payload: '', timeOffset: 0, continueOnFailure: false };
        createOrUpdateScheduleTask(uuid, schedule.id, undefined, {
            ...control,
            action: 'else',
            sequenceId: endTask?.sequenceId,
        })
            .then(() =>
                endTask
                    ? undefined
                    : createOrUpdateScheduleTask(uuid, schedule.id, undefined, { ...control, action: 'endif' })
            )
            .then(() => getServerSchedule(uuid, schedule.id))
            .then((updated) => appendSchedule(updated))
            .catch((error) => addError({ key: 'schedules', message: httpErrorToHuman(error) }))
            .then(() => setBusy(false));
    };

    return (
        <Can action={'schedule.update'}>
            <button
                type={'button'}
                onClick={add}
                disabled={busy}
                className={
                    'mx-3 mb-3 flex w-[calc(100%-1.5rem)] items-center justify-center gap-1.5 rounded border border-dashed border-orange-500/50 py-2 text-sm text-orange-300 transition-colors hover:border-orange-400 hover:text-orange-200 disabled:opacity-50'
                }
            >
                <Plus className={'h-4 w-4'} />
                {t('schedules.tasks.add_otherwise')}
            </button>
        </Can>
    );
};

const Branch = ({
    schedule,
    label,
    tone,
    nodes,
    sequenceId,
    ids,
}: {
    schedule: Schedule;
    label: string;
    tone: 'then' | 'otherwise';
    nodes: Node[];
    sequenceId?: number;
    ids: number[];
}) => {
    const { t } = useTranslation('client/server');

    return (
        <div className={'mx-3 mb-3 rounded border border-gray-700 bg-gray-900/40'}>
            <p
                className={`border-b border-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-wide ${
                    tone === 'then' ? 'text-green-400' : 'text-orange-400'
                }`}
            >
                {label}
            </p>
            {nodes.length === 0 && (
                <p className={'px-4 pt-3 text-xs text-gray-500'}>{t('schedules.tasks.branch_empty')}</p>
            )}
            {/* Branches and blocks contain each other. */}
            {/* eslint-disable-next-line @typescript-eslint/no-use-before-define */}
            <TaskNodes schedule={schedule} nodes={nodes} ids={ids} />
            <NewFunctionButton schedule={schedule} sequenceId={sequenceId} />
        </div>
    );
};

const TaskNodes = ({ schedule, nodes, ids }: { schedule: Schedule; nodes: Node[]; ids: number[] }) => {
    const { t } = useTranslation('client/server');

    return (
        <>
            {nodes.map((node) => {
                if (node.kind === 'task') {
                    const position = ids.indexOf(node.task.id);
                    return (
                        <ScheduleTaskRow
                            key={node.task.id}
                            schedule={schedule}
                            task={node.task}
                            first={position === 0}
                            last={position === ids.length - 1}
                        />
                    );
                }

                // A new task of a branch goes before the "otherwise" or the end of the block.
                const thenAt = (node.elseTask || node.endTask)?.sequenceId;
                return (
                    <div key={node.task.id} className={'my-2 border-l-4 border-primary-500 bg-neutral-800/40'}>
                        <ScheduleTaskRow schedule={schedule} task={node.task} blockIds={blockIds(node)} />
                        <Branch
                            schedule={schedule}
                            label={t('schedules.tasks.branch_then')}
                            tone={'then'}
                            nodes={node.then}
                            sequenceId={thenAt}
                            ids={ids}
                        />
                        {node.elseTask ? (
                            <Branch
                                schedule={schedule}
                                label={t('schedules.tasks.branch_otherwise')}
                                tone={'otherwise'}
                                nodes={node.otherwise}
                                sequenceId={node.endTask?.sequenceId}
                                ids={ids}
                            />
                        ) : (
                            <AddOtherwiseButton schedule={schedule} endTask={node.endTask} />
                        )}
                    </div>
                );
            })}
        </>
    );
};

export default ({ schedule, tasks }: { schedule: Schedule; tasks: Task[] }) => {
    const nodes = buildTaskTree(tasks);

    return <TaskNodes schedule={schedule} nodes={nodes} ids={tasks.map((task) => task.id)} />;
};
