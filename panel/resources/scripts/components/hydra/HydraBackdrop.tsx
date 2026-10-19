import React, { useEffect } from 'react';
import { HydraBackground } from '@/state/settings';

const isDefault = (background?: HydraBackground | null): boolean =>
    !background || (!background.image && !background.blur && !background.dim);

/**
 * Hydra: the page background set in the admin area, drawn behind everything.
 * Without an image the hexagon pattern is blurred and darkened; if nothing is
 * changed at all the body background is used as it is.
 *
 * The same background (without blur) is also handed to the page as
 * --hydra-page-background, for elements that cover the background, such as
 * the gap above the sticky greeting on the dashboard.
 */
export default ({ background }: { background?: HydraBackground | null }) => {
    useEffect(() => {
        if (isDefault(background)) return;

        const dim = `rgb(0 0 0 / ${(background!.dim / 100).toFixed(2)})`;
        const image = background!.image
            ? `url("${background!.image}") center / cover no-repeat fixed`
            : 'var(--hydra-pattern) center top / 760px repeat fixed';

        const root = document.documentElement;
        root.style.setProperty(
            '--hydra-page-background',
            `linear-gradient(${dim}, ${dim}) fixed, ${image}, var(--hydra-bg)`
        );

        return () => {
            root.style.removeProperty('--hydra-page-background');
        };
    }, [background?.image, background?.blur, background?.dim]);

    if (isDefault(background)) {
        return null;
    }

    return (
        <div className={'hydra-backdrop-layer'} aria-hidden>
            <div
                className={'hydra-backdrop-layer-image'}
                style={{
                    ...(background!.image
                        ? {
                              backgroundImage: `url("${background!.image}")`,
                              backgroundSize: 'cover',
                              backgroundPosition: 'center',
                              backgroundRepeat: 'no-repeat',
                          }
                        : {}),
                    filter: background!.blur ? `blur(${background!.blur}px)` : undefined,
                }}
            />
            {background!.dim > 0 && (
                <div className={'hydra-backdrop-layer-dim'} style={{ opacity: background!.dim / 100 }} />
            )}
        </div>
    );
};
