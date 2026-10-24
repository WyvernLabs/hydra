import React, { useContext, useEffect, useState } from 'react';
import { Schedule, Task } from '@/api/server/schedules/getServerSchedules';
import { ServerContext } from '@/state/server';
import createOrUpdateScheduleTask from '@/api/server/schedules/createOrUpdateScheduleTask';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import Label from '@/components/elements/Label';
import Input, { Textarea } from '@/components/elements/Input';
import { Button } from '@/components/elements/button/index';
import Select from '@/components/elements/Select';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import Switch from '@/components/elements/Switch';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import {
    ACTION_GROUPS,
    Condition,
    CONTROL_ACTIONS,
    parseJson,
    TaskAction,
} from '@/components/server/schedules/taskActions';

interface Props {
    schedule: Schedule;
    // If a task is provided we can assume we're editing it. If not provided,
    // we are creating a new one.
    task?: Task;
}

const STATES = ['running', 'offline', 'starting', 'stopping'];

const Help = ({ children }: { children: React.ReactNode }) => (
    <p className={'mt-1 text-xs text-gray-400'}>{children}</p>
);

const Weekdays = ({ value, onChange }: { value: string; onChange: (value: string) => void }) => {
    const { t } = useTranslation('client/server');
    const days = value.split(',').filter(Boolean).map(Number);

    return (
        <div className={'flex flex-wrap gap-2'}>
            {[1, 2, 3, 4, 5, 6, 7].map((day) => {
                const active = days.includes(day);
                return (
                    <button
                        key={day}
                        type={'button'}
                        onClick={() =>
                            onChange((active ? days.filter((d) => d !== day) : [...days, day]).sort().join(','))
                        }
                        className={classNames(
                            'w-12 rounded border py-1.5 text-sm transition-colors',
                            active
                                ? 'border-accent-500 bg-accent-500/20 text-gray-50'
                                : 'border-gray-600 bg-gray-800 text-gray-300 hover:border-gray-500'
                        )}
                    >
                        {t(`schedules.weekdays_short.${day}`)}
                    </button>
                );
            })}
        </div>
    );
};

const ConditionFields = ({ value, onChange }: { value: Condition; onChange: (c: Condition) => void }) => {
    const { t } = useTranslation('client/server');
    const [from, to] = value.value.includes('-') ? value.value.split('-') : ['08:00', '22:00'];

    return (
        <div className={'space-y-4'}>
            <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                <div>
                    <Label>{t('schedules.tasks.condition_check')}</Label>
                    <Select
                        value={value.check}
                        onChange={(e) => {
                            const check = e.currentTarget.value as Condition['check'];
                            const defaults: Record<Condition['check'], string> = {
                                state: 'running',
                                file: '',
                                time: '08:00-22:00',
                                weekday: '6,7',
                            };
                            onChange({ ...value, check, value: defaults[check] });
                        }}
                    >
                        {(['state', 'file', 'time', 'weekday'] as const).map((check) => (
                            <option key={check} value={check}>
                                {t(`schedules.tasks.checks.${check}`)}
                            </option>
                        ))}
                    </Select>
                </div>
                <div>
                    <Label>{t('schedules.tasks.condition_op')}</Label>
                    <Select
                        value={value.op}
                        onChange={(e) => onChange({ ...value, op: e.currentTarget.value as Condition['op'] })}
                    >
                        <option value={'is'}>{t(`schedules.tasks.ops.${value.check}_is`)}</option>
                        <option value={'is_not'}>{t(`schedules.tasks.ops.${value.check}_is_not`)}</option>
                    </Select>
                </div>
            </div>
            {value.check === 'state' && (
                <Select value={value.value} onChange={(e) => onChange({ ...value, value: e.currentTarget.value })}>
                    {STATES.map((s) => (
                        <option key={s} value={s}>
                            {t(`schedules.states.${s}`)}
                        </option>
                    ))}
                </Select>
            )}
            {value.check === 'file' && (
                <div>
                    <Input
                        value={value.value}
                        className={'font-mono'}
                        placeholder={'world/session.lock'}
                        onChange={(e) => onChange({ ...value, value: e.currentTarget.value })}
                    />
                    <Help>{t('schedules.tasks.file_help')}</Help>
                </div>
            )}
            {value.check === 'time' && (
                <div>
                    <div className={'grid grid-cols-2 gap-4'}>
                        <Input
                            type={'time'}
                            value={from}
                            onChange={(e) => onChange({ ...value, value: `${e.currentTarget.value}-${to}` })}
                        />
                        <Input
                            type={'time'}
                            value={to}
                            onChange={(e) => onChange({ ...value, value: `${from}-${e.currentTarget.value}` })}
                        />
                    </div>
                    <Help>{t('schedules.tasks.time_help')}</Help>
                </div>
            )}
            {value.check === 'weekday' && (
                <Weekdays value={value.value} onChange={(v) => onChange({ ...value, value: v })} />
            )}
        </div>
    );
};

