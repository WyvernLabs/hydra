import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Router } from 'react-router-dom';
import { createMemoryHistory, MemoryHistory } from 'history';
import classNames from 'classnames';
import { useTranslation } from 'react-i18next';
import { House, Plus, Server as ServerIcon, ShieldCheck, X as CloseIcon } from 'lucide-react';
import { useStoreState } from '@/state/hooks';
import { history as browserHistory } from '@/components/history';
import { AppTabContext, AppTabContextValue } from '@/components/hydra/tabs/context';

/*
 * Hydra: tabs inside the window of the installed app (addon "pwa"). Every tab
 * has a router of its own and stays mounted while it is in the background, so
 * consoles, uploads and open files go on. Links opened with the middle mouse
 * button or Ctrl/Shift open a tab instead of a new window. The admin area is
 * no part of the React app and is shown in a frame tab.
 */

interface Tab {
    id: string;
    path: string;
    title: string;
    frame: boolean;
}

interface State {
    tabs: Tab[];
    active: string;
    // Tabs that were in front once; the others are only loaded when opened.
    mounted: string[];
}

type Navigator = { push: (path: string) => void; back?: () => void; forward?: () => void };

const newId = (): string => Math.random().toString(36).slice(2, 10);

/**
 * "app" for pages of the React app, "frame" for the admin area, null for
 * everything else (logout, downloads, …), which is left to the browser.
 */
export const pageKind = (pathname: string): 'app' | 'frame' | null => {
    if (pathname === '/' || /^\/(server|account|pages)(\/|$)/.test(pathname)) return 'app';
    if (/^\/admin(\/|$)/.test(pathname)) return 'frame';
    return null;
};

const pathOf = (location: { pathname: string; search: string; hash: string }): string =>
    location.pathname + location.search + location.hash;

