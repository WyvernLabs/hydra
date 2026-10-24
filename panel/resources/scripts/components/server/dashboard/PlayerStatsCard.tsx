import React, { useEffect, useMemo, useRef, useState } from 'react';
import useSWR from 'swr';
import { useTranslation } from 'react-i18next';
import { Users } from 'lucide-react';
import i18n from '@/i18n';
import { ServerContext } from '@/state/server';
import { getPlayerHistory, PlayerHistory, PlayerRange } from '@/api/server/players';

// Hydra: players online over time on the server dashboard (addon
// "player_stats"). One series, so no legend: a line with a light wash below,
// gaps where the server did not answer, a crosshair with the value on hover.

const RANGES: PlayerRange[] = ['day', 'week', 'month'];
const HEIGHT = 200;
const MARGIN = { top: 10, right: 12, bottom: 24, left: 34 };

// Clean steps for the y axis: 1, 2, 5, 10, 20, 50, …
const niceStep = (max: number): number => {
    const rough = Math.max(1, max / 4);
    const power = Math.pow(10, Math.floor(Math.log10(rough)));
    const step = [1, 2, 5, 10].map((factor) => factor * power).find((value) => value >= rough) || rough;
    return Math.max(1, Math.round(step));
};

const timeLabel = (date: Date, range: PlayerRange): string =>
    new Intl.DateTimeFormat(
        i18n.language,
        range === 'day' ? { hour: '2-digit', minute: '2-digit' } : { day: 'numeric', month: 'short' }
    ).format(date);

const fullTimeLabel = (date: Date, range: PlayerRange): string =>
    new Intl.DateTimeFormat(i18n.language, {
        weekday: range === 'day' ? undefined : 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);

const Chart = ({ history }: { history: PlayerHistory }) => {
    const { t } = useTranslation('client/server');
    const box = useRef<HTMLDivElement>(null);
    const [width, setWidth] = useState(600);
    const [hover, setHover] = useState<number | null>(null);

    useEffect(() => {
        if (!box.current) return;
        const observer = new ResizeObserver(([entry]) => setWidth(Math.max(240, entry.contentRect.width)));
        observer.observe(box.current);
        return () => observer.disconnect();
    }, []);

    const points = history.points;
    const top = Math.max(1, history.peak || 0);
    const step = niceStep(top);
    const yMax = Math.ceil(top / step) * step;
    const plotWidth = width - MARGIN.left - MARGIN.right;
    const plotHeight = HEIGHT - MARGIN.top - MARGIN.bottom;
    const x = (index: number) =>
        MARGIN.left + (points.length > 1 ? (index / (points.length - 1)) * plotWidth : plotWidth / 2);
    const y = (value: number) => MARGIN.top + plotHeight - (value / yMax) * plotHeight;

    // Runs of points with values; the server was offline between them.
    const runs = useMemo(() => {
        const list: number[][] = [];
        let current: number[] = [];
        points.forEach((point, index) => {
            if (point.online === null) {
                if (current.length) list.push(current);
                current = [];
            } else {
                current.push(index);
            }
        });
        if (current.length) list.push(current);
        return list;
    }, [points, width]);

    const ticks: number[] = [];
    for (let value = 0; value <= yMax; value += step) ticks.push(value);
    const labelEvery = Math.ceil(points.length / Math.max(2, Math.floor(plotWidth / 90)));

    const onMove = (event: React.PointerEvent<SVGSVGElement>) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const relative = (event.clientX - rect.left - MARGIN.left) / plotWidth;
        setHover(Math.max(0, Math.min(points.length - 1, Math.round(relative * (points.length - 1)))));
    };

    const hovered = hover !== null ? points[hover] : null;

    return (
        <div ref={box} className={'relative'}>
            <svg
                width={width}
                height={HEIGHT}
                className={'block touch-none'}
                onPointerMove={onMove}
                onPointerDown={onMove}
                onPointerLeave={() => setHover(null)}
                role={'img'}
                aria-label={t('dashboard.players.chart_label')}
            >
                {ticks.map((value) => (
                    <g key={value}>
                        <line
                            x1={MARGIN.left}
                            x2={width - MARGIN.right}
                            y1={y(value)}
                            y2={y(value)}
                            stroke={'var(--hydra-border)'}
                            strokeWidth={1}
                        />
                        <text
                            x={MARGIN.left - 8}
                            y={y(value)}
                            dy={'0.32em'}
                            textAnchor={'end'}
                            className={'fill-gray-400'}
                            fontSize={11}
                        >
                            {value}
                        </text>
                    </g>
                ))}
                {points.map((point, index) =>
                    index % labelEvery === 0 ? (
                        <text
                            key={index}
                            x={x(index)}
                            y={HEIGHT - 6}
                            textAnchor={index === 0 ? 'start' : 'middle'}
                            className={'fill-gray-400'}
                            fontSize={11}
                        >
                            {timeLabel(point.time, history.range)}
                        </text>
                    ) : null
                )}
                {runs.map((run) =>
                    run.length === 1 ? (
                        <circle
                            key={run[0]}
                            cx={x(run[0])}
                            cy={y(points[run[0]].online!)}
                            r={4}
                            fill={'rgb(var(--hydra-accent-500))'}
                        />
                    ) : (
                        <g key={run[0]}>
                            <path
                                d={`M${x(run[0])},${y(0)} ${run
                                    .map((i) => `L${x(i)},${y(points[i].online!)}`)
                                    .join(' ')} L${x(run[run.length - 1])},${y(0)} Z`}
                                fill={'rgb(var(--hydra-accent-500) / 0.1)'}
                            />
                            <path
                                d={run.map((i, n) => `${n ? 'L' : 'M'}${x(i)},${y(points[i].online!)}`).join(' ')}
                                fill={'none'}
                                stroke={'rgb(var(--hydra-accent-500))'}
                                strokeWidth={2}
                                strokeLinejoin={'round'}
                                strokeLinecap={'round'}
                            />
                        </g>
                    )
                )}
                {hovered && (
                    <g pointerEvents={'none'}>
                        <line
                            x1={x(hover!)}
                            x2={x(hover!)}
                            y1={MARGIN.top}
                            y2={MARGIN.top + plotHeight}
                            stroke={'var(--hydra-border-strong)'}
                            strokeWidth={1}
                        />
                        {hovered.online !== null && (
                            <circle
                                cx={x(hover!)}
                                cy={y(hovered.online)}
                                r={4}
                                fill={'rgb(var(--hydra-accent-500))'}
                                stroke={'var(--hydra-panel)'}
                                strokeWidth={2}
                            />
                        )}
                    </g>
                )}
            </svg>
            {hovered && (
                <div
                    className={
                        'pointer-events-none absolute top-0 z-10 rounded-md border border-gray-600 bg-gray-800 px-3 py-2 shadow-lg'
                    }
                    style={{
                        left: Math.min(Math.max(x(hover!) - 80, 0), width - 160),
                        width: 160,
                    }}
                >
                    <p className={'text-sm font-semibold text-gray-50'}>
                        {hovered.online === null
                            ? t('dashboard.players.no_answer')
                            : t('dashboard.players.count', { count: hovered.online })}
                    </p>
                    <p className={'text-xs text-gray-400'}>{fullTimeLabel(hovered.time, history.range)}</p>
                </div>
            )}
            {/* The values for screen readers. */}
            <table className={'sr-only'}>
                <caption>{t('dashboard.players.chart_label')}</caption>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.time.getTime()}>
                            <th scope={'row'}>{fullTimeLabel(point.time, history.range)}</th>
                            <td>{point.online === null ? t('dashboard.players.no_answer') : point.online}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};

