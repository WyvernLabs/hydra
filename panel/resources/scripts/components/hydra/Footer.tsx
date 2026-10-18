import React from 'react';
import { useStoreState } from '@/state/hooks';

/**
 * Footer below every page and the login form. Shows the configured panel name and,
 * when it was renamed, a small "Hydra" hint – without text that would need translating.
 */
export default () => {
    const name = useStoreState((state) => state.settings.data!.name);

    return (
        <p className={'hydra-footer'}>
            {name} &copy; {new Date().getFullYear()}
            {name.trim().toLowerCase() !== 'hydra' && <> &middot; Hydra</>}
        </p>
    );
};
