import React, { useState } from 'react';
import { Search as SearchIcon } from 'lucide-react';
import useEventListener from '@/plugins/useEventListener';
import SearchModal from '@/components/dashboard/search/SearchModal';
import { SidebarLink } from '@/components/hydra/Sidebar';
import { useTranslation } from 'react-i18next';
import { useAppTab } from '@/components/hydra/tabs/context';

export default () => {
    const [visible, setVisible] = useState(false);
    const { t } = useTranslation();
    const tab = useAppTab();

    useEventListener('keydown', (e: KeyboardEvent) => {
        if (
            tab.active &&
            ['input', 'textarea'].indexOf(((e.target as HTMLElement).tagName || 'input').toLowerCase()) < 0
        ) {
            if (!visible && e.metaKey && e.key.toLowerCase() === '/') {
                setVisible(true);
            }
        }
    });

    return (
        <>
            {visible && <SearchModal appear visible={visible} onDismissed={() => setVisible(false)} />}
            <SidebarLink
                item={{ key: 'search', name: t('nav.search'), icon: SearchIcon, onClick: () => setVisible(true) }}
            />
        </>
    );
};
