import React, { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';
import tw from 'twin.macro';
import { useTranslation } from 'react-i18next';
import { useStoreState } from '@/state/hooks';
import { TurnstileForm } from '@/state/settings';

// Hydra: Cloudflare Turnstile widget for the forms of the login page
// (Design > Addons > Cloudflare Turnstile).

interface TurnstileApi {
    render: (container: HTMLElement, options: Record<string, unknown>) => string;
    reset: (widget: string) => void;
    remove: (widget: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

let loader: Promise<TurnstileApi> | null = null;

const loadTurnstile = (): Promise<TurnstileApi> => {
    if (window.turnstile) return Promise.resolve(window.turnstile);

    if (!loader) {
        loader = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            script.onload = () =>
                window.turnstile ? resolve(window.turnstile) : reject(new Error('Turnstile missing.'));
            script.onerror = () => {
                loader = null;
                reject(new Error('Turnstile could not be loaded.'));
            };
            document.head.appendChild(script);
        });
    }

    return loader;
};

/**
 * Whether the given form has to send a Turnstile token.
 */
export const useTurnstile = (form: TurnstileForm): { enabled: boolean; siteKey: string } => {
    const turnstile = useStoreState((state) => state.settings.data!.turnstile);

    return { enabled: !!turnstile?.siteKey && !!turnstile.forms[form], siteKey: turnstile?.siteKey || '' };
};

export interface TurnstileHandle {
    // Tokens can only be used once, so the widget is reset after every submit.
    reset: () => void;
}

interface Props {
    siteKey: string;
    onToken: (token: string) => void;
}

const Turnstile = forwardRef<TurnstileHandle, Props>(({ siteKey, onToken }, ref) => {
    const { i18n } = useTranslation();
    const container = useRef<HTMLDivElement>(null);
    const widget = useRef<string | null>(null);
    const callback = useRef(onToken);
    callback.current = onToken;

    useImperativeHandle(ref, () => ({
        reset: () => {
            callback.current('');
            if (widget.current && window.turnstile) window.turnstile.reset(widget.current);
        },
    }));

    useEffect(() => {
        let cancelled = false;

        loadTurnstile()
            .then((turnstile) => {
                if (cancelled || !container.current) return;

                widget.current = turnstile.render(container.current, {
                    sitekey: siteKey,
                    theme: 'dark',
                    size: 'flexible',
                    language: i18n.language,
                    callback: (token: string) => callback.current(token),
                    'expired-callback': () => callback.current(''),
                    'error-callback': () => callback.current(''),
                });
            })
            .catch(console.error);

        return () => {
            cancelled = true;
            if (widget.current && window.turnstile) window.turnstile.remove(widget.current);
            widget.current = null;
        };
    }, [siteKey]);

    return <div ref={container} css={tw`mt-6`} style={{ minHeight: 65 }} />;
});

Turnstile.displayName = 'Turnstile';

export default Turnstile;
