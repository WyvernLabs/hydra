import React, { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FileCode, Loader2, Maximize, Music, ZoomIn } from 'lucide-react';
import getFilePreviewUrl from '@/api/server/files/getFilePreviewUrl';
import { httpErrorToHuman } from '@/api/http';
import { useIde } from '@/components/server/files/ide/context';
import { basename } from '@/components/server/files/ide/paths';
import { extensionOf } from '@/components/server/files/ide/fileTypes';
import { DownloadButton, StatusButton, ViewerMessage, ViewerProps } from '@/components/server/files/ide/viewers/common';

// Hydra: images, videos and music of the file manager. The node streams them
// through a preview link, so players can skip through large files.

export default ({ tab, active }: ViewerProps) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const [url, setUrl] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [mediaError, setMediaError] = useState(false);
    const [actualSize, setActualSize] = useState(false);
    const [dimensions, setDimensions] = useState<{ width: number; height: number } | null>(null);
    const [media, setMedia] = useState<HTMLMediaElement | null>(null);

    const load = () => {
        setError(null);
        setMediaError(false);
        getFilePreviewUrl(ide.uuid, tab.path)
            .then(setUrl)
            .catch((error) => setError(httpErrorToHuman(error)));
    };

    useEffect(load, [tab.path]);

    // Hidden tabs do not keep playing.
    useEffect(() => {
        if (!active) media?.pause();
    }, [active, media]);

    if (error || mediaError) {
        return (
            <ViewerMessage
                tab={tab}
                title={t('preview.failed')}
                message={error || t('preview.unsupported')}
                onRetry={load}
            />
        );
    }
    if (!url) {
        return (
            <div className={'flex h-full items-center justify-center'}>
                <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
            </div>
        );
    }

    return (
        <div className={'flex h-full flex-col'}>
            <div className={'flex min-h-0 flex-1 items-center justify-center overflow-auto p-4'}>
                {tab.kind === 'image' && (
                    <div
                        className={'rounded'}
                        style={{
                            backgroundImage:
                                'linear-gradient(45deg, rgb(255 255 255 / 0.06) 25%, transparent 25%, transparent 75%, rgb(255 255 255 / 0.06) 75%), linear-gradient(45deg, rgb(255 255 255 / 0.06) 25%, transparent 25%, transparent 75%, rgb(255 255 255 / 0.06) 75%)',
                            backgroundSize: '16px 16px',
                            backgroundPosition: '0 0, 8px 8px',
                        }}
                    >
                        <img
                            src={url}
                            alt={basename(tab.path)}
                            onLoad={(event) =>
                                setDimensions({
                                    width: event.currentTarget.naturalWidth,
                                    height: event.currentTarget.naturalHeight,
                                })
                            }
                            onError={() => setMediaError(true)}
                            onClick={() => setActualSize((v) => !v)}
                            className={
                                actualSize
                                    ? 'max-w-none cursor-zoom-out'
                                    : 'max-h-[70vh] max-w-full cursor-zoom-in object-contain'
                            }
                            style={
                                (dimensions?.width || 0) < 64 && !actualSize
                                    ? { imageRendering: 'pixelated', minWidth: 128 }
                                    : undefined
                            }
                        />
                    </div>
                )}
                {tab.kind === 'video' && (
                    <video
                        ref={setMedia}
                        src={url}
                        controls
                        preload={'metadata'}
                        onError={() => setMediaError(true)}
                        className={'max-h-full max-w-full rounded bg-black'}
                    />
                )}
                {tab.kind === 'audio' && (
                    <div
                        className={
                            'flex w-full max-w-lg flex-col items-center gap-4 rounded-lg border border-gray-700 bg-gray-900/60 p-6'
                        }
                    >
                        <div className={'flex h-20 w-20 items-center justify-center rounded-full bg-primary-500/20'}>
                            <Music className={'h-10 w-10 text-primary-300'} />
                        </div>
                        <p className={'max-w-full truncate text-sm text-gray-100'}>{basename(tab.path)}</p>
                        <audio
                            ref={setMedia}
                            src={url}
                            controls
                            preload={'metadata'}
                            onError={() => setMediaError(true)}
                            className={'w-full'}
                        />
                    </div>
                )}
            </div>
            <div
                className={
                    'flex flex-wrap items-center gap-3 border-t border-gray-700 px-2 py-1 text-2xs text-gray-400'
                }
            >
                <span className={'min-w-0 flex-1 truncate'}>
                    {dimensions ? t('preview.dimensions', dimensions) : basename(tab.path)}
                </span>
                {tab.kind === 'image' && (
                    <StatusButton
                        title={actualSize ? t('preview.fit') : t('preview.actual_size')}
                        onClick={() => setActualSize((v) => !v)}
                    >
                        {actualSize ? <Maximize className={'h-3.5 w-3.5'} /> : <ZoomIn className={'h-3.5 w-3.5'} />}
                    </StatusButton>
                )}
                {extensionOf(tab.path) === 'svg' && (
                    <StatusButton
                        title={t('preview.edit_text')}
                        onClick={() => ide.openFile(tab.path, undefined, true)}
                    >
                        <FileCode className={'h-3.5 w-3.5'} />
                    </StatusButton>
                )}
                <div className={'scale-90'}>
                    <DownloadButton path={tab.path} />
                </div>
            </div>
        </div>
    );
};
