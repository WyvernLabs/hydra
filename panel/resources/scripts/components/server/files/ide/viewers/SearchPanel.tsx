import React, { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import {
    ArrowDown,
    ArrowUp,
    CaseSensitive,
    ChevronRight,
    Regex,
    Replace,
    ReplaceAll,
    WholeWord,
    X,
} from 'lucide-react';
import { MAX_MATCHES, SearchOptions } from '@/components/server/files/ide/viewers/search';

// Hydra: search bar of the text editor (Ctrl+F, Ctrl+H for replace).

interface Props {
    query: string;
    replacement: string;
    options: SearchOptions;
    showReplace: boolean;
    canReplace: boolean;
    invalid: boolean;
    count: number;
    current: number;
    focusKey: number;
    onQuery: (value: string) => void;
    onReplacement: (value: string) => void;
    onOptions: (options: SearchOptions) => void;
    onToggleReplace: () => void;
    onNext: () => void;
    onPrevious: () => void;
    onReplace: () => void;
    onReplaceAll: () => void;
    onClose: () => void;
}

const Toggle = ({
    active,
    title,
    onClick,
    children,
}: {
    active: boolean;
    title: string;
    onClick: () => void;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        title={title}
        aria-pressed={active}
        onClick={onClick}
        className={classNames(
            'flex h-6 w-6 items-center justify-center rounded',
            active ? 'bg-primary-500/30 text-primary-200' : 'text-gray-400 hover:bg-gray-600 hover:text-gray-100'
        )}
    >
        {children}
    </button>
);

const IconButton = ({
    title,
    onClick,
    disabled,
    children,
}: {
    title: string;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        title={title}
        onClick={onClick}
        disabled={disabled}
        className={
            'flex h-6 w-6 items-center justify-center rounded text-gray-300 hover:bg-gray-600 hover:text-gray-50 disabled:opacity-40 disabled:hover:bg-transparent'
        }
    >
        {children}
    </button>
);

export default (props: Props) => {
    const { t } = useTranslation('client/files');
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => {
        input.current?.focus();
        input.current?.select();
    }, [props.focusKey]);

    const onKeyDown = (event: React.KeyboardEvent, replace: boolean) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            props.onClose();
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (replace) {
                event.ctrlKey || event.metaKey ? props.onReplaceAll() : props.onReplace();
            } else {
                event.shiftKey ? props.onPrevious() : props.onNext();
            }
        } else if (event.key === 'F3') {
            event.preventDefault();
            event.shiftKey ? props.onPrevious() : props.onNext();
        }
    };

    const counter = props.invalid
        ? t('search.invalid')
        : !props.query
        ? ''
        : props.count === 0
        ? t('search.none')
        : t('search.position', {
              current: props.current > 0 ? props.current : '?',
              total: props.count >= MAX_MATCHES ? `${MAX_MATCHES}+` : props.count,
          });

    return (
        <div
            className={
                'absolute right-4 top-2 z-20 flex w-[min(28rem,calc(100%-2rem))] gap-1 rounded-lg border border-gray-600 bg-gray-800 p-1.5 shadow-xl'
            }
        >
            {props.canReplace && (
                <button
                    type={'button'}
                    title={t('search.toggle_replace')}
                    onClick={props.onToggleReplace}
                    className={'flex w-5 items-start justify-center rounded pt-1 text-gray-400 hover:bg-gray-600'}
                >
                    <ChevronRight
                        className={classNames('h-4 w-4 transition-transform', props.showReplace && 'rotate-90')}
                    />
                </button>
            )}
            <div className={'flex min-w-0 flex-1 flex-col gap-1'}>
                <div className={'flex items-center gap-1'}>
                    <div
                        className={classNames(
                            'flex min-w-0 flex-1 items-center rounded border bg-gray-700 pr-1',
                            props.invalid ? 'border-red-500' : 'border-gray-600 focus-within:border-primary-400'
                        )}
                    >
                        <input
                            ref={input}
                            value={props.query}
                            onChange={(event) => props.onQuery(event.currentTarget.value)}
                            onKeyDown={(event) => onKeyDown(event, false)}
                            placeholder={t('search.find')}
                            aria-label={t('search.find')}
                            spellCheck={false}
                            className={'min-w-0 flex-1 bg-transparent px-2 py-1 text-xs text-gray-100 outline-none'}
                        />
                        <Toggle
                            active={props.options.caseSensitive}
                            title={t('search.case')}
                            onClick={() =>
                                props.onOptions({ ...props.options, caseSensitive: !props.options.caseSensitive })
                            }
                        >
                            <CaseSensitive className={'h-4 w-4'} />
                        </Toggle>
                        <Toggle
                            active={props.options.wholeWord}
                            title={t('search.word')}
                            onClick={() => props.onOptions({ ...props.options, wholeWord: !props.options.wholeWord })}
                        >
                            <WholeWord className={'h-4 w-4'} />
                        </Toggle>
                        <Toggle
                            active={props.options.regex}
                            title={t('search.regex')}
                            onClick={() => props.onOptions({ ...props.options, regex: !props.options.regex })}
                        >
                            <Regex className={'h-4 w-4'} />
                        </Toggle>
                    </div>
                    <span className={'w-20 shrink-0 truncate text-center text-2xs text-gray-400'}>{counter}</span>
                    <IconButton title={t('search.previous')} onClick={props.onPrevious} disabled={props.count === 0}>
                        <ArrowUp className={'h-4 w-4'} />
                    </IconButton>
                    <IconButton title={t('search.next')} onClick={props.onNext} disabled={props.count === 0}>
                        <ArrowDown className={'h-4 w-4'} />
                    </IconButton>
                    <IconButton title={t('search.close')} onClick={props.onClose}>
                        <X className={'h-4 w-4'} />
                    </IconButton>
                </div>
                {props.showReplace && props.canReplace && (
                    <div className={'flex items-center gap-1'}>
                        <input
                            value={props.replacement}
                            onChange={(event) => props.onReplacement(event.currentTarget.value)}
                            onKeyDown={(event) => onKeyDown(event, true)}
                            placeholder={t('search.replace')}
                            aria-label={t('search.replace')}
                            spellCheck={false}
                            className={
                                'min-w-0 flex-1 rounded border border-gray-600 bg-gray-700 px-2 py-1 text-xs text-gray-100 outline-none focus:border-primary-400'
                            }
                        />
                        <IconButton
                            title={t('search.replace_one')}
                            onClick={props.onReplace}
                            disabled={props.count === 0}
                        >
                            <Replace className={'h-4 w-4'} />
                        </IconButton>
                        <IconButton
                            title={t('search.replace_all')}
                            onClick={props.onReplaceAll}
                            disabled={props.count === 0}
                        >
                            <ReplaceAll className={'h-4 w-4'} />
                        </IconButton>
                    </div>
                )}
            </div>
        </div>
    );
};
