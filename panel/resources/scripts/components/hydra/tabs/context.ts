import React, { useContext, useEffect } from 'react';

// Hydra: what a page knows about the app tab it is shown in. Outside of the
// tab mode of the installed app there is exactly one, always active "tab".

export interface AppTabContextValue {
    inTabs: boolean;
    // The tab is the one in front; keyboard shortcuts only work there.
    active: boolean;
    setTitle: (title: string) => void;
    // Opens a page of the panel in a new tab (or a new browser tab outside the app).
    open: (path: string, background?: boolean) => void;
}

export const AppTabContext = React.createContext<AppTabContextValue>({
    inTabs: false,
    active: true,
    setTitle: (title) => {
        document.title = title;
    },
    open: (path) => {
        window.open(path, '_blank', 'noopener');
    },
});

export const useAppTab = (): AppTabContextValue => useContext(AppTabContext);

/**
 * Sets the window title, or the title of the tab in the tab mode.
 */
export const useDocumentTitle = (title?: string) => {
    const { setTitle } = useAppTab();

    useEffect(() => {
        if (title) setTitle(title);
    }, [title]);
};
