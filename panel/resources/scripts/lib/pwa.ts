import { useEffect, useState } from 'react';

// Hydra: helpers of the installable app (addon "pwa").

interface InstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let deferred: InstallPromptEvent | null = null;
const listeners = new Set<() => void>();
const notify = () => listeners.forEach((listener) => listener());

// The browser offers the installation once, often before React has rendered.
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferred = event as InstallPromptEvent;
    notify();
});
window.addEventListener('appinstalled', () => {
    deferred = null;
    notify();
});

// Right after the installation the browser moves the open page into the app
// window without loading it again, so it would run without tabs until the
// next start. It is loaded again once it is in the window.
const appWindow = window.matchMedia('(display-mode: standalone), (display-mode: window-controls-overlay)');
appWindow.addEventListener?.('change', (event) => {
    if (event.matches && !document.documentElement.classList.contains('hydra-tabs-mode')) {
        window.location.reload();
    }
});

/**
 * Whether the panel runs as installed app in its own window.
 */
export const isStandalone = (): boolean =>
    window.matchMedia(
        '(display-mode: standalone), (display-mode: window-controls-overlay), (display-mode: minimal-ui), (display-mode: fullscreen)'
    ).matches || (navigator as Navigator & { standalone?: boolean }).standalone === true;

/**
 * Pages open in tabs inside the window: in the installed app, or in the
 * browser after localStorage "hydra:tabs" was set to "1" (for trying it out).
 */
export const wantsTabs = (): boolean => {
    try {
        if (localStorage.getItem('hydra:tabs') === '1') return true;
    } catch {
        // Storage may be blocked.
    }

    return isStandalone();
};

/**
 * Function that asks the browser to install the app, null while it cannot.
 */
export const useInstallPrompt = (): (() => void) | null => {
    const [, setVersion] = useState(0);

    useEffect(() => {
        const listener = () => setVersion((version) => version + 1);
        listeners.add(listener);
        return () => {
            listeners.delete(listener);
        };
    }, []);

    if (!deferred || isStandalone()) return null;

    return () => {
        const event = deferred;
        if (!event) return;
        event.prompt().catch(() => undefined);
        event.userChoice.finally(() => {
            deferred = null;
            notify();
        });
    };
};
