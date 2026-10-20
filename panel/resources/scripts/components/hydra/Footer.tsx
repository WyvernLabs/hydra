import React from 'react';
import { useStoreState } from '@/state/hooks';

// Mandatory credit, same text as BrandingService::CREDIT. WyvernLabs is a registered
// trademark; unlike the rest of the branding it is not configurable.
export const HYDRA_CREDIT = 'Hydra by WyvernLabs®';

/**
 * Footer below every page and the login form. Shows the configured panel name and
 * the Hydra credit, which always stays visible – without text that would need translating.
 */
export default () => {
    const name = useStoreState((state) => state.settings.data!.name);
    // Hydra: public pages such as the imprint must be reachable from every page.
    const pages = useStoreState((state) => (state.settings.data!.links || []).filter((link) => link.public));
    const year = new Date().getFullYear();

    return (
        <p className={'hydra-footer'}>
            {name.trim().toLowerCase() === 'hydra' ? (
                <>
                    {HYDRA_CREDIT} &copy; {year}
                </>
            ) : (
                <>
                    {name} &copy; {year} &middot; {HYDRA_CREDIT}
                </>
            )}
            {pages.map((page) => (
                <React.Fragment key={page.id}>
                    {' '}
                    &middot; <a href={`/pages/${page.slug}`}>{page.title}</a>
                </React.Fragment>
            ))}
        </p>
    );
};
