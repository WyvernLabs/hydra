import React, { useEffect, useMemo, useRef, useState } from 'react';
import { ITerminalOptions, Terminal } from 'xterm';
import { FitAddon } from 'xterm-addon-fit';
import { SearchAddon } from 'xterm-addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';
import { WebLinksAddon } from 'xterm-addon-web-links';
import { Unicode11Addon } from 'xterm-addon-unicode11';
import { ScrollDownHelperAddon } from '@/plugins/XtermScrollDownHelperAddon';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ServerContext } from '@/state/server';
import { usePermissions } from '@/plugins/usePermissions';
import { theme as th } from 'twin.macro';
import { hydraColor } from '@/lib/helpers';
import useEventListener from '@/plugins/useEventListener';
import { debounce } from 'debounce';
import { usePersistedState } from '@/plugins/usePersistedState';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import classNames from 'classnames';
import isEqual from 'react-fast-compare';
import { ChevronsRight as ChevronDoubleRightIcon, FileUp } from 'lucide-react';
import { useStoreState } from '@/state/hooks';
import uploadLogToMcLogs from '@/api/server/files/uploadLogToMcLogs';
import { httpErrorToHuman } from '@/api/http';
import { colorCodesToAnsi, fillPlaceholders } from '@/lib/consoleText';
import { ip } from '@/lib/formatters';

import 'xterm/css/xterm.css';
import styles from './style.module.css';
import { useTranslation } from 'react-i18next';

const theme = {
    background: hydraColor('black'),
    cursor: 'transparent',
    black: hydraColor('black'),
    red: '#E54B4B',
    green: '#9ECE58',
    yellow: '#FAED70',
    blue: '#396FE2',
    magenta: '#BB80B3',
    cyan: '#2DDAFD',
    white: '#d0d0d0',
    brightBlack: 'rgba(255, 255, 255, 0.2)',
    brightRed: '#FF5370',
    brightGreen: '#C3E88D',
    brightYellow: '#FFCB6B',
    brightBlue: '#82AAFF',
    brightMagenta: '#C792EA',
    brightCyan: '#89DDFF',
    brightWhite: '#ffffff',
    selection: hydraColor('accent-500', 0.35),
};

// Hydra: kinds of lines the console can be limited to.
type LineKind = 'warnings' | 'errors' | 'fatal';
const LINE_KINDS: LineKind[] = ['warnings', 'errors', 'fatal'];

// Lines kept to show them again when the filter changes.
const MAX_LINES = 5000;