const TaskDetailsModal = ({ schedule, task }: Props) => {
    const { dismiss } = useContext(ModalContext);
    const { t } = useTranslation('client/server');
    const { clearFlashes, addError } = useFlash();

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);
    // Hydra: backups are limited by their space.
    const backupLimit = ServerContext.useStoreState((state) => state.server.data!.featureLimits.backup_storage);

    const [action, setAction] = useState<TaskAction>((task?.action as TaskAction) || 'command');
    const [payload, setPayload] = useState(task?.payload || '');
    const [timeOffset, setTimeOffset] = useState(task?.timeOffset ?? 0);
    const [continueOnFailure, setContinueOnFailure] = useState(task?.continueOnFailure || false);
    const [submitting, setSubmitting] = useState(false);

    // Structured payloads of the new actions.
    const [wait, setWait] = useState(() => parseJson(task?.payload || '', { state: 'offline', timeout: 300 }));
    const [webhook, setWebhook] = useState(() => parseJson(task?.payload || '', { url: '', message: '' }));
    const [condition, setCondition] = useState<Condition>(() =>
        parseJson<Condition>(task?.payload || '', { check: 'state', op: 'is', value: 'running' })
    );

    useEffect(() => {
        return () => {
            clearFlashes('schedule:task');
        };
    }, []);

    const changeAction = (next: TaskAction) => {
        setAction(next);
        if (next !== task?.action) {
            setPayload(next === 'power' ? 'start' : '');
        } else {
            setPayload(task.payload);
        }
    };

    const isControl = CONTROL_ACTIONS.includes(action);

    const buildPayload = (): string => {
        switch (action) {
            case 'wait_state':
                return JSON.stringify({ state: wait.state, timeout: Number(wait.timeout) });
            case 'webhook':
                return JSON.stringify({ url: webhook.url.trim(), message: webhook.message });
            case 'if':
                return JSON.stringify(condition);
            case 'else':
            case 'endif':
            case 'stop':
                return '';
            default:
                return payload;
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('schedule:task');
        if (backupLimit === 0 && action === 'backup') {
            addError({ message: t('schedules.tasks.backup_limit'), key: 'schedule:task' });
            return;
        }

        setSubmitting(true);
        createOrUpdateScheduleTask(uuid, schedule.id, task?.id, {
            action,
            payload: buildPayload(),
            timeOffset: isControl ? 0 : timeOffset,
            continueOnFailure: isControl ? false : continueOnFailure,
        })
            .then((task) => {
                let tasks = schedule.tasks.map((t) => (t.id === task.id ? task : t));
                if (!schedule.tasks.find((t) => t.id === task.id)) {
                    tasks = [...tasks, task];
                }

                appendSchedule({ ...schedule, tasks });
                dismiss();
            })
            .catch((error) => {
                console.error(error);
                setSubmitting(false);
                addError({ message: httpErrorToHuman(error), key: 'schedule:task' });
            });
    };

    return (
        <form onSubmit={submit} css={tw`m-0`}>
            <FlashMessageRender byKey={'schedule:task'} css={tw`mb-4`} />
            <h2 css={tw`text-2xl mb-6`}>
                {task ? t('schedules.tasks.edit_title') : t('schedules.tasks.create_title')}
            </h2>
            <div className={'grid grid-cols-1 gap-4 sm:grid-cols-3'}>
                <div className={isControl ? 'sm:col-span-3' : 'sm:col-span-2'}>
                    <Label>{t('schedules.tasks.action')}</Label>
                    <Select value={action} onChange={(e) => changeAction(e.currentTarget.value as TaskAction)}>
                        {ACTION_GROUPS.map((group) => (
                            <optgroup key={group.key} label={t(`schedules.tasks.groups.${group.key}`)}>
                                {group.actions.map((a) => (
                                    <option key={a} value={a}>
                                        {t(`schedules.tasks.actions.${a}`)}
                                    </option>
                                ))}
                            </optgroup>
                        ))}
                    </Select>
                </div>
                {!isControl && (
                    <div>
                        <Label>{t('schedules.tasks.time_offset')}</Label>
                        <Input
                            type={'number'}
                            min={0}
                            max={900}
                            value={timeOffset}
                            onChange={(e) => setTimeOffset(Number(e.currentTarget.value))}
                        />
                    </div>
                )}
            </div>
            {!isControl && <Help>{t('schedules.tasks.time_offset_help')}</Help>}
            <p className={'mt-4 text-sm text-gray-300'}>{t(`schedules.tasks.help.${action}`)}</p>

            <div css={tw`mt-4`}>
                {action === 'command' && (
                    <>
                        <Label>{t('schedules.tasks.payload')}</Label>
                        <Textarea rows={4} value={payload} onChange={(e) => setPayload(e.currentTarget.value)} />
                    </>
                )}
                {action === 'power' && (
                    <>
                        <Label>{t('schedules.tasks.payload')}</Label>
                        <Select value={payload} onChange={(e) => setPayload(e.currentTarget.value)}>
                            <option value={'start'}>{t('schedules.tasks.power_start')}</option>
                            <option value={'restart'}>{t('schedules.tasks.power_restart')}</option>
                            <option value={'stop'}>{t('schedules.tasks.power_stop')}</option>
                            <option value={'kill'}>{t('schedules.tasks.power_kill')}</option>
                        </Select>
                    </>
                )}
                {action === 'backup' && (
                    <>
                        <Label>{t('schedules.tasks.ignored_files')}</Label>
                        <Textarea rows={4} value={payload} onChange={(e) => setPayload(e.currentTarget.value)} />
                        <Help>{t('schedules.tasks.ignored_files_help')}</Help>
                    </>
                )}
                {action === 'wait_state' && (
                    <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                        <div>
                            <Label>{t('schedules.tasks.wait_for')}</Label>
                            <Select
                                value={wait.state}
                                onChange={(e) => setWait({ ...wait, state: e.currentTarget.value })}
                            >
                                {STATES.map((s) => (
                                    <option key={s} value={s}>
                                        {t(`schedules.states.${s}`)}
                                    </option>
                                ))}
                            </Select>
                        </div>
                        <div>
                            <Label>{t('schedules.tasks.wait_timeout')}</Label>
                            <Input
                                type={'number'}
                                min={10}
                                max={3600}
                                value={wait.timeout}
                                onChange={(e) => setWait({ ...wait, timeout: Number(e.currentTarget.value) })}
                            />
                        </div>
                    </div>
                )}
                {action === 'delete_files' && (
                    <>
                        <Label>{t('schedules.tasks.paths')}</Label>
                        <Textarea
                            rows={4}
                            className={'font-mono'}
                            placeholder={'logs/old.log\ncache'}
                            value={payload}
                            onChange={(e) => setPayload(e.currentTarget.value)}
                        />
                        <Help>{t('schedules.tasks.paths_help')}</Help>
                    </>
                )}
                {action === 'webhook' && (
                    <div className={'space-y-4'}>
                        <div>
                            <Label>{t('schedules.tasks.webhook_url')}</Label>
                            <Input
                                type={'url'}
                                value={webhook.url}
                                placeholder={'https://discord.com/api/webhooks/…'}
                                onChange={(e) => setWebhook({ ...webhook, url: e.currentTarget.value })}
                            />
                        </div>
                        <div>
                            <Label>{t('schedules.tasks.webhook_message')}</Label>
                            <Textarea
                                rows={3}
                                value={webhook.message}
                                onChange={(e) => setWebhook({ ...webhook, message: e.currentTarget.value })}
                            />
                            <Help>{t('schedules.tasks.webhook_help')}</Help>
                        </div>
                    </div>
                )}
                {action === 'if' && <ConditionFields value={condition} onChange={setCondition} />}
            </div>

            {!isControl && (
                <div css={tw`mt-6 bg-neutral-700 border border-neutral-800 shadow-inner p-4 rounded`}>
                    <Switch
                        name={'continueOnFailure'}
                        description={t('schedules.tasks.continue_on_failure_help')}
                        label={t('schedules.tasks.continue_on_failure')}
                        defaultChecked={continueOnFailure}
                        onChange={(e) => setContinueOnFailure(e.currentTarget.checked)}
                    />
                </div>
            )}
            <div css={tw`flex justify-end mt-6`}>
                <Button type={'submit'} disabled={submitting}>
                    {task ? t('schedules.tasks.save') : t('schedules.tasks.create')}
                </Button>
            </div>
        </form>
    );
};

export default asModal<Props>()(TaskDetailsModal);
