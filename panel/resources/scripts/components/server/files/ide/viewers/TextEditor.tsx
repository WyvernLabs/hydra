import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Braces, Loader2, RotateCcw, Save, Search, WrapText } from 'lucide-react';
import classNames from 'classnames';
import CodeMirror from '@/lib/codemirror';
import modes from '@/modes';
import getFileContents from '@/api/server/files/getFileContents';
import saveFileContents from '@/api/server/files/saveFileContents';
import { httpErrorToHuman } from '@/api/http';
import { useIde } from '@/components/server/files/ide/context';
import { modeForFile } from '@/components/server/files/ide/fileTypes';
import { basename } from '@/components/server/files/ide/paths';
import SearchPanel from '@/components/server/files/ide/viewers/SearchPanel';
import {
    buildQuery,
    comparePos,
    findMatches,
    Match,
    matchOverlay,
    replacementFor,
    SearchOptions,
} from '@/components/server/files/ide/viewers/search';
import {
    ViewerProps,
    ViewerMessage,
    StatusButton,
    useSaveShortcut,
} from '@/components/server/files/ide/viewers/common';

// Hydra: text files of the file manager in CodeMirror, with syntax
// highlighting by file type and search & replace.

const WRAP_KEY = 'hydra:files:wrap';

const readWrap = (): boolean => {
    try {
        return localStorage.getItem(WRAP_KEY) !== '0';
    } catch {
        return true;
    }
};