const Stat = ({ label, value }: { label: string; value: string }) => (
    <div className={'rounded-md bg-gray-700/50 px-3 py-2'}>
        <p className={'text-xs text-gray-400'}>{label}</p>
        <p className={'text-lg font-semibold text-gray-50'}>{value}</p>
    </div>
);

export default ({ className }: { className?: string }) => {
    const { t } = useTranslation('client/server');
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [range, setRange] = useState<PlayerRange>('day');
    const { data } = useSWR<PlayerHistory>(['/players', uuid, range], () => getPlayerHistory(uuid, range), {
        refreshInterval: 60000,
    });

    const hasValues = !!data && data.points.some((point) => point.online !== null);

    return (
        <section className={`rounded-lg border border-gray-700 bg-gray-800/80 p-4 backdrop-blur ${className || ''}`}>
            <div className={'mb-3 flex flex-wrap items-center justify-between gap-3'}>
                <h3 className={'flex items-center gap-2 text-sm font-medium uppercase tracking-wide text-gray-300'}>
                    <Users className={'h-4 w-4 text-primary-400'} />
                    {t('dashboard.players.title')}
                </h3>
                <div className={'hydra-segmented'} role={'tablist'}>
                    {RANGES.map((item) => (
                        <button
                            key={item}
                            type={'button'}
                            role={'tab'}
                            aria-selected={range === item}
                            className={range === item ? 'active' : undefined}
                            onClick={() => setRange(item)}
                        >
                            {t(`dashboard.players.ranges.${item}`)}
                        </button>
                    ))}
                </div>
            </div>
            <div className={'mb-4 grid grid-cols-3 gap-3'}>
                <Stat
                    label={t('dashboard.players.now')}
                    value={
                        data?.current ? `${data.current.online} / ${data.current.max}` : t('dashboard.players.offline')
                    }
                />
                <Stat
                    label={t('dashboard.players.peak')}
                    value={data?.peak !== null && data?.peak !== undefined ? String(data.peak) : '–'}
                />
                <Stat
                    label={t('dashboard.players.average')}
                    value={
                        data?.average !== null && data?.average !== undefined
                            ? data.average.toLocaleString(i18n.language, { maximumFractionDigits: 1 })
                            : '–'
                    }
                />
            </div>
            {!data ? (
                <div className={'h-[200px] animate-pulse rounded bg-gray-700/40'} />
            ) : hasValues ? (
                <Chart history={data} />
            ) : (
                <p className={'flex h-[200px] items-center justify-center text-sm text-gray-400'}>
                    {t('dashboard.players.empty')}
                </p>
            )}
        </section>
    );
};
