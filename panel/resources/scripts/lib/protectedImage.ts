/**
 * Hydra: CSS class that shields a picture against "save image", dragging and
 * long press (public/hydra/layout.css). Empty while the protection is switched
 * off under Design > Colors & Logo.
 */
export const protectedClass = (branding?: { protect?: boolean }): string =>
    branding?.protect === false ? '' : 'hydra-protected';