export default ({ tab, active }: ViewerProps) => {
    const { t } = useTranslation('client/files');
    const ide = useIde();
    const host = useRef<HTMLDivElement>(null);
    const [editor, setEditor] = useState<CodeMirror.Editor | null>(null);
    const [content, setContent] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);
    const [saveError, setSaveError] = useState<string | null>(null);
    const [clean, setClean] = useState(0);
    const [mime, setMime] = useState(() => modeForFile(basename(tab.path)).mime);
    const [wrap, setWrap] = useState(readWrap);
    const [cursor, setCursor] = useState({ line: 1, ch: 1, selected: 0 });

    // Search state.
    const [searchOpen, setSearchOpen] = useState(false);
    const [showReplace, setShowReplace] = useState(false);
    const [focusKey, setFocusKey] = useState(0);
    const [query, setQuery] = useState('');
    const [replacement, setReplacement] = useState('');
    const [options, setOptions] = useState<SearchOptions>({ caseSensitive: false, wholeWord: false, regex: false });
    const [matches, setMatches] = useState<Match[]>([]);
    const [current, setCurrent] = useState(0);
    const [docVersion, setDocVersion] = useState(0);
    const currentMark = useRef<CodeMirror.TextMarker | null>(null);

    const readOnly = !ide.can.update || !ide.canWriteIn(tab.path);
    const re = useMemo(() => buildQuery(query, options), [query, options]);

    const load = useCallback(() => {
        setError(null);
        setContent(null);
        getFileContents(ide.uuid, tab.path)
            .then((text) => setContent(text))
            .catch((error) => setError(httpErrorToHuman(error)));
    }, [ide.uuid, tab.path]);

    useEffect(load, [ide.uuid]);

    // Create the editor once the file is loaded.
    useEffect(() => {
        if (content === null || !host.current) return;

        // Options of addons are missing in the types.
        const config: CodeMirror.EditorConfiguration & Record<string, unknown> = {
            value: content,
            mode: mime,
            theme: 'ayu-mirage',
            indentUnit: 4,
            tabSize: 4,
            indentWithTabs: false,
            smartIndent: true,
            lineWrapping: wrap,
            lineNumbers: true,
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            readOnly,
            spellcheck: false,
            autocorrect: false,
            autocapitalize: false,
            autoCloseBrackets: true,
            matchBrackets: true,
            styleActiveLine: true,
            scrollPastEnd: true,
        };
        const cm = CodeMirror(host.current, config);
        cm.setOption('extraKeys', {
            Tab: (instance: CodeMirror.Editor) =>
                instance.somethingSelected()
                    ? instance.execCommand('indentMore')
                    : instance.replaceSelection(' '.repeat(instance.getOption('indentUnit') || 4)),
            'Shift-Tab': 'indentLess',
            'Ctrl-/': 'toggleComment',
            'Cmd-/': 'toggleComment',
            // Handled by the editor area (own search bar), not by CodeMirror's dialogs.
            'Ctrl-F': false,
            'Cmd-F': false,
            'Ctrl-H': false,
            'Cmd-Alt-F': false,
            'Shift-Ctrl-F': false,
            'Shift-Ctrl-R': false,
            'Ctrl-G': false,
            'Shift-Ctrl-G': false,
            'Cmd-G': false,
            'Shift-Cmd-G': false,
            F3: false,
            'Shift-F3': false,
            Esc: false,
        });
        const gen = cm.getDoc().changeGeneration(true);
        setClean(gen);
        setEditor(cm);

        return () => {
            host.current?.replaceChildren();
            setEditor(null);
        };
    }, [content]);

    useEffect(() => {
        if (!editor) return;
        const onChange = () => {
            ide.setDirty(tab.id, !editor.getDoc().isClean(clean));
            setDocVersion((v) => v + 1);
        };
        const onCursor = () => {
            const pos = editor.getDoc().getCursor();
            setCursor({ line: pos.line + 1, ch: pos.ch + 1, selected: editor.getDoc().getSelection().length });
        };
        editor.on('changes', onChange);
        editor.on('cursorActivity', onCursor);
        onChange();

        return () => {
            editor.off('changes', onChange);
            editor.off('cursorActivity', onCursor);
        };
    }, [editor, clean, tab.id]);

    useEffect(() => {
        editor?.setOption('mode', mime);
    }, [editor, mime]);

    useEffect(() => {
        editor?.setOption('lineWrapping', wrap);
        try {
            localStorage.setItem(WRAP_KEY, wrap ? '1' : '0');
        } catch {
            // Only a convenience.
        }
    }, [editor, wrap]);

    useEffect(() => {
        editor?.setOption('readOnly', readOnly);
    }, [editor, readOnly]);

    // A hidden editor does not measure itself, so refresh it when shown.
    useEffect(() => {
        if (active && editor) {
            editor.refresh();
            editor.focus();
        }
    }, [active, editor]);

    const save = useCallback(() => {
        if (!editor || readOnly || saving) return;
        setSaving(true);
        setSaveError(null);
        const gen = editor.getDoc().changeGeneration(true);
        saveFileContents(ide.uuid, tab.path, editor.getValue())
            .then(() => {
                setClean(gen);
                ide.setDirty(tab.id, !editor.getDoc().isClean(gen));
                ide.refresh(tab.path.slice(0, tab.path.lastIndexOf('/')) || '/');
            })
            .catch((error) => setSaveError(httpErrorToHuman(error)))
            .then(() => setSaving(false));
    }, [editor, readOnly, saving, tab.path, tab.id]);

    // ---- search -------------------------------------------------------------

    useEffect(() => {
        if (!editor || !searchOpen || !re || re === 'invalid') {
            setMatches([]);
            return;
        }
        const overlay = matchOverlay(new RegExp(re.source, re.flags));
        editor.addOverlay(overlay);
        const timeout = setTimeout(() => setMatches(findMatches(editor.getDoc(), re)), docVersion ? 120 : 0);

        return () => {
            clearTimeout(timeout);
            editor.removeOverlay(overlay);
        };
    }, [editor, searchOpen, re, docVersion]);

    const markCurrent = (match: Match | null) => {
        currentMark.current?.clear();
        currentMark.current = null;
        if (editor && match) {
            currentMark.current = editor.getDoc().markText(match.from, match.to, { className: 'hydra-match-current' });
        }
    };

    useEffect(() => {
        if (!editor || matches.length === 0) {
            setCurrent(0);
            markCurrent(null);
            return;
        }
        // The match at or after the cursor is the current one.
        const from = editor.getDoc().getCursor('from');
        const index = matches.findIndex((m) => comparePos(m.to, from) > 0);
        const i = index === -1 ? 0 : index;
        setCurrent(i + 1);
        markCurrent(matches[i]);
    }, [matches]);

    useEffect(() => {
        if (!searchOpen) markCurrent(null);
    }, [searchOpen]);

    const go = (direction: 1 | -1) => {
        if (!editor || matches.length === 0) return;
        const doc = editor.getDoc();
        let index: number;
        if (direction === 1) {
            const head = doc.getCursor('to');
            index = matches.findIndex(
                (m) => comparePos(m.from, head) >= 0 && comparePos(m.from, doc.getCursor('from')) !== 0
            );
            if (index === -1) index = 0;
        } else {
            const head = doc.getCursor('from');
            index = -1;
            for (let i = matches.length - 1; i >= 0; i--) {
                if (comparePos(matches[i].from, head) < 0) {
                    index = i;
                    break;
                }
            }
            if (index === -1) index = matches.length - 1;
        }
        const match = matches[index];
        doc.setSelection(match.from, match.to);
        editor.scrollIntoView({ from: match.from, to: match.to }, 80);
        setCurrent(index + 1);
        markCurrent(match);
    };

    const replaceOne = () => {
        if (!editor || readOnly || !re || re === 'invalid' || matches.length === 0) return;
        const doc = editor.getDoc();
        const from = doc.getCursor('from');
        const to = doc.getCursor('to');
        const match = matches.find((m) => comparePos(m.from, from) === 0 && comparePos(m.to, to) === 0);
        if (!match) {
            go(1);
            return;
        }
        doc.replaceRange(
            replacementFor(doc.getRange(match.from, match.to), re, replacement, options),
            match.from,
            match.to,
            '+replace'
        );
        // Matches are recounted after the change; continue after the replacement.
        setTimeout(() => go(1), 150);
    };

    const replaceAll = () => {
        if (!editor || readOnly || !re || re === 'invalid' || matches.length === 0) return;
        const doc = editor.getDoc();
        editor.operation(() => {
            [...matches].reverse().forEach((m) => {
                doc.replaceRange(
                    replacementFor(doc.getRange(m.from, m.to), re, replacement, options),
                    m.from,
                    m.to,
                    '+replace'
                );
            });
        });
    };

    const openSearch = (replace: boolean) => {
        const selected = editor?.getDoc().getSelection();
        if (selected && !selected.includes('\n')) setQuery(selected);
        setSearchOpen(true);
        if (replace && !readOnly) setShowReplace(true);
        setFocusKey((key) => key + 1);
    };

    // Shortcuts work in the whole editor area, not only in CodeMirror, and
    // keep the browser from opening its own search.
    const onKeyDown = (event: React.KeyboardEvent) => {
        const mod = event.ctrlKey || event.metaKey;
        const key = event.key.toLowerCase();
        if (mod && key === 'f') {
            event.preventDefault();
            openSearch(false);
        } else if ((mod && key === 'h') || (event.metaKey && event.altKey && key === 'f')) {
            event.preventDefault();
            openSearch(true);
        } else if (event.key === 'F3' && searchOpen) {
            event.preventDefault();
            go(event.shiftKey ? -1 : 1);
        } else if (event.key === 'Escape' && searchOpen) {
            event.preventDefault();
            setSearchOpen(false);
            editor?.focus();
        }
    };

    useSaveShortcut(active, save);

    const formatJson = () => {
        if (!editor) return;
        try {
            editor.setValue(JSON.stringify(JSON.parse(editor.getValue()), null, 4) + '\n');
        } catch (error) {
            setSaveError(t('editor.invalid_json', { error: (error as Error).message }));
        }
    };

    if (error) {
        return <ViewerMessage tab={tab} title={t('editor.load_failed')} message={error} onRetry={load} />;
    }

    const mode = modes.find((m) => m.mime === mime);

    return (
        <div className={'flex h-full flex-col'} onKeyDown={onKeyDown}>
            <div className={'hydra-ide-editor relative min-h-0 flex-1'}>
                {content === null && (
                    <div className={'absolute inset-0 flex items-center justify-center'}>
                        <Loader2 className={'h-6 w-6 animate-spin text-gray-400'} />
                    </div>
                )}
                <div ref={host} className={'h-full'} />
                {searchOpen && (
                    <SearchPanel
                        query={query}
                        replacement={replacement}
                        options={options}
                        showReplace={showReplace}
                        canReplace={!readOnly}
                        invalid={re === 'invalid'}
                        count={matches.length}
                        current={current}
                        focusKey={focusKey}
                        onQuery={setQuery}
                        onReplacement={setReplacement}
                        onOptions={setOptions}
                        onToggleReplace={() => setShowReplace((v) => !v)}
                        onNext={() => go(1)}
                        onPrevious={() => go(-1)}
                        onReplace={replaceOne}
                        onReplaceAll={replaceAll}
                        onClose={() => {
                            setSearchOpen(false);
                            editor?.focus();
                        }}
                    />
                )}
            </div>
            <div
                className={
                    'flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-gray-700 px-2 py-1 text-2xs text-gray-400'
                }
            >
                {saveError ? (
                    <span className={'min-w-0 flex-1 truncate text-red-400'} title={saveError}>
                        {saveError}
                    </span>
                ) : (
                    <span className={'min-w-0 flex-1 truncate'}>
                        {readOnly ? t('editor.read_only') : tab.dirty ? t('editor.unsaved') : t('editor.saved')}
                    </span>
                )}
                <span>
                    {t('editor.position', { line: cursor.line, column: cursor.ch })}
                    {cursor.selected > 0 && ` (${t('editor.selected', { count: cursor.selected })})`}
                </span>
                <select
                    value={mime}
                    onChange={(event) => setMime(event.currentTarget.value)}
                    aria-label={t('editor.language')}
                    className={'rounded border-0 bg-transparent py-0 pl-1 pr-6 text-2xs text-gray-300 focus:ring-0'}
                >
                    {!mode && <option value={mime}>{mime}</option>}
                    {modes.map((m) => (
                        <option key={`${m.name}_${m.mime}`} value={m.mime} className={'bg-gray-800'}>
                            {m.name}
                        </option>
                    ))}
                </select>
                <StatusButton title={t('editor.search')} onClick={() => openSearch(false)}>
                    <Search className={'h-3.5 w-3.5'} />
                </StatusButton>
                <StatusButton title={t('editor.wrap')} active={wrap} onClick={() => setWrap((v) => !v)}>
                    <WrapText className={'h-3.5 w-3.5'} />
                </StatusButton>
                {mode?.name === 'JSON' && !readOnly && (
                    <StatusButton title={t('editor.format_json')} onClick={formatJson}>
                        <Braces className={'h-3.5 w-3.5'} />
                    </StatusButton>
                )}
                <StatusButton
                    title={t('editor.reload')}
                    onClick={() => {
                        if (!tab.dirty || window.confirm(t('editor.reload_confirm'))) {
                            ide.setDirty(tab.id, false);
                            load();
                        }
                    }}
                >
                    <RotateCcw className={'h-3.5 w-3.5'} />
                </StatusButton>
                {!readOnly && (
                    <button
                        type={'button'}
                        onClick={save}
                        disabled={saving || !tab.dirty}
                        className={classNames(
                            'flex items-center gap-1 rounded px-2 py-0.5 text-xs font-medium',
                            tab.dirty ? 'bg-primary-500 text-white hover:bg-primary-400' : 'bg-gray-700 text-gray-400',
                            'disabled:cursor-not-allowed'
                        )}
                    >
                        {saving ? (
                            <Loader2 className={'h-3.5 w-3.5 animate-spin'} />
                        ) : (
                            <Save className={'h-3.5 w-3.5'} />
                        )}
                        {t('editor.save')}
                    </button>
                )}
            </div>
        </div>
    );
};
