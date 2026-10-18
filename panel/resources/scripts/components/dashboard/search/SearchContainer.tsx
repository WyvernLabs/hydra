import React, { useState } from 'react';
import { SearchIcon } from '@heroicons/react/outline';
import useEventListener from '@/plugins/useEventListener';
import SearchModal from '@/components/dashboard/search/SearchModal';
import { SidebarLink } from '@/components/hydra/Sidebar';

export default () => {
    const [visible, setVisible] = useState(false);

    useEventListener('keydown', (e: KeyboardEvent) => {
        if (['input', 'textarea'].indexOf(((e.target as HTMLElement).tagName || 'input').toLowerCase()) < 0) {
            if (!visible && e.metaKey && e.key.toLowerCase() === '/') {
                setVisible(true);
            }
        }
    });

    return (
        <>
            {visible && <SearchModal appear visible={visible} onDismissed={() => setVisible(false)} />}
            <SidebarLink item={{ key: 'search', name: 'Search', icon: SearchIcon, onClick: () => setVisible(true) }} />
        </>
    );
};
