/**
 * Given a valid six character HEX color code, converts it into its associated
 * RGBA value with a user controllable alpha channel.
 */
function hexToRgba(hex: string, alpha = 1): string {
    // noinspection RegExpSimplifiable
    if (!/#?([a-fA-F0-9]{2}){3}/.test(hex)) {
        return hex;
    }

    // noinspection RegExpSimplifiable
    const [r, g, b] = hex.match(/[a-fA-F0-9]{2}/g)!.map((v) => parseInt(v, 16));

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export { hexToRgba };

/**
 * Resolves one of the Hydra color tokens (e.g. "accent-400", "gray-700") to a
 * concrete rgba() string. Needed wherever colors are drawn on a canvas (charts,
 * terminal) because those cannot use CSS custom properties directly.
 */
function hydraColor(token: string, alpha = 1): string {
    const value = getComputedStyle(document.documentElement).getPropertyValue(`--hydra-${token}`).trim();
    const [r, g, b] = value.split(/\s+/).map((v) => parseInt(v, 10));
    if ([r, g, b].some((v) => Number.isNaN(v))) {
        return `rgba(0, 0, 0, ${alpha})`;
    }

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

export { hydraColor };
