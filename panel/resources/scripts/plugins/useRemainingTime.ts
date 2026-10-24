import { useEffect, useRef, useState } from 'react';
import { TFunction } from 'i18next';

// Hydra: time left of an upload, download, archive or backup, from the speed
// over the last seconds.

// The speed is measured over this time span.
const RATE_WINDOW = 8000;

interface Sample {
    at: number;
    loaded: number;
}

/**
 * Seconds left until loaded reaches total, or null while there is not enough
 * data to tell. Re-renders every second while active.
 */
export default (loaded: number, total: number, active: boolean): number | null => {
    const samples = useRef<Sample[]>([]);
    const [, setTick] = useState(0);

    useEffect(() => {
        if (!active) {
            samples.current = [];
            return;
        }

        const interval = setInterval(() => setTick((value) => value + 1), 1000);
        return () => clearInterval(interval);
    }, [active]);

    if (!active || total <= 0) return null;

    const now = Date.now();
    const list = samples.current;
    const last = list[list.length - 1];
    if (!last || last.loaded !== loaded) {
        // Progress going back (e.g. the next phase) starts the measurement anew.
        if (last && loaded < last.loaded) list.length = 0;
        list.push({ at: now, loaded });
    }
    while (list.length > 2 && now - list[0].at > RATE_WINDOW) {
        list.shift();
    }

    if (list.length < 2) return null;
    const first = list[0];
    const latest = list[list.length - 1];
    const span = latest.at - first.at;
    if (span < 1500) return null;

    const rate = (latest.loaded - first.loaded) / (span / 1000);
    if (rate <= 0) return null;

    return Math.max(0, (total - loaded) / rate);
};

/**
 * "about 1 min 20 s left" and the like, rounded so it does not jump around.
 */
export const formatRemaining = (t: TFunction, seconds: number | null): string => {
    if (seconds === null) return t('client/server:files.transfers.calculating');

    let time: string;
    if (seconds < 60) {
        time = t('client/server:files.transfers.seconds', { s: Math.max(1, Math.ceil(seconds / 5) * 5) });
    } else if (seconds < 3600) {
        const rounded = Math.ceil(seconds / 10) * 10;
        time = t('client/server:files.transfers.minutes', { m: Math.floor(rounded / 60), s: rounded % 60 });
    } else {
        const minutes = Math.ceil(seconds / 60);
        time = t('client/server:files.transfers.hours', { h: Math.floor(minutes / 60), m: minutes % 60 });
    }

    return t('client/server:files.transfers.remaining', { time });
};
