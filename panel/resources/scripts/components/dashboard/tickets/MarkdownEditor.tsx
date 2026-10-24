import React, { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Bold, Code, ImagePlus, Italic, Link as LinkIcon, List, Quote } from 'lucide-react';
import { useStoreState } from '@/state/hooks';
import { previewTicketMarkdown, uploadTicketImage } from '@/api/account/tickets';
import { httpErrorToHuman } from '@/api/http';

// Hydra: Markdown editor of tickets. Formatting buttons, images by button,
// paste or drag and drop (uploaded right away and inserted as Markdown), and a
// preview rendered by the panel.

interface Props {
    value: string;
    onChange: (value: string) => void;
    onError: (message: string) => void;
    disabled?: boolean;
}

const IMAGE_TYPES = /^image\/(png|jpeg|gif|webp)$/;

export default ({ value, onChange, onError, disabled }: Props) => {
    const { t } = useTranslation('client/tickets');
    const uploadLimit = useStoreState((state) => state.settings.data!.tickets.uploadLimit);
    const textarea = useRef<HTMLTextAreaElement>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);
    // The latest text, for uploads that finish after more was typed.
    const current = useRef(value);
    current.current = value;

    const update = (text: string, selectionStart?: number, selectionEnd?: number) => {
        current.current = text;
        onChange(text);
        if (selectionStart !== undefined) {
            requestAnimationFrame(() => {
                textarea.current?.focus();
                textarea.current?.setSelectionRange(selectionStart, selectionEnd ?? selectionStart);
            });
        }
    };

    const selection = () => {
        const el = textarea.current!;
        return {
            start: el.selectionStart,
            end: el.selectionEnd,
            text: value.substring(el.selectionStart, el.selectionEnd),
        };
    };

    const wrap = (before: string, after: string, placeholder: string) => {
        const { start, end, text } = selection();
        const inner = text || placeholder;
        update(
            value.substring(0, start) + before + inner + after + value.substring(end),
            start + before.length,
            start + before.length + inner.length
        );
    };

    const prefixLines = (prefix: string) => {
        const { end } = selection();
        const start = value.lastIndexOf('\n', textarea.current!.selectionStart - 1) + 1;
        const lines = value
            .substring(start, end)
            .split('\n')
            .map((line) => prefix + line)
            .join('\n');
        update(value.substring(0, start) + lines + value.substring(end), start + lines.length);
    };

    const insert = (text: string) => {
        const { start, end } = selection();
        const before = value.substring(0, start);
        const block = (before.length > 0 && !before.endsWith('\n') ? '\n' : '') + text + '\n';
        update(before + block + value.substring(end), start + block.length);
    };

    const upload = (file: File) => {
        if (file.size > uploadLimit * 1024 * 1024) {
            onError(t('editor.too_large', { size: uploadLimit }));
            return;
        }

        const marker = `![${t('editor.uploading')} ${file.name}]()`;
        insert(marker);

        uploadTicketImage(file)
            .then(({ url, name }) =>
                update(current.current.replace(marker, `![${name.replace(/[[\]]/g, '')}](${url})`))
            )
            .catch((error) => {
                update(current.current.replace(`${marker}\n`, '').replace(marker, ''));
                onError(`${t('editor.failed')} ${httpErrorToHuman(error)}`);
            });
    };

    const uploadAll = (files: FileList | null) => {
        Array.from(files || [])
            .filter((file) => IMAGE_TYPES.test(file.type))
            .forEach(upload);
    };

    const showPreview = () => {
        setPreview('');
        previewTicketMarkdown(value)
            .then((html) => setPreview(html))
            .catch((error) => {
                setPreview(null);
                onError(httpErrorToHuman(error));
            });
    };

    const tools: Array<[string, React.ComponentType<{ className?: string }>, () => void]> = [
        ['bold', Bold, () => wrap('**', '**', 'text')],
        ['italic', Italic, () => wrap('*', '*', 'text')],
        ['code', Code, () => (selection().text.includes('\n') ? wrap('```\n', '\n```', '') : wrap('`', '`', 'code'))],
        ['quote', Quote, () => prefixLines('> ')],
        ['list', List, () => prefixLines('- ')],
        ['link', LinkIcon, () => wrap('[', '](https://)', 'text')],
        ['image', ImagePlus, () => fileInput.current?.click()],
    ];

    // Spoilers open on click, like on Discord.
    const reveal = (event: React.MouseEvent) => {
        const target = event.target as HTMLElement;
        if (target.classList.contains('md-spoiler')) target.classList.add('is-revealed');
    };

    return (
        <div>
            <div className={'mb-2 flex flex-wrap items-center gap-1'}>
                {tools.map(([key, Icon, action]) => (
                    <button
                        key={key}
                        type={'button'}
                        title={t(`editor.${key}`)}
                        disabled={disabled || preview !== null}
                        onClick={action}
                        className={
                            'rounded p-1.5 text-gray-300 hover:bg-gray-700 hover:text-gray-100 disabled:cursor-not-allowed disabled:opacity-40'
                        }
                    >
                        <Icon className={'h-4 w-4'} />
                    </button>
                ))}
                <div className={'ml-auto flex overflow-hidden rounded border border-gray-600 text-xs'}>
                    <button
                        type={'button'}
                        onClick={() => setPreview(null)}
                        className={`px-3 py-1 ${preview === null ? 'bg-gray-600 text-gray-50' : 'text-gray-300'}`}
                    >
                        {t('editor.write')}
                    </button>
                    <button
                        type={'button'}
                        onClick={showPreview}
                        className={`px-3 py-1 ${preview !== null ? 'bg-gray-600 text-gray-50' : 'text-gray-300'}`}
                    >
                        {t('editor.preview')}
                    </button>
                </div>
                <input
                    ref={fileInput}
                    type={'file'}
                    accept={'image/png,image/jpeg,image/gif,image/webp'}
                    multiple
                    hidden
                    onChange={(event) => {
                        uploadAll(event.currentTarget.files);
                        event.currentTarget.value = '';
                    }}
                />
            </div>
            {preview === null ? (
                <textarea
                    ref={textarea}
                    value={value}
                    disabled={disabled}
                    maxLength={20000}
                    rows={8}
                    placeholder={t('editor.placeholder')}
                    onChange={(event) => update(event.currentTarget.value)}
                    onPaste={(event) => {
                        if (event.clipboardData.files.length > 0) {
                            event.preventDefault();
                            uploadAll(event.clipboardData.files);
                        }
                    }}
                    onDragOver={(event) => event.preventDefault()}
                    onDrop={(event) => {
                        if (event.dataTransfer.files.length > 0) {
                            event.preventDefault();
                            uploadAll(event.dataTransfer.files);
                        }
                    }}
                    className={
                        'block min-h-[10rem] w-full resize-y rounded border-2 border-gray-600 bg-gray-700 p-3 font-mono text-sm text-gray-100 outline-none focus:border-primary-400'
                    }
                />
            ) : (
                <div
                    className={
                        'hydra-markdown min-h-[10rem] rounded border-2 border-dashed border-gray-600 p-3 text-sm'
                    }
                    onClick={reveal}
                    dangerouslySetInnerHTML={{
                        // Rendered by the panel without raw HTML.
                        __html: preview || `<p>${t('editor.empty')}</p>`,
                    }}
                />
            )}
            <p className={'mt-1 text-xs text-gray-400'}>{t('editor.hint', { size: uploadLimit })}</p>
        </div>
    );
};
