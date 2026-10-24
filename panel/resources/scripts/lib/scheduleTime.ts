import { TFunction } from 'i18next';

// Hydra: simple choices for when a time based schedule runs, turned into the
// five cron fields and back.

export type TimePreset = 'minutes' | 'hourly' | 'daily' | 'weekly' | 'monthly' | 'custom';

export interface TimeValues {
    preset: TimePreset;
    // Every n minutes.
    interval: number;
    // Minute of the hour (hourly) and time of day (daily, weekly, monthly).
    minute: number;
    hour: number;
    // ISO weekdays 1 (Monday) to 7 (Sunday) for weekly.
    weekdays: number[];
    // Day of the month for monthly.
    day: number;
    // Raw fields for custom.
    cron: Cron;
}

export interface Cron {
    minute: string;
    hour: string;
    dayOfMonth: string;
    month: string;
    dayOfWeek: string;
}

const isNumber = (value: string, min: number, max: number): boolean =>
    /^\d+$/.test(value) && Number(value) >= min && Number(value) <= max;

// Cron counts Sunday as 0, the form uses ISO weekdays.
const toCronDay = (day: number): number => day % 7;
const fromCronDay = (day: number): number => (day === 0 ? 7 : day);

/**
 * The form values for the cron fields, falling back to "custom" for anything
 * the simple choices cannot express.
 */
export const fromCron = (cron: Cron): TimeValues => {
    const base: TimeValues = {
        preset: 'custom',
        interval: 5,
        minute: 0,
        hour: 4,
        weekdays: [1],
        day: 1,
        cron,
    };
    const { minute, hour, dayOfMonth, month, dayOfWeek } = cron;
    if (month !== '*') return base;

    const everyMinutes = /^\*\/(\d+)$/.exec(minute);
    if ((everyMinutes || minute === '*') && hour === '*' && dayOfMonth === '*' && dayOfWeek === '*') {
        return { ...base, preset: 'minutes', interval: everyMinutes ? Number(everyMinutes[1]) : 1 };
    }
    if (!isNumber(minute, 0, 59)) return base;
    const m = Number(minute);

    if (hour === '*' && dayOfMonth === '*' && dayOfWeek === '*') {
        return { ...base, preset: 'hourly', minute: m };
    }
    if (!isNumber(hour, 0, 23)) return base;
    const h = Number(hour);

    if (dayOfMonth === '*' && dayOfWeek === '*') {
        return { ...base, preset: 'daily', minute: m, hour: h };
    }
    if (dayOfMonth === '*' && /^[0-7](,[0-7])*$/.test(dayOfWeek)) {
        const weekdays = Array.from(new Set(dayOfWeek.split(',').map((d) => fromCronDay(Number(d))))).sort();
        return { ...base, preset: 'weekly', minute: m, hour: h, weekdays };
    }
    if (dayOfWeek === '*' && isNumber(dayOfMonth, 1, 31)) {
        return { ...base, preset: 'monthly', minute: m, hour: h, day: Number(dayOfMonth) };
    }

    return base;
};

/**
 * The cron fields for the form values.
 */
export const toCron = (values: TimeValues): Cron => {
    const m = String(values.minute);
    const h = String(values.hour);
    switch (values.preset) {
        case 'minutes':
            return {
                minute: values.interval <= 1 ? '*' : `*/${values.interval}`,
                hour: '*',
                dayOfMonth: '*',
                month: '*',
                dayOfWeek: '*',
            };
        case 'hourly':
            return { minute: m, hour: '*', dayOfMonth: '*', month: '*', dayOfWeek: '*' };
        case 'daily':
            return { minute: m, hour: h, dayOfMonth: '*', month: '*', dayOfWeek: '*' };
        case 'weekly':
            return {
                minute: m,
                hour: h,
                dayOfMonth: '*',
                month: '*',
                dayOfWeek: (values.weekdays.length ? values.weekdays : [1]).slice().sort().map(toCronDay).join(','),
            };
        case 'monthly':
            return { minute: m, hour: h, dayOfMonth: String(values.day), month: '*', dayOfWeek: '*' };
        default:
            return values.cron;
    }
};

const pad = (value: number): string => String(value).padStart(2, '0');

/**
 * A sentence like "Every day at 04:00" for the cron fields.
 */
export const describeCron = (t: TFunction, cron: Cron): string => {
    const values = fromCron(cron);
    const time = `${pad(values.hour)}:${pad(values.minute)}`;
    switch (values.preset) {
        case 'minutes':
            return values.interval <= 1
                ? t('client/server:schedules.time.every_minute')
                : t('client/server:schedules.time.every_minutes', { count: values.interval });
        case 'hourly':
            return t('client/server:schedules.time.every_hour', { minute: pad(values.minute) });
        case 'daily':
            return t('client/server:schedules.time.every_day', { time });
        case 'weekly':
            return t('client/server:schedules.time.every_week', {
                days: values.weekdays.map((d) => t(`client/server:schedules.weekdays_short.${d}`)).join(', '),
                time,
            });
        case 'monthly':
            return t('client/server:schedules.time.every_month', { day: values.day, time });
        default:
            return `${cron.minute} ${cron.hour} ${cron.dayOfMonth} ${cron.month} ${cron.dayOfWeek}`;
    }
};
