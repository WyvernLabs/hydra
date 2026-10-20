import { action, Action } from 'easy-peasy';

export interface SiteSettings {
    name: string;
    locale: string;
    // Installed languages with their native names, e.g. { en: 'English', de: 'Deutsch' }.
    languages: Record<string, string>;
    // Hydra: Cloudflare Turnstile (Design > Addons). siteKey is empty while
    // the addon is off, forms says which forms need a token.
    turnstile: {
        siteKey: string;
        forms: Record<TurnstileForm, boolean>;
    };
    // Hydra: visitors can create an account themselves.
    registration: boolean;
    branding: {
        accent: string;
        logo: string;
        favicon: string;
    };
    profileUploadLimit: number;
    links: PanelLinkItem[];
    backgrounds: {
        login: HydraBackground;
        dashboard: HydraBackground;
    };
}

export type TurnstileForm = 'login' | 'password' | 'register';

// Hydra: a background set in the admin area. Without an image the hexagon
// pattern is used; blur is in pixels, dim in percent.
export interface HydraBackground {
    image: string | null;
    blur: number;
    dim: number;
}

// Hydra: a link or page in the "Links" section of the sidebar.
export interface PanelLinkItem {
    id: number;
    type: 'page' | 'url';
    title: string;
    icon: string;
    slug: string | null;
    url: string | null;
    public: boolean;
}

export interface SettingsStore {
    data?: SiteSettings;
    setSettings: Action<SettingsStore, SiteSettings>;
}

const settings: SettingsStore = {
    data: undefined,

    setSettings: action((state, payload) => {
        state.data = payload;
    }),
};

export default settings;
