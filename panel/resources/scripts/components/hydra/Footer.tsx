import React from 'react';
import { useStoreState } from '@/state/hooks';

/**
 * Footer below every page and the login form. Shows the configured panel name and,
 * when it was renamed, a small "Hydra" hint – without text that would need translating.
 */
export default () => {
    const name = useStoreState((state) => state.settings.data!.name);
    // Hydra: public pages such as the imprint must be reachable from every page.
    const pages = useStoreState((state) => (state.settings.data!.links || []).filter((link) => link.public));

    return (
        <p className={'hydra-footer'}>
            {name} &copy; {new Date().getFullYear()}
            {name.trim().toLowerCase() !== 'hydra' && <> &middot; Hydra</>}
            {pages.map((page) => (
                <React.Fragment key={page.id}>
                    {' '}
                    &middot; <a href={`/pages/${page.slug}`}>{page.title}</a>
                </React.Fragment>
            ))}
        </p>
    );
};
