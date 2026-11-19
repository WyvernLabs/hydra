import React from 'react';
import { useStoreState } from '@/state/hooks';

/**
 * Footer below every page and the login form: the own text from Designer >
 * Metadata, otherwise "Name © year", followed by the public pages (imprint …).
 * The fixed Hydra credit is only shown in the admin area.
 */
export default () => {
    const name = useStoreState((state) => state.settings.data!.name);
    const custom = useStoreState((state) => state.settings.data!.footer);
    // Hydra: public pages such as the imprint must be reachable from every page.
    const pages = useStoreState((state) => (state.settings.data!.links || []).filter((link) => link.public));
    const year = new Date().getFullYear();

    return (
        <p className={'hydra-footer'}>
            {custom || (
                <>
                    {name} &copy; {year}
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
