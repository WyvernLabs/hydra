import {
    format as formatFns,
    formatDistanceToNow as formatDistanceToNowFns,
    formatDistanceToNowStrict as formatDistanceToNowStrictFns,
    Locale,
} from 'date-fns';
import { de, enUS } from 'date-fns/locale';
import i18n from '@/i18n';

const locales: Record<string, Locale> = { de, en: enUS };

/**
 * date-fns locale matching the active panel language. New languages only need an
 * entry in the map above; unknown languages fall back to English.
 */
export const dateLocale = (): Locale => locales[i18n.language] || enUS;

/**
 * Localized date formats, so components never hard code an English pattern.
 * "datetime" → "Oct 18, 2026 21:07" / "18. Okt. 2026 21:07"
 */
const patterns = {
    date: 'PP',
    datetime: 'PP p',
    datetimeSeconds: 'PP pp',
};

export const formatDate = (date: Date | number, pattern: keyof typeof patterns = 'datetime'): string =>
    formatFns(date, patterns[pattern], { locale: dateLocale() });

export const formatDistanceToNow = (
    date: Date | number,
    options: { addSuffix?: boolean; includeSeconds?: boolean } = {}
): string => formatDistanceToNowFns(date, { ...options, locale: dateLocale() });

export const formatDistanceToNowStrict = (date: Date | number, options: { addSuffix?: boolean } = {}): string =>
    formatDistanceToNowStrictFns(date, { ...options, locale: dateLocale() });
