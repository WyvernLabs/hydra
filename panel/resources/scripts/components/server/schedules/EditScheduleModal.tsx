import React, { useContext, useEffect, useState } from 'react';
import { Schedule, ScheduleTriggerType } from '@/api/server/schedules/getServerSchedules';
import createOrUpdateSchedule from '@/api/server/schedules/createOrUpdateSchedule';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import ModalContext from '@/context/ModalContext';
import asModal from '@/hoc/asModal';
import Switch from '@/components/elements/Switch';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Label from '@/components/elements/Label';
import ScheduleCheatsheetCards from '@/components/server/schedules/ScheduleCheatsheetCards';
import { useTranslation } from 'react-i18next';
import { Cron, describeCron, fromCron, TimePreset, TimeValues, toCron } from '@/lib/scheduleTime';
import classNames from 'classnames';

interface Props {
    schedule?: Schedule;
}

// Hydra: the schedule runs at certain times, when the server reaches a state or
// when a line in its console matches. Times are picked from simple choices,
// the cron fields stay available as "custom".

const PRESETS: TimePreset[] = ['minutes', 'hourly', 'daily', 'weekly', 'monthly', 'custom'];
const TRIGGERS: ScheduleTriggerType[] = ['cron', 'state', 'console'];
const STATES = ['running', 'offline', 'starting', 'stopping'];

const pad = (value: number): string => String(value).padStart(2, '0');

const Box = ({ children }: { children: React.ReactNode }) => (
    <div css={tw`mt-6 bg-neutral-700 border border-neutral-800 shadow-inner p-4 rounded`}>{children}</div>
);

