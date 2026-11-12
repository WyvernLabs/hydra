import React from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Ban, Check } from 'lucide-react';
import { COLORS, contrastText } from '@/components/server/board/context';

// Hydra: color swatches of the board with a field for any other color.

interface Props {
    value: string | null;
    onChange: (color: string | null) => void;
    // Offers "no color" (columns); tags always have one.
    allowNone?: boolean;
    disabled?: boolean;
}

export default ({ value, onChange, allowNone, disabled }: Props) => {
    const { t } = useTranslation('client/board');
    const custom = value && !COLORS.includes(value.toLowerCase());

    return (
        <div className={'flex flex-wrap items-center gap-1.5'}>
            {allowNone && (
                <button
                    type={'button'}
                    disabled={disabled}
                    title={t('color.none')}
                    aria-label={t('color.none')}
                    onClick={() => onChange(null)}
                    className={classNames(
                        'flex h-6 w-6 items-center justify-center rounded-full border border-gray-500 text-gray-400',
                        value === null && 'ring-2 ring-white ring-offset-2 ring-offset-gray-800'
                    )}
                >
                    <Ban className={'h-3.5 w-3.5'} />
                </button>
            )}
            {COLORS.map((color) => (
                <button
                    key={color}
                    type={'button'}
                    disabled={disabled}
                    aria-label={color}
                    onClick={() => onChange(color)}
                    style={{ backgroundColor: color, color: contrastText(color) }}
                    className={classNames(
                        'flex h-6 w-6 items-center justify-center rounded-full',
                        value?.toLowerCase() === color && 'ring-2 ring-white ring-offset-2 ring-offset-gray-800'
                    )}
                >
                    {value?.toLowerCase() === color && <Check className={'h-3.5 w-3.5'} />}
                </button>
            ))}
            <label
                title={t('color.custom')}
                className={classNames(
                    'relative h-6 w-6 cursor-pointer overflow-hidden rounded-full border border-gray-500',
                    custom && 'ring-2 ring-white ring-offset-2 ring-offset-gray-800'
                )}
                style={{
                    background: custom
                        ? value!
                        : 'conic-gradient(#ef4444, #eab308, #22c55e, #06b6d4, #3b82f6, #a855f7, #ef4444)',
                }}
            >
                <input
                    type={'color'}
                    disabled={disabled}
                    value={value || '#3b82f6'}
                    onChange={(event) => onChange(event.currentTarget.value)}
                    className={'absolute inset-0 h-full w-full cursor-pointer opacity-0'}
                    aria-label={t('color.custom')}
                />
            </label>
        </div>
    );
};
