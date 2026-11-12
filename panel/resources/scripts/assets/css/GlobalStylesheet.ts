import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';

export default createGlobalStyle`
    body {
        ${tw`font-sans text-neutral-200`};
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
    }

    p {
        ${tw`text-neutral-200 leading-snug font-sans`};
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    /* Hydra: the panel is dark only. Without this, the browser draws the open
       dropdown list in its light system style and the options inherit the light
       text colour of the select, which leaves white text on light grey.
       The surface colours are translucent with glass designs, and the browser
       draws the open list on a white base, so the options need opaque colours. */
    select {
        color-scheme: dark;
    }

    select option, select optgroup {
        background-color: rgb(var(--hydra-surface-800, 31 31 31));
        color: rgb(var(--hydra-gray-100, 245 245 245));
    }

    select option:checked {
        background-color: rgb(var(--hydra-accent-600, 37 99 235));
        color: #fff;
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }
`;