const initialState = (key: string): State => {
    let tabs: Tab[] = [];
    let active = '';
    try {
        const saved = JSON.parse(localStorage.getItem(key) || 'null');
        if (saved && Array.isArray(saved.tabs)) {
            tabs = saved.tabs.filter(
                (tab: Tab) =>
                    typeof tab.id === 'string' && typeof tab.path === 'string' && pageKind(tab.path.split(/[?#]/)[0])
            );
            active = saved.active;
        }
    } catch {
        // Nothing saved or storage blocked.
    }

    // The address the app was started with, e.g. a link from an email.
    const launch = pathOf(window.location);
    if (pageKind(window.location.pathname) === 'app') {
        const existing = tabs.find((tab) => !tab.frame && tab.path === launch);
        if (existing) {
            active = existing.id;
        } else if (launch !== '/' || tabs.length === 0) {
            const tab = { id: newId(), path: launch, title: '', frame: false };
            tabs.push(tab);
            active = tab.id;
        }
    }
    if (tabs.length === 0) {
        tabs = [{ id: newId(), path: '/', title: '', frame: false }];
    }
    if (!tabs.some((tab) => tab.id === active)) {
        active = tabs[0].id;
    }

    return { tabs, active, mounted: [active] };
};

interface ViewProps {
    tab: Tab;
    active: boolean;
    Routes: React.ComponentType;
    register: (id: string, navigator: Navigator | null) => void;
    onPath: (id: string, path: string) => void;
    onTitle: (id: string, title: string) => void;
    open: (path: string, background?: boolean) => void;
    attach: (doc: Document) => void;
}

// Views only render again when they come to the front or go back; the path
// and title in "tab" are only read when they start.
const sameView = (a: ViewProps, b: ViewProps) => a.active === b.active && a.tab.id === b.tab.id;

const AppView = React.memo(({ tab, active, Routes, register, onPath, onTitle, open }: ViewProps) => {
    const history = useMemo<MemoryHistory>(() => createMemoryHistory({ initialEntries: [tab.path] }), []);

    useEffect(() => {
        register(tab.id, {
            push: (path) => history.push(path),
            back: () => history.canGo(-1) && history.goBack(),
            forward: () => history.canGo(1) && history.goForward(),
        });
        const unlisten = history.listen((location) => onPath(tab.id, pathOf(location)));

        return () => {
            unlisten();
            register(tab.id, null);
        };
    }, []);

    const context = useMemo<AppTabContextValue>(
        () => ({ inTabs: true, active, setTitle: (title) => onTitle(tab.id, title), open }),
        [active]
    );

    return (
        <div className={classNames('hydra-tab-view', { 'is-active': active })} aria-hidden={!active}>
            <AppTabContext.Provider value={context}>
                <Router history={history}>
                    <Routes />
                </Router>
            </AppTabContext.Provider>
        </div>
    );
}, sameView);

const FrameView = React.memo(({ tab, active, register, onPath, onTitle, attach }: ViewProps) => {
    const frame = useRef<HTMLIFrameElement>(null);
    // Later pages are loaded by the frame itself.
    const [src] = useState(tab.path);

    useEffect(() => {
        register(tab.id, { push: (path) => frame.current?.contentWindow?.location.assign(path) });
        return () => register(tab.id, null);
    }, []);

    const onLoad = () => {
        try {
            const view = frame.current!.contentWindow!;
            onPath(tab.id, pathOf(view.location));
            onTitle(tab.id, view.document.title);
            attach(view.document);
        } catch {
            // Not readable, e.g. an error page of the browser.
        }
    };

    return (
        <div
            className={classNames('hydra-tab-view', 'hydra-tab-view--frame', { 'is-active': active })}
            aria-hidden={!active}
        >
            <iframe ref={frame} src={src} onLoad={onLoad} title={tab.id} className={'hydra-tab-frame'} />
        </div>
    );
}, sameView);

export default ({ Routes }: { Routes: React.ComponentType }) => {
    const { t } = useTranslation();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const appName = useStoreState((state) => state.settings.data!.name);
    const key = `${uuid}:hydra_tabs`;
    const [state, setState] = useState<State>(() => initialState(key));
    const current = useRef(state);
    current.current = state;
    const navigators = useRef<Record<string, Navigator>>({});
    const [dragging, setDragging] = useState<string | null>(null);

    const register = useCallback((id: string, navigator: Navigator | null) => {
        if (navigator) {
            navigators.current[id] = navigator;
        } else {
            delete navigators.current[id];
        }
    }, []);

    const activate = useCallback((id: string) => {
        setState((state) => ({
            ...state,
            active: id,
            mounted: state.mounted.includes(id) ? state.mounted : [...state.mounted, id],
        }));
    }, []);

    const open = useCallback((path: string, background = false) => {
        const kind = pageKind(new URL(path, window.location.origin).pathname);
        if (!kind) {
            window.location.assign(path);
            return;
        }

        // The admin area keeps one tab.
        const frame = kind === 'frame' ? current.current.tabs.find((tab) => tab.frame) : undefined;
        if (frame) {
            navigators.current[frame.id]?.push(path);
            if (!background) activate(frame.id);
            return;
        }

        const tab: Tab = { id: newId(), path, title: '', frame: kind === 'frame' };
        setState((state) => {
            // New tabs go right of the one in front, like in a browser.
            const index = state.tabs.findIndex((item) => item.id === state.active);
            const tabs = [...state.tabs];
            tabs.splice(index + 1, 0, tab);

            return background ? { ...state, tabs } : { tabs, active: tab.id, mounted: [...state.mounted, tab.id] };
        });
    }, []);

    const close = useCallback((id: string) => {
        setState((state) => {
            const index = state.tabs.findIndex((tab) => tab.id === id);
            const tabs = state.tabs.filter((tab) => tab.id !== id);
            if (tabs.length === 0) {
                const tab = { id: newId(), path: '/', title: '', frame: false };
                return { tabs: [tab], active: tab.id, mounted: [tab.id] };
            }
            const active = state.active === id ? tabs[Math.min(index, tabs.length - 1)].id : state.active;

            return {
                tabs,
                active,
                mounted: [...state.mounted.filter((item) => item !== id), active].filter(
                    (item, position, list) => list.indexOf(item) === position
                ),
            };
        });
    }, []);

    const update = useCallback((id: string, values: Partial<Tab>) => {
        setState((state) => {
            const tab = state.tabs.find((item) => item.id === id);
            if (!tab || Object.entries(values).every(([field, value]) => tab[field as keyof Tab] === value)) {
                return state;
            }

            return { ...state, tabs: state.tabs.map((item) => (item.id === id ? { ...item, ...values } : item)) };
        });
    }, []);

    const onPath = useCallback((id: string, path: string) => {
        const tab = current.current.tabs.find((item) => item.id === id);
        const kind = pageKind(new URL(path, window.location.origin).pathname);

        if (tab?.frame) {
            if (kind === 'frame') {
                update(id, { path });
            } else if (kind === 'app') {
                // A link of the admin area back to the panel.
                close(id);
                open(path);
            } else {
                window.location.assign(path);
            }
            return;
        }

        if (kind === 'app') {
            update(id, { path });
        } else {
            navigators.current[id]?.back?.();
            if (kind === 'frame') {
                open(path);
            } else {
                window.location.assign(path);
            }
        }
    }, []);

    const onTitle = useCallback((id: string, title: string) => update(id, { title }), []);

    // Links with the middle mouse button or Ctrl/Shift open a tab.
    const onLink = useCallback((event: MouseEvent) => {
        if (event.type === 'auxclick' && event.button !== 1) return;
        const target = event.target as Element | null;
        const link = target?.closest?.('a[href]') as HTMLAnchorElement | null;
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        const url = new URL(link.href, window.location.href);
        if (url.origin !== window.location.origin) return;
        const kind = pageKind(url.pathname);
        if (!kind) return;

        const modified = event.type === 'auxclick' || event.ctrlKey || event.metaKey || event.shiftKey;
        if (event.type === 'mousedown') {
            // No scroll cursor for the middle button.
            if (event.button === 1) event.preventDefault();
            return;
        }
        // Plain clicks: the React app and the admin frame handle their own links.
        const inFrame = link.ownerDocument !== document;
        if (!modified && ((kind === 'app' && !inFrame) || (kind === 'frame' && inFrame))) return;

        event.preventDefault();
        event.stopPropagation();
        open(pathOf(url), modified && !event.shiftKey);
    }, []);

    const attached = useRef(new WeakSet<Document>());
    const attach = useCallback((doc: Document) => {
        if (attached.current.has(doc)) return;
        attached.current.add(doc);
        ['click', 'auxclick', 'mousedown'].forEach((type) => doc.addEventListener(type, onLink as EventListener, true));
    }, []);

    useEffect(() => {
        attach(document);

        const move = (step: number) => {
            const { tabs, active } = current.current;
            const index = tabs.findIndex((tab) => tab.id === active);
            activate(tabs[(index + step + tabs.length) % tabs.length].id);
        };
        const onKey = (event: KeyboardEvent) => {
            if (event.ctrlKey && (event.key === 'Tab' || event.key === 'PageDown' || event.key === 'PageUp')) {
                event.preventDefault();
                move(event.key === 'PageUp' || (event.key === 'Tab' && event.shiftKey) ? -1 : 1);
            } else if (event.altKey && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
                event.preventDefault();
                const navigator = navigators.current[current.current.active];
                event.key === 'ArrowLeft' ? navigator?.back?.() : navigator?.forward?.();
            }
        };
        // The back and forward buttons of the mouse.
        const onMouse = (event: MouseEvent) => {
            if (event.button !== 3 && event.button !== 4) return;
            event.preventDefault();
            const navigator = navigators.current[current.current.active];
            event.button === 3 ? navigator?.back?.() : navigator?.forward?.();
        };
        document.addEventListener('keydown', onKey);
        document.addEventListener('mouseup', onMouse);

        // Redirects of the API helpers (e.g. to set up two-factor) go to the tab in front.
        const unlisten = browserHistory.listen((location) =>
            navigators.current[current.current.active]?.push(pathOf(location))
        );

        // Links to the panel opened elsewhere arrive in the running app (manifest "launch_handler").
        const launchQueue = (
            window as Window & { launchQueue?: { setConsumer: (fn: (params: { targetURL?: string }) => void) => void } }
        ).launchQueue;
        let first = true;
        launchQueue?.setConsumer((params) => {
            // The first one is the address the app started with, which has a tab already.
            if (first) {
                first = false;
                return;
            }
            if (params.targetURL) open(pathOf(new URL(params.targetURL)));
        });

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('mouseup', onMouse);
            unlisten();
        };
    }, []);

    // Open tabs come back on the next start.
    useEffect(() => {
        try {
            localStorage.setItem(
                key,
                JSON.stringify({
                    tabs: state.tabs.map(({ id, path, title, frame }) => ({ id, path, title, frame })),
                    active: state.active,
                })
            );
        } catch {
            // Storage full or blocked.
        }
    }, [state.tabs, state.active]);

    // The address bar and the window title follow the tab in front.
    const front = state.tabs.find((tab) => tab.id === state.active);
    useEffect(() => {
        if (!front) return;
        window.history.replaceState(null, '', front.frame ? '/' : front.path);
        document.title = front.title || appName;
    }, [front?.path, front?.title, front?.frame]);

    useEffect(() => {
        document.documentElement.classList.add('hydra-tabs-mode');
        return () => document.documentElement.classList.remove('hydra-tabs-mode');
    }, []);

    const reorder = (over: string) => {
        if (!dragging || dragging === over) return;
        setState((state) => {
            const tabs = [...state.tabs];
            const from = tabs.findIndex((tab) => tab.id === dragging);
            const to = tabs.findIndex((tab) => tab.id === over);
            tabs.splice(to, 0, tabs.splice(from, 1)[0]);
            return { ...state, tabs };
        });
    };

    return (
        <>
            <div className={'hydra-tabbar'}>
                <div className={'hydra-tabbar-tabs'} role={'tablist'}>
                    {state.tabs.map((tab) => {
                        const Icon = tab.frame ? ShieldCheck : tab.path.startsWith('/server/') ? ServerIcon : House;

                        return (
                            <div
                                key={tab.id}
                                role={'tab'}
                                aria-selected={tab.id === state.active}
                                title={tab.title}
                                draggable
                                className={classNames('hydra-tab', {
                                    'is-active': tab.id === state.active,
                                    'is-dragging': tab.id === dragging,
                                })}
                                onClick={() => activate(tab.id)}
                                onMouseDown={(event) => event.button === 1 && event.preventDefault()}
                                onAuxClick={(event) => {
                                    if (event.button === 1) {
                                        event.preventDefault();
                                        close(tab.id);
                                    }
                                }}
                                onDragStart={(event) => {
                                    event.dataTransfer.effectAllowed = 'move';
                                    setDragging(tab.id);
                                }}
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    reorder(tab.id);
                                }}
                                onDragEnd={() => setDragging(null)}
                            >
                                <Icon className={'hydra-tab-icon'} />
                                <span className={'hydra-tab-title'}>{tab.title || t('tabs.loading')}</span>
                                <button
                                    type={'button'}
                                    className={'hydra-tab-close'}
                                    onClick={(event) => {
                                        event.stopPropagation();
                                        close(tab.id);
                                    }}
                                    title={t('tabs.close')}
                                    aria-label={t('tabs.close')}
                                >
                                    <CloseIcon />
                                </button>
                            </div>
                        );
                    })}
                    <button
                        type={'button'}
                        className={'hydra-tab-new'}
                        onClick={() => open('/')}
                        title={t('tabs.new')}
                        aria-label={t('tabs.new')}
                    >
                        <Plus />
                    </button>
                </div>
            </div>
            <div className={'hydra-tab-views'}>
                {state.tabs
                    .filter((tab) => state.mounted.includes(tab.id))
                    .map((tab) => {
                        const View = tab.frame ? FrameView : AppView;

                        return (
                            <View
                                key={tab.id}
                                tab={tab}
                                active={tab.id === state.active}
                                Routes={Routes}
                                register={register}
                                onPath={onPath}
                                onTitle={onTitle}
                                open={open}
                                attach={attach}
                            />
                        );
                    })}
            </div>
        </>
    );
};
