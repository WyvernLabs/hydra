import React, { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Download, FileWarning, RotateCcw } from 'lucide-react';
import { EditorTab, useIde } from '@/components/server/files/ide/context';
import { basename } from '@/components/server/files/ide/paths';
import useFileTransfers from '@/plugins/useFileTransfers';

// Hydra: parts shared by the viewers of the file manager.

export interface ViewerProps {
    tab: EditorTab;
    // Whether the tab is the visible one; hidden tabs stay mounted.
    active: boolean;
}

/**
 * Ctrl+S saves the visible tab, wherever the focus is on the page.
 */
export const useSaveShortcut = (active: boolean, save: () => void) => {
    const latest = useRef(save);
    latest.current = save;

    useEffect(() => {
        if (!active) return;
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 's') {
                event.preventDefault();
                latest.current();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [active]);
};

export const StatusButton = ({
    title,
    active,
    onClick,
    disabled,
    children,
}: {
    title: string;
    active?: boolean;
    onClick: () => void;
    disabled?: boolean;
    children: React.ReactNode;
}) => (
    <button
        type={'button'}
        title={title}
        aria-label={title}
        aria-pressed={active}
        onClick={onClick}
        disabled={disabled}
        className={classNames(
            'flex h-5 w-5 items-center justify-center rounded disabled:opacity-40',
            active ? 'text-primary-300' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-100'
        )}
    >
        {children}
    </button>
);

/**
 * Download button for the file of a tab.
 */
export const DownloadButton = ({ path, size }: { path: string; size?: number }) => {
    const { t } = useTranslation('client/files');
    const transfers = useFileTransfers();

    return (
        <button
            type={'button'}
            onClick={() => transfers.download(path, basename(path), size || 0).catch(() => undefined)}
            className={
                'flex items-center gap-2 rounded bg-gray-700 px-3 py-1.5 text-sm text-gray-100 hover:bg-gray-600'
            }
        >
            <Download className={'h-4 w-4'} />
            {t('actions.download')}
        </button>
    );
};

/**
 * A file that cannot be shown, e.g. because loading failed.
 */
export const ViewerMessage = ({
    tab,
    title,
    message,
    onRetry,
    children,
}: {
    tab: EditorTab;
    title: string;
    message?: string;
    onRetry?: () => void;
    children?: React.ReactNode;
}) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();

    return (
        <div className={'flex h-full flex-col items-center justify-center gap-3 p-6 text-center'}>
            <FileWarning className={'h-10 w-10 text-gray-500'} />
            <div>
                <p className={'text-sm font-medium text-gray-100'}>{title}</p>
                {message && <p className={'mt-1 max-w-md text-xs text-gray-400'}>{message}</p>}
            </div>
            <div className={'flex flex-wrap justify-center gap-2'}>
                {onRetry && (
                    <button
                        type={'button'}
                        onClick={onRetry}
                        className={
                            'flex items-center gap-2 rounded bg-gray-700 px-3 py-1.5 text-sm text-gray-100 hover:bg-gray-600'
                        }
                    >
                        <RotateCcw className={'h-4 w-4'} />
                        {t('editor.retry')}
                    </button>
                )}
                {children}
                {ide.can.read && <DownloadButton path={tab.path} />}
            </div>
        </div>
    );
};
