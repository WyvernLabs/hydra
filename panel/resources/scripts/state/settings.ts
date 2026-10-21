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
    // Hydra: databases can be opened in phpMyAdmin.
    phpMyAdmin: boolean;
    // Hydra: overview page of the servers; with landing servers open on it.
    dashboard: { enabled: boolean; landing: boolean };
    branding: {
        accent: string;
        logo: string;
        favicon: string;
    };
    profileUploadLimit: number;
    links: PanelLinkItem[];
    // Hydra: navigation of the server pages (Design > Navigation).
    serverNavigation: ServerNavCategory[];
    // Hydra: drawing data of the Lucide icons picked in the admin area, by name.
    icons: Record<string, Array<[string, Record<string, string>]>>;
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

// Hydra: a category of the server navigation. "name" is null for the default
// name, "color" colors the icons of its buttons.
export interface ServerNavCategory {
    id: string;
    name: string | null;
    color: string | null;
    items: ServerNavItem[];
}

// Hydra: a button of the server navigation, "eggs" limits it to some eggs.
export interface ServerNavItem {
    key: string;
    icon: string;
    eggs: number[] | null;
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
