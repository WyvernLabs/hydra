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
