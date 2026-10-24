import React from 'react';

// Hydra: a ring that shows how much of a limit is used, like the usage of the
// nodes in the admin area. Accent color, yellow from 75 % and red from 90 %.

const RADIUS = 42;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

interface Props {
    title: string;
    icon: React.ComponentType<{ className?: string }>;
    // Share of the limit in percent, fills the ring.
    fill: number;
    // Text in the middle of the ring.
    value: string;
    // Text below the title, e.g. "1.20 GB of 4.00 GB".
    detail: string;
}

export default ({ title, icon: Icon, fill, value, detail }: Props) => {
    const percent = Math.max(0, Math.min(100, fill));
    const color = percent >= 90 ? 'rgb(239 68 68)' : percent >= 75 ? 'rgb(234 179 8)' : 'rgb(var(--hydra-accent-500))';

    return (
        <div
            className={
                'flex h-full items-center gap-4 rounded-lg border border-gray-700 bg-gray-800/80 p-3 backdrop-blur'
            }
        >
            <div className={'relative h-20 w-20 shrink-0'}>
                <svg viewBox={'0 0 100 100'} className={'h-full w-full -rotate-90'} aria-hidden>
                    <circle cx={50} cy={50} r={RADIUS} fill={'none'} strokeWidth={9} className={'stroke-gray-700'} />
                    <circle
                        cx={50}
                        cy={50}
                        r={RADIUS}
                        fill={'none'}
                        strokeWidth={9}
                        strokeLinecap={'round'}
                        stroke={color}
                        strokeDasharray={`${(CIRCUMFERENCE * percent) / 100} ${CIRCUMFERENCE}`}
                        style={{ transition: 'stroke-dasharray .6s ease, stroke .3s' }}
                    />
                </svg>
                <span
                    className={
                        'absolute inset-0 flex items-center justify-center font-mono text-sm font-semibold text-gray-50'
                    }
                >
                    {value}
                </span>
            </div>
            <div className={'min-w-0'}>
                <p className={'flex items-center gap-1.5 text-sm font-medium text-gray-100'}>
                    <Icon className={'h-4 w-4 shrink-0 text-gray-400'} />
                    {title}
                </p>
                <p className={'mt-1 text-xs text-gray-400'}>{detail}</p>
            </div>
        </div>
    );
};
