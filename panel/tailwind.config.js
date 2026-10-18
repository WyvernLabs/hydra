const colors = require('tailwindcss/colors');

// Hydra: every gray and accent shade is backed by a CSS custom property holding
// an "r g b" triplet (see public/hydra/tokens.css). The accent color
// can therefore be changed at runtime from the admin area without rebuilding assets.
//
// Colors are defined as functions instead of "<alpha-value>" strings because
// twin.macro still bundles Tailwind 2, which only understands this form. It keeps
// opacity utilities (bg-opacity-50, bg-gray-700/50) working in both versions.
const variable = (name) => ({ opacityVariable, opacityValue }) => {
    if (opacityValue !== undefined) {
        return `rgb(var(${name}) / ${opacityValue})`;
    }
    if (opacityVariable !== undefined) {
        return `rgb(var(${name}) / var(${opacityVariable}, 1))`;
    }
    return `rgb(var(${name}))`;
};

const scale = (name) =>
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].reduce((shades, step) => {
        shades[step] = variable(`--hydra-${name}-${step}`);
        return shades;
    }, {});

const gray = scale('gray');
const accent = scale('accent');

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Segoe UI"', 'system-ui', '-apple-system', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
                header: ['"Segoe UI"', 'system-ui', '-apple-system', 'Roboto', '"Helvetica Neue"', 'Arial', 'sans-serif'],
            },
            colors: {
                black: variable('--hydra-black'),
                // "primary" and "neutral" are deprecated upstream, prefer "blue" and "gray". In Hydra
                // "primary", "blue" and "cyan" all point at the configurable accent color.
                primary: accent,
                blue: accent,
                cyan: accent,
                accent: accent,
                gray: gray,
                neutral: gray,
                green: colors.green,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            // Rounder corners than upstream, matching the Sylas panel.
            borderRadius: {
                DEFAULT: '9px',
                md: '9px',
                lg: '14px',
                hydra: 'var(--hydra-radius)',
                'hydra-sm': 'var(--hydra-radius-sm)',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
