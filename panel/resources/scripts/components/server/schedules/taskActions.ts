import { TFunction } from 'i18next';
import { Task } from '@/api/server/schedules/getServerSchedules';

// Hydra: the actions of schedule tasks, their payloads and how they are shown.

export type TaskAction =
    | 'command'
    | 'power'
    | 'backup'
    | 'wait_state'
    | 'delete_files'
    | 'webhook'
    | 'if'
    | 'else'
    | 'endif'
    | 'stop';

export const ACTION_GROUPS: { key: string; actions: TaskAction[] }[] = [
    { key: 'server', actions: ['command', 'power', 'backup', 'wait_state', 'delete_files', 'webhook'] },
    { key: 'flow', actions: ['if', 'else', 'endif', 'stop'] },
];

// Flow control runs right away, a delay makes no sense there.
export const CONTROL_ACTIONS: TaskAction[] = ['if', 'else', 'endif', 'stop'];

export interface Condition {
    check: 'state' | 'file' | 'time' | 'weekday';
    op: 'is' | 'is_not';
    value: string;
}

export const parseJson = <T extends object>(payload: string, fallback: T): T => {
    try {
        const data = JSON.parse(payload);
        return data && typeof data === 'object' ? { ...fallback, ...data } : fallback;
    } catch (e) {
        return fallback;
    }
};

/**
 * Nesting depth of every task (by id), for indenting the blocks of "if".
 */
export const taskDepths = (tasks: Task[]): Record<number, number> => {
    const depths: Record<number, number> = {};
    let depth = 0;
    tasks.forEach((task) => {
        if (task.action === 'endif' || task.action === 'else') depth = Math.max(0, depth - 1);
        depths[task.id] = depth;
        if (task.action === 'if' || task.action === 'else') depth++;
    });

    return depths;
};

export const describeCondition = (t: TFunction, c: Condition): string => {
    const key = `client/server:schedules.tasks.conditions.${c.check}_${c.op}`;
    switch (c.check) {
        case 'state':
            return t(key, { value: t(`client/server:schedules.states.${c.value}`) });
        case 'weekday':
            return t(key, {
                value: c.value
                    .split(',')
                    .filter(Boolean)
                    .map((d) => t(`client/server:schedules.weekdays_short.${d}`))
                    .join(', '),
            });
        default:
            return t(key, { value: c.value });
    }
};

/**
 * A short text for what the task does.
 */
export const describeTask = (t: TFunction, task: Pick<Task, 'action' | 'payload'>): string | null => {
    const p = (key: string, values?: Record<string, unknown>) => t(`client/server:schedules.tasks.${key}`, values);
    switch (task.action) {
        case 'wait_state': {
            const data = parseJson(task.payload, { state: 'offline', timeout: 300 });
            return p('describe.wait_state', {
                state: t(`client/server:schedules.states.${data.state}`),
                timeout: data.timeout,
            });
        }
        case 'webhook': {
            const data = parseJson(task.payload, { url: '', message: '' });
            let host = data.url;
            try {
                host = new URL(data.url).host;
            } catch (e) {
                // Shown as entered.
            }
            return `${host}: ${data.message}`;
        }
        case 'if':
            return describeCondition(t, parseJson<Condition>(task.payload, { check: 'state', op: 'is', value: '' }));
        case 'else':
        case 'endif':
        case 'stop':
            return null;
        default:
            return task.payload || null;
    }
};
