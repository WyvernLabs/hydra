import { action, Action } from 'easy-peasy';

export interface SiteSettings {
    name: string;
    locale: string;
    // Installed languages with their native names, e.g. { en: 'English', de: 'Deutsch' }.
    languages: Record<string, string>;
    recaptcha: {
        enabled: boolean;
        siteKey: string;
    };
    branding: {
        accent: string;
        logo: string;
        favicon: string;
    };
    profileUploadLimit: number;
    links: PanelLinkItem[];
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
