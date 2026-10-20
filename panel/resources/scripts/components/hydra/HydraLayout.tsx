import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router';
import { Menu as MenuIcon, X as XIcon } from 'lucide-react';
import Sidebar, { SidebarSection } from '@/components/hydra/Sidebar';
import { useStoreState } from '@/state/hooks';
import http from '@/api/http';
import HydraBackdrop from '@/components/hydra/HydraBackdrop';
import { HydraBackground } from '@/state/settings';
import { useTranslation } from 'react-i18next';

interface Props {
    sections: SidebarSection[];
    children: React.ReactNode;
    // Hydra: e.g. the background of a server's egg; the dashboard background otherwise.
    background?: HydraBackground | null;
}

/**
 * Page shell of the client area: floating sidebar on the left, content on the
 * right. On small screens the sidebar turns into an off-canvas menu.
 */
export default ({ sections, children, background }: Props) => {
    const location = useLocation();
    const [open, setOpen] = useState(false);
    const branding = useStoreState((state) => state.settings.data!.branding);
    const name = useStoreState((state) => state.settings.data!.name);
    const dashboardBackground = useStoreState((state) => state.settings.data!.backgrounds?.dashboard);
    const { t } = useTranslation();

    useEffect(() => setOpen(false), [location.pathname]);

    // Hydra: keeps the "online" status in the admin area up to date while the
    // panel is open, even on pages that do not talk to the API on their own.
    useEffect(() => {
        const timer = setInterval(() => {
            if (document.visibilityState === 'visible') {
                http.get('/api/client/account').catch(() => undefined);
            }
        }, 120 * 1000);

        return () => clearInterval(timer);
    }, []);

    return (
        <div className={'hydra-shell'}>
            <HydraBackdrop background={background || dashboardBackground} />
            <header className={'hydra-topbar'}>
                <button type={'button'} className={'hydra-icon-btn'} onClick={() => setOpen(!open)}>
                    {open ? <XIcon className={'hydra-nav-icon'} /> : <MenuIcon className={'hydra-nav-icon'} />}
                    <span className={'sr-only'}>{t('nav.menu')}</span>
                </button>
                <img src={branding.logo} alt={name} className={'hydra-topbar-logo'} />
            </header>
            {open && <div className={'hydra-backdrop'} onClick={() => setOpen(false)} />}
            <Sidebar sections={sections} open={open} onNavigate={() => setOpen(false)} />
            <main className={'hydra-main'}>{children}</main>
        </div>
    );
};