// eslint-disable-next-line no-control-regex
const stripAnsi = (line: string) => line.replace(/\u001b\[[0-9;?]*[A-Za-z]/g, '');

/**
 * Hydra: kind of a console line by the usual log levels, null for others.
 */
const lineKind = (line: string): LineKind | null => {
    const text = stripAnsi(line);
    if (/\b(FATAL|CRITICAL)\b/i.test(text)) return 'fatal';
    if (/\b(ERROR|SEVERE)\b/i.test(text)) return 'errors';
    if (/\bWARN(ING)?\b/i.test(text)) return 'warnings';

    return null;
};

const terminalProps: ITerminalOptions = {
    disableStdin: true,
    cursorStyle: 'underline',
    allowTransparency: true,
    fontSize: 12,
    fontFamily: th('fontFamily.mono'),
    rows: 30,
    theme: theme,
};

export default () => {
    const settings = useStoreState((state) => state.settings.data!.console);
    // Hydra: the prefixes can be changed under Design > Console.
    const TERMINAL_PRELUDE = `\u001b[1m\u001b[33m${colorCodesToAnsi(settings.prefix)} \u001b[0m`;
    const { t } = useTranslation('client/server');
    const ref = useRef<HTMLDivElement>(null);
    const terminal = useMemo(() => new Terminal({ ...terminalProps }), []);
    const fitAddon = new FitAddon();
    const searchAddon = new SearchAddon();
    const searchBar = new SearchBarAddon({ searchAddon });
    const webLinksAddon = new WebLinksAddon();
    const unicode11Addon = new Unicode11Addon();
    const scrollDownHelperAddon = new ScrollDownHelperAddon();
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const [canSendCommands, canReadFiles] = usePermissions(['control.console', 'file.read-content']);
    const serverId = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const [filter, setFilter] = usePersistedState<LineKind[]>(`${serverId}:console_filter`, []);
    const [uploading, setUploading] = useState(false);
    const lines = useRef<string[]>([]);
    const activeFilter = useRef<LineKind[]>(filter || []);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    // Hydra: values for the placeholders of the message while offline.
    const placeholders = ServerContext.useStoreState((state) => {
        const server = state.server.data!;
        const allocation = server.allocations.find((item) => item.isDefault);

        return {
            name: server.name,
            id: server.id,
            node: server.node,
            address: allocation ? `${allocation.alias || ip(allocation.ip)}:${allocation.port}` : '',
        };
    }, isEqual);
    const [history, setHistory] = usePersistedState<string[]>(`${serverId}:command_history`, []);
    const [historyIndex, setHistoryIndex] = useState(-1);
    // SearchBarAddon has hardcoded z-index: 999 :(
    const zIndex = `
    .xterm-search-bar__addon {
        z-index: 10;
    }`;

    const shown = (line: string) => {
        const kind = activeFilter.current.length > 0 ? lineKind(line) : null;

        return activeFilter.current.length === 0 || (kind !== null && activeFilter.current.includes(kind));
    };

    // Hydra: every line goes through here, so it can be shown again when the filter changes.
    const writeLine = (line: string) => {
        lines.current.push(line);
        if (lines.current.length > MAX_LINES) lines.current.splice(0, lines.current.length - MAX_LINES);
        if (shown(line)) terminal.writeln(line);
    };

    // Hydra: Wings puts "[… Daemon]:" in front of its messages, which can be replaced.
    const replaceDaemonPrefix = (line: string) =>
        settings.daemonPrefix
            ? line.replace(
                  // eslint-disable-next-line no-control-regex
                  /^((?:\u001b\[[0-9;]*m)*)\[[^\]\n]{1,40} Daemon\]:/,
                  (_, colors) => colors + colorCodesToAnsi(settings.daemonPrefix!) + '\u001b[0m'
              )
            : line;

    const handleConsoleOutput = (line: string, prelude = false) =>
        writeLine(
            (prelude ? TERMINAL_PRELUDE : '') + replaceDaemonPrefix(line.replace(/(?:\r\n|\r|\n)$/im, '')) + '\u001b[0m'
        );

    // Hydra: message of the admins while the server is offline (Design > Console).
    const writeOfflineMessage = () => {
        if (!settings.offlineMessage) return;
        fillPlaceholders(settings.offlineMessage, placeholders)
            .split(/\r?\n/)
            .forEach((text) => writeLine(`\u001b[37m${colorCodesToAnsi(text)}\u001b[0m`));
    };

    const uploadLog = () => {
        setUploading(true);
        writeLine(TERMINAL_PRELUDE + t('console.mclogs.uploading') + '\u001b[0m');
        uploadLogToMcLogs(uuid)
            .then((url) =>
                writeLine(TERMINAL_PRELUDE + '\u001b[1m\u001b[32m' + t('console.mclogs.done', { url }) + '\u001b[0m')
            )
            .catch((error) =>
                writeLine(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + httpErrorToHuman(error) + '\u001b[0m')
            )
            .then(() => setUploading(false));
    };

    const toggleFilter = (kind: LineKind) =>
        setFilter((current) =>
            (current || []).includes(kind)
                ? (current || []).filter((item) => item !== kind)
                : [...(current || []), kind]
        );

    // Hydra: shows the kept lines again with the new filter.
    useEffect(() => {
        activeFilter.current = filter || [];
        if (!terminal.element) return;

        terminal.reset();
        lines.current.filter(shown).forEach((line) => terminal.writeln(line));
    }, [filter]);

    const handleTransferStatus = (status: string) => {
        switch (status) {
            // Sent by either the source or target node if a failure occurs.
            case 'failure':
                writeLine(TERMINAL_PRELUDE + t('console.transfer_failed') + '\u001b[0m');
                return;
        }
    };

    const handleDaemonErrorOutput = (line: string) =>
        writeLine(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

    const handlePowerChangeEvent = (state: string) => {
        // Hydra: while offline only the message of the admins is shown, when there is one.
        if (state === 'offline' && settings.offlineMessage) {
            writeOfflineMessage();
            return;
        }
        writeLine(TERMINAL_PRELUDE + t('console.marked_as', { state: t(`status.${state}`, state) }) + '\u001b[0m');
    };

    const handleCommandKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowUp') {
            const newIndex = Math.min(historyIndex + 1, history!.length - 1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';

            // By default up arrow will also bring the cursor to the start of the line,
            // so we'll preventDefault to keep it at the end.
            e.preventDefault();
        }

        if (e.key === 'ArrowDown') {
            const newIndex = Math.max(historyIndex - 1, -1);

            setHistoryIndex(newIndex);
            e.currentTarget.value = history![newIndex] || '';
        }

        const command = e.currentTarget.value;
        if (e.key === 'Enter' && command.length > 0) {
            setHistory((prevHistory) => [command, ...prevHistory!].slice(0, 32));
            setHistoryIndex(-1);

            instance && instance.send('send command', command);
            e.currentTarget.value = '';
        }
    };

    useEffect(() => {
        if (connected && ref.current && !terminal.element) {
            terminal.loadAddon(fitAddon);
            terminal.loadAddon(searchAddon);
            terminal.loadAddon(searchBar);
            terminal.loadAddon(webLinksAddon);
            terminal.loadAddon(unicode11Addon);
            terminal.loadAddon(scrollDownHelperAddon);

            terminal.open(ref.current);

            // Activate Unicode 11 for proper emoji and special character width handling
            terminal.unicode.activeVersion = '11';

            fitAddon.fit();
            searchBar.addNewStyle(zIndex);

            // Add support for capturing keys
            terminal.attachCustomKeyEventHandler((e: KeyboardEvent) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
                    document.execCommand('copy');
                    return false;
                } else if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                    e.preventDefault();
                    searchBar.show();
                    return false;
                } else if (e.key === 'Escape') {
                    searchBar.hidden();
                }
                return true;
            });
        }
    }, [terminal, connected]);

    useEventListener(
        'resize',
        debounce(() => {
            if (terminal.element) {
                fitAddon.fit();
            }
        }, 100)
    );

    useEffect(() => {
        const listeners: Record<string, (s: string) => void> = {
            [SocketEvent.STATUS]: handlePowerChangeEvent,
            [SocketEvent.CONSOLE_OUTPUT]: handleConsoleOutput,
            [SocketEvent.INSTALL_OUTPUT]: handleConsoleOutput,
            [SocketEvent.TRANSFER_LOGS]: handleConsoleOutput,
            [SocketEvent.TRANSFER_STATUS]: handleTransferStatus,
            [SocketEvent.DAEMON_MESSAGE]: (line) => handleConsoleOutput(line, true),
            [SocketEvent.DAEMON_ERROR]: handleDaemonErrorOutput,
        };

        if (connected && instance) {
            // Do not clear the console if the server is being transferred.
            if (!isTransferring) {
                terminal.clear();
                lines.current = [];
            }

            Object.keys(listeners).forEach((key: string) => {
                instance.addListener(key, listeners[key]);
            });
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                Object.keys(listeners).forEach((key: string) => {
                    instance.removeListener(key, listeners[key]);
                });
            }
        };
    }, [connected, instance]);

    return (
        <div className={styles.terminal}>
            {/* Hydra: filter by log level and upload of the log to mclo.gs, in a box of its own above the console. */}
            <div
                className={
                    'mb-2 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-hydra border border-gray-700 bg-gray-800 px-3 py-2 text-xs shadow'
                }
            >
                <span className={'text-gray-400'} title={t('console.filter.hint')}>
                    {t('console.filter.label')}
                </span>
                {LINE_KINDS.map((kind) => (
                    <label key={kind} className={'flex cursor-pointer select-none items-center gap-1.5 text-gray-200'}>
                        <input
                            type={'checkbox'}
                            className={'h-3.5 w-3.5 cursor-pointer accent-primary-500'}
                            checked={(filter || []).includes(kind)}
                            onChange={() => toggleFilter(kind)}
                        />
                        <span
                            className={classNames({
                                'text-yellow-300': kind === 'warnings',
                                'text-red-300': kind === 'errors',
                                'text-red-400 font-semibold': kind === 'fatal',
                            })}
                        >
                            {t(`console.filter.${kind}`)}
                        </span>
                    </label>
                ))}
                {settings.mclogs && canReadFiles && (
                    <button
                        type={'button'}
                        onClick={uploadLog}
                        disabled={uploading || !connected}
                        title={t('console.mclogs.title', { path: settings.mclogs })}
                        className={
                            'ml-auto flex items-center gap-1.5 rounded bg-gray-700 px-2.5 py-1 text-gray-100 hover:bg-gray-600 disabled:cursor-not-allowed disabled:opacity-50'
                        }
                    >
                        <FileUp className={'h-3.5 w-3.5'} />
                        {t('console.mclogs.button')}
                    </button>
                )}
            </div>
            <div className={'relative flex flex-col'}>
                <SpinnerOverlay visible={!connected} size={'large'} />
                <div
                    className={classNames(styles.container, styles.overflows_container, {
                        'rounded-b': !canSendCommands,
                    })}
                >
                    <div className={'h-full'}>
                        <div id={styles.terminal} ref={ref} />
                    </div>
                </div>
                {canSendCommands && (
                    <div className={classNames('relative', styles.overflows_container)}>
                        <input
                            className={classNames('peer', styles.command_input)}
                            type={'text'}
                            placeholder={t('console.command_placeholder')}
                            aria-label={t('console.command_label')}
                            disabled={!instance || !connected}
                            onKeyDown={handleCommandKeyDown}
                            autoCorrect={'off'}
                            autoCapitalize={'none'}
                        />
                        <div
                            className={classNames(
                                'text-gray-100 peer-focus:text-gray-50 peer-focus:animate-pulse',
                                styles.command_icon
                            )}
                        >
                            <ChevronDoubleRightIcon className={'w-4 h-4'} />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};
