import React, { useEffect, useState } from 'react';
import { useLocation } from 'react-router';
import { MenuIcon, XIcon } from '@heroicons/react/outline';
import Sidebar, { SidebarSection } from '@/components/hydra/Sidebar';
import { useStoreState } from '@/state/hooks';

interface Props {
    sections: SidebarSection[];
    children: React.ReactNode;
}

/**
 * Page shell of the client area: floating sidebar on the left, content on the
 * right. On small screens the sidebar turns into an off-canvas menu.
 */
export default ({ sections, children }: Props) => {
    const location = useLocation();
    const [open, setOpen] = useState(false);
    const branding = useStoreState((state) => state.settings.data!.branding);
    const name = useStoreState((state) => state.settings.data!.name);

    useEffect(() => setOpen(false), [location.pathname]);

    return (
        <div className={'hydra-shell'}>
            <header className={'hydra-topbar'}>
                <button type={'button'} className={'hydra-icon-btn'} onClick={() => setOpen(!open)}>
                    {open ? <XIcon className={'hydra-nav-icon'} /> : <MenuIcon className={'hydra-nav-icon'} />}
                    <span className={'sr-only'}>Menu</span>
                </button>
                <img src={branding.logo} alt={name} className={'hydra-topbar-logo'} />
            </header>
            {open && <div className={'hydra-backdrop'} onClick={() => setOpen(false)} />}
            <Sidebar sections={sections} open={open} onNavigate={() => setOpen(false)} />
            <main className={'hydra-main'}>{children}</main>
        </div>
    );
};
