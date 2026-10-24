import React, { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import classNames from 'classnames';
import { Code2, Loader2, X } from 'lucide-react';
import { EditorTab, useIde } from '@/components/server/files/ide/context';
import { basename } from '@/components/server/files/ide/paths';
import { ViewerMessage, ViewerProps } from '@/components/server/files/ide/viewers/common';
import TextEditor from '@/components/server/files/ide/viewers/TextEditor';
import MediaViewer from '@/components/server/files/ide/viewers/MediaViewer';
import useFileTransfers from '@/plugins/useFileTransfers';

// Hydra: right column of the file manager, the opened files as tabs. Every
// tab stays mounted while it is open, so switching keeps undo history,
// scroll position and unsaved changes.

const NbtEditor = lazy(() => import('@/components/server/files/ide/viewers/NbtEditor'));
const SqliteEditor = lazy(() => import('@/components/server/files/ide/viewers/SqliteEditor'));

const OtherFile = ({ tab }: ViewerProps) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const transfers = useFileTransfers();
    const directory = tab.path.slice(0, tab.path.lastIndexOf('/')) || '/';

    return (
        <ViewerMessage
            tab={tab}
            title={tab.kind === 'archive' ? t('viewer.archive') : t('viewer.binary')}
            message={tab.kind === 'archive' ? t('viewer.archive_help') : t('viewer.binary_help')}
        >
            {tab.kind === 'archive' && ide.can.create && ide.canWriteIn(directory) && (
                <button
                    type={'button'}
                    onClick={() =>
                        transfers
                            .unarchive(directory, basename(tab.path))
                            .then(() => ide.refresh(directory))
                            .catch(() => undefined)
                    }
                    className={'rounded bg-primary-500 px-3 py-1.5 text-sm text-white hover:bg-primary-400'}
                >
                    {t('actions.unarchive')}
                </button>
            )}
            {tab.kind === 'binary' && (
                <button
                    type={'button'}
                    onClick={() => ide.openFile(tab.path, undefined, true)}
                    className={'rounded bg-gray-700 px-3 py-1.5 text-sm text-gray-100 hover:bg-gray-600'}
                >
                    {t('preview.edit_text')}
                </button>
            )}
        </ViewerMessage>
    );
};

const Viewer = (props: ViewerProps) => {
    if (props.tab.forceText) return <TextEditor {...props} />;

    switch (props.tab.kind) {
        case 'text':
            return <TextEditor {...props} />;
        case 'image':
        case 'video':
        case 'audio':
            return <MediaViewer {...props} />;
        case 'nbt':
            return <NbtEditor {...props} />;
        case 'sqlite':
            return <SqliteEditor {...props} />;
        default:
            return <OtherFile {...props} />;
    }
};

const Tab = ({ tab, active }: { tab: EditorTab; active: boolean }) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();

    return (
        <div
            role={'tab'}
            aria-selected={active}
            title={tab.path}
            onClick={() => ide.setActive(tab.id)}
            onAuxClick={(event) => {
                if (event.button === 1) ide.closeTab(tab.id);
            }}
            className={classNames(
                'group flex max-w-[14rem] shrink-0 cursor-pointer items-center gap-1.5 border-r border-gray-700 py-1.5 pl-3 pr-1.5 text-xs',
                active
                    ? 'bg-gray-800 text-gray-50 shadow-[inset_0_2px_0] shadow-primary-500'
                    : 'bg-gray-900/40 text-gray-400 hover:text-gray-200'
            )}
        >
            <span className={'truncate'}>{basename(tab.path)}</span>
            <button
                type={'button'}
                aria-label={t('tabs.close')}
                onClick={(event) => {
                    event.stopPropagation();
                    ide.closeTab(tab.id);
                }}
                className={'flex h-4 w-4 shrink-0 items-center justify-center rounded hover:bg-gray-600'}
            >
                {tab.dirty ? <span className={'h-2 w-2 rounded-full bg-gray-300 group-hover:hidden'} /> : null}
                <X className={classNames('h-3 w-3', tab.dirty && 'hidden group-hover:block')} />
            </button>
        </div>
    );
};

export default () => {
    const { t } = useTranslation('client/files');
    const ide = useIde();

    if (ide.tabs.length === 0) {
        return (
            <div className={'flex h-full flex-col items-center justify-center gap-3 p-6 text-center text-gray-500'}>
                <Code2 className={'h-12 w-12'} />
                <p className={'text-sm'}>{t('tabs.empty')}</p>
                <p className={'max-w-sm text-xs'}>{t('tabs.empty_help')}</p>
            </div>
        );
    }

    return (
        <div className={'flex h-full flex-col'}>
            <div role={'tablist'} className={'flex shrink-0 overflow-x-auto border-b border-gray-700 bg-gray-900/40'}>
                {ide.tabs.map((tab) => (
                    <Tab key={tab.id} tab={tab} active={tab.id === ide.active} />
                ))}
            </div>
            <div className={'relative min-h-0 flex-1'}>
                {ide.tabs.map((tab) => (
                    <div
                        key={tab.id}
                        role={'tabpanel'}
                        className={classNames('absolute inset-0', tab.id !== ide.active && 'hidden')}
                    >
                        <Suspense
                            fallback={
                                <div className={'flex h-full items-center justify-center'}>
                                    <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                                </div>
                            }
                        >
                            <Viewer tab={tab} active={tab.id === ide.active} />
                        </Suspense>
                    </div>
                ))}
            </div>
        </div>
    );
};
