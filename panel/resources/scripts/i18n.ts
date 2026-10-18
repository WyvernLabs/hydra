import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import I18NextHttpBackend, { HttpBackendOptions } from 'i18next-http-backend';
import I18NextMultiloadBackendAdapter from 'i18next-multiload-backend-adapter';

// If we're using HMR use a unique hash per page reload so that we're always
// doing cache busting. Otherwise just use the builder provided hash value in
// the URL to allow cache busting to occur whenever the front-end is rebuilt.
const hash = module.hot ? Date.now().toString(16) : process.env.WEBPACK_BUILD_HASH;

// Hydra: the server already resolved the language for this request (user setting >
// browser language > panel default) and hands it over in window.SiteConfiguration.
const locale = (window as { SiteConfiguration?: { locale?: string } }).SiteConfiguration?.locale || 'en';

i18n.use(I18NextMultiloadBackendAdapter)
    .use(initReactI18next)
    .init({
        debug: process.env.DEBUG === 'true',
        lng: locale,
        fallbackLng: 'en',
        keySeparator: '.',
        // Translations live in resources/lang/<locale>/<namespace>.php. Keys can name their
        // namespace explicitly, e.g. t('client/common:actions.save').
        nsSeparator: ':',
        ns: ['client/common'],
        defaultNS: 'client/common',
        backend: {
            backend: I18NextHttpBackend,
            backendOption: {
                loadPath: '/locales/locale.json?locale={{lng}}&namespace={{ns}}',
                queryStringParams: { hash },
                allowMultiLoading: true,
            } as HttpBackendOptions,
        } as Record<string, any>,
        interpolation: {
            // Per i18n-react documentation: this is not needed since React is already
            // handling escapes for us.
            escapeValue: false,
        },
    });

i18n.on('languageChanged', (language) => {
    document.documentElement.lang = language;
});

export default i18n;