const TimeFields = ({ value, onChange }: { value: TimeValues; onChange: (v: TimeValues) => void }) => {
    const { t } = useTranslation('client/server');
    const [showCheatsheet, setShowCheatsheet] = useState(false);
    const set = (patch: Partial<TimeValues>) => onChange({ ...value, ...patch });
    const time = `${pad(value.hour)}:${pad(value.minute)}`;
    const setTime = (raw: string) => {
        const [h, m] = raw.split(':').map(Number);
        if (!isNaN(h) && !isNaN(m)) set({ hour: h, minute: m });
    };
    const setCron = (key: keyof Cron, raw: string) => set({ cron: { ...value.cron, [key]: raw } });

    return (
        <div className={'mt-4 space-y-4'}>
            <div>
                <Label>{t('schedules.time.repeat')}</Label>
                <Select
                    value={value.preset}
                    onChange={(e) => {
                        const preset = e.currentTarget.value as TimePreset;
                        // Custom starts from the fields of the current choice.
                        set(preset === 'custom' ? { preset, cron: toCron(value) } : { preset });
                    }}
                >
                    {PRESETS.map((preset) => (
                        <option key={preset} value={preset}>
                            {t(`schedules.time.presets.${preset}`)}
                        </option>
                    ))}
                </Select>
            </div>
            {value.preset === 'minutes' && (
                <div>
                    <Label>{t('schedules.time.interval')}</Label>
                    <Select value={value.interval} onChange={(e) => set({ interval: Number(e.currentTarget.value) })}>
                        {[1, 2, 5, 10, 15, 20, 30].map((n) => (
                            <option key={n} value={n}>
                                {t('schedules.time.minutes_option', { count: n })}
                            </option>
                        ))}
                    </Select>
                </div>
            )}
            {value.preset === 'hourly' && (
                <div>
                    <Label>{t('schedules.time.at_minute')}</Label>
                    <Input
                        type={'number'}
                        min={0}
                        max={59}
                        value={value.minute}
                        onChange={(e) => set({ minute: Math.min(59, Math.max(0, Number(e.currentTarget.value))) })}
                    />
                </div>
            )}
            {['daily', 'weekly', 'monthly'].includes(value.preset) && (
                <div className={'grid grid-cols-1 gap-4 sm:grid-cols-2'}>
                    <div>
                        <Label>{t('schedules.time.at_time')}</Label>
                        <Input type={'time'} value={time} onChange={(e) => setTime(e.currentTarget.value)} />
                    </div>
                    {value.preset === 'monthly' && (
                        <div>
                            <Label>{t('schedules.time.day_of_month')}</Label>
                            <Select value={value.day} onChange={(e) => set({ day: Number(e.currentTarget.value) })}>
                                {Array.from({ length: 28 }, (_, i) => i + 1).map((day) => (
                                    <option key={day} value={day}>
                                        {day}.
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                </div>
            )}
            {value.preset === 'weekly' && (
                <div>
                    <Label>{t('schedules.time.weekdays')}</Label>
                    <div className={'flex flex-wrap gap-2'}>
                        {[1, 2, 3, 4, 5, 6, 7].map((day) => {
                            const active = value.weekdays.includes(day);
                            return (
                                <button
                                    key={day}
                                    type={'button'}
                                    onClick={() =>
                                        set({
                                            weekdays: active
                                                ? value.weekdays.filter((d) => d !== day)
                                                : [...value.weekdays, day],
                                        })
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
                </div>
            )}
            {value.preset === 'custom' && (
                <>
                    <div css={tw`grid grid-cols-2 sm:grid-cols-5 gap-4`}>
                        {(
                            [
                                ['minute', 'schedules.minute'],
                                ['hour', 'schedules.hour'],
                                ['dayOfMonth', 'schedules.day_of_month'],
                                ['month', 'schedules.month'],
                                ['dayOfWeek', 'schedules.day_of_week'],
                            ] as [keyof Cron, string][]
                        ).map(([key, label]) => (
                            <div key={key}>
                                <Label>{t(label)}</Label>
                                <Input value={value.cron[key]} onChange={(e) => setCron(key, e.currentTarget.value)} />
                            </div>
                        ))}
                    </div>
                    <p css={tw`text-neutral-400 text-xs`}>{t('schedules.cron_help')}</p>
                    <div css={tw`bg-neutral-800 p-4 rounded`}>
                        <Switch
                            name={'show_cheatsheet'}
                            description={t('schedules.show_cheatsheet_help')}
                            label={t('schedules.show_cheatsheet')}
                            defaultChecked={showCheatsheet}
                            onChange={() => setShowCheatsheet((s) => !s)}
                        />
                        {showCheatsheet && (
                            <div css={tw`block md:flex w-full`}>
                                <ScheduleCheatsheetCards />
                            </div>
                        )}
                    </div>
                </>
            )}
            <p className={'text-sm text-gray-300'}>
                {t('schedules.time.summary')} <strong>{describeCron(t, toCron(value))}</strong>
            </p>
        </div>
    );
};

const EditScheduleModal = ({ schedule }: Props) => {
    const { addError, clearFlashes } = useFlash();
    const { dismiss } = useContext(ModalContext);
    const { t } = useTranslation('client/server');

    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const appendSchedule = ServerContext.useStoreActions((actions) => actions.schedules.appendSchedule);

    const [name, setName] = useState(schedule?.name || '');
    const [trigger, setTrigger] = useState<ScheduleTriggerType>(schedule?.trigger.type || 'cron');
    const [time, setTime] = useState<TimeValues>(() =>
        schedule
            ? fromCron(schedule.cron)
            : { ...fromCron({ minute: '0', hour: '4', dayOfMonth: '*', month: '*', dayOfWeek: '*' }) }
    );
    const [state, setState] = useState(
        schedule?.trigger.type === 'state' ? schedule.trigger.value || 'running' : 'running'
    );
    const [match, setMatch] = useState(schedule?.trigger.type === 'console' ? schedule.trigger.value || '' : '');
    const [regex, setRegex] = useState(schedule?.trigger.regex || false);
    const [cooldown, setCooldown] = useState(schedule?.trigger.cooldown ?? 60);
    const [enabled, setEnabled] = useState(schedule?.isActive ?? true);
    const [onlyWhenOnline, setOnlyWhenOnline] = useState(schedule?.onlyWhenOnline ?? false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        return () => {
            clearFlashes('schedule:edit');
        };
    }, []);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        clearFlashes('schedule:edit');
        setSubmitting(true);

        createOrUpdateSchedule(uuid, {
            id: schedule?.id,
            name,
            // Schedules started by the server keep valid cron fields, they are not used.
            cron:
                trigger === 'cron'
                    ? toCron(time)
                    : { minute: '*', hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' },
            trigger: {
                type: trigger,
                value: trigger === 'state' ? state : trigger === 'console' ? match : null,
                regex: trigger === 'console' && regex,
                cooldown,
            },
            onlyWhenOnline,
            isActive: enabled,
        })
            .then((schedule) => {
                setSubmitting(false);
                appendSchedule(schedule);
                dismiss();
            })
            .catch((error) => {
                console.error(error);

                setSubmitting(false);
                addError({ key: 'schedule:edit', message: httpErrorToHuman(error) });
            });
    };

    return (
        <form onSubmit={submit}>
            <h3 css={tw`text-2xl mb-6`}>{schedule ? t('schedules.edit_title') : t('schedules.create_title')}</h3>
            <FlashMessageRender byKey={'schedule:edit'} css={tw`mb-6`} />
            <div>
                <Label>{t('schedules.name')}</Label>
                <Input value={name} onChange={(e) => setName(e.currentTarget.value)} maxLength={191} required />
                <p className={'mt-1 text-xs text-gray-400'}>{t('schedules.name_help')}</p>
            </div>

            <div className={'mt-6'}>
                <Label>{t('schedules.trigger.title')}</Label>
                <div className={'grid grid-cols-1 gap-2 sm:grid-cols-3'}>
                    {TRIGGERS.map((type) => (
                        <button
                            key={type}
                            type={'button'}
                            onClick={() => setTrigger(type)}
                            className={classNames(
                                'rounded border p-3 text-left text-sm transition-colors',
                                trigger === type
                                    ? 'border-accent-500 bg-accent-500/10 text-gray-50'
                                    : 'border-gray-600 bg-gray-700 text-gray-300 hover:border-gray-500'
                            )}
                        >
                            <span className={'block font-medium'}>{t(`schedules.trigger.${type}`)}</span>
                            <span className={'mt-1 block text-xs text-gray-400'}>
                                {t(`schedules.trigger.${type}_help`)}
                            </span>
                        </button>
                    ))}
                </div>
            </div>

            {trigger === 'cron' && <TimeFields value={time} onChange={setTime} />}
            {trigger === 'state' && (
                <div className={'mt-4'}>
                    <Label>{t('schedules.trigger.state_label')}</Label>
                    <Select value={state} onChange={(e) => setState(e.currentTarget.value)}>
                        {STATES.map((s) => (
                            <option key={s} value={s}>
                                {t(`schedules.states.${s}`)}
                            </option>
                        ))}
                    </Select>
                </div>
            )}
            {trigger === 'console' && (
                <div className={'mt-4 space-y-3'}>
                    <div>
                        <Label>{t('schedules.trigger.console_label')}</Label>
                        <Input
                            value={match}
                            onChange={(e) => setMatch(e.currentTarget.value)}
                            maxLength={255}
                            placeholder={regex ? '^\\[\\d+:\\d+:\\d+\\] .* joined the game$' : 'joined the game'}
                            className={'font-mono'}
                            required
                        />
                        <p className={'mt-1 text-xs text-gray-400'}>
                            {regex ? t('schedules.trigger.regex_help') : t('schedules.trigger.console_input_help')}
                        </p>
                    </div>
                    <label className={'flex items-center gap-2 text-sm text-gray-200'}>
                        <input type={'checkbox'} checked={regex} onChange={(e) => setRegex(e.currentTarget.checked)} />
                        {t('schedules.trigger.regex')}
                    </label>
                </div>
            )}
            {trigger !== 'cron' && (
                <div className={'mt-4'}>
                    <Label>{t('schedules.trigger.cooldown')}</Label>
                    <Input
                        type={'number'}
                        min={5}
                        max={86400}
                        value={cooldown}
                        onChange={(e) => setCooldown(Number(e.currentTarget.value))}
                    />
                    <p className={'mt-1 text-xs text-gray-400'}>{t('schedules.trigger.cooldown_help')}</p>
                </div>
            )}

            <Box>
                <Switch
                    name={'onlyWhenOnline'}
                    description={t('schedules.only_online_help')}
                    label={t('schedules.only_online')}
                    defaultChecked={onlyWhenOnline}
                    onChange={(e) => setOnlyWhenOnline(e.currentTarget.checked)}
                />
            </Box>
            <Box>
                <Switch
                    name={'enabled'}
                    description={t('schedules.enabled_help')}
                    label={t('schedules.enabled')}
                    defaultChecked={enabled}
                    onChange={(e) => setEnabled(e.currentTarget.checked)}
                />
            </Box>
            <div css={tw`mt-6 text-right`}>
                <Button className={'w-full sm:w-auto'} type={'submit'} disabled={submitting}>
                    {schedule ? t('schedules.save') : t('schedules.create')}
                </Button>
            </div>
        </form>
    );
};

export default asModal<Props>()(EditScheduleModal);
