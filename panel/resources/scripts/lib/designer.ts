import { useEffect, useState } from 'react';
import { useStoreState } from '@/state/hooks';
import { HydraBackground, HydraLoginDesign } from '@/state/settings';

/**
 * Hydra: live preview of the designer (admin area > Design). The designer
 * shows the panel in a frame with "?hydra_designer=1" and sends the unsaved
 * values of its form as messages. partials/hydra/head.blade.php applies the
 * CSS variables and passes everything on as a "hydra-designer" event; the
 * last state stays in window.hydraDesigner for components that mount later.
 */
export interface DesignerState {
    login?: Partial<HydraLoginDesign>;
    backgrounds?: Partial<Record<'login' | 'dashboard', HydraBackground>>;
}

declare global {
    interface Window {
        hydraDesigner?: DesignerState;
    }
}

export const useDesignerState = (): DesignerState => {
    const [state, setState] = useState<DesignerState>(() => window.hydraDesigner || {});

    useEffect(() => {
        const listener = (event: Event) => setState({ ...((event as CustomEvent<DesignerState>).detail || {}) });
        window.addEventListener('hydra-designer', listener);

        return () => window.removeEventListener('hydra-designer', listener);
    }, []);

    return state;
};

const DEFAULT_LOGIN: HydraLoginDesign = {
    style: 'classic',
    mirror: false,
    image: null,
    title: null,
    subtitle: null,
    info: null,
};

/**
 * Look of the login page: the saved one, overridden by the designer preview.
 */
export const useLoginDesign = (): HydraLoginDesign => {
    const saved = useStoreState((state) => state.settings.data!.login);
    const preview = useDesignerState().login;

    return { ...DEFAULT_LOGIN, ...(saved || {}), ...(preview || {}) };
};

/**
 * A page background, overridden by the designer preview.
 */
export const useDesignerBackground = (
    slot: 'login' | 'dashboard',
    saved?: HydraBackground | null
): HydraBackground | null | undefined => {
    const preview = useDesignerState().backgrounds?.[slot];

    return preview || saved;
};
