import React from 'react';
import {
    BookOpenIcon,
    ChatIcon,
    DocumentTextIcon,
    GlobeAltIcon,
    HeartIcon,
    IdentificationIcon,
    InformationCircleIcon,
    LinkIcon,
    QuestionMarkCircleIcon,
    ScaleIcon,
    ShieldCheckIcon,
} from '@heroicons/react/outline';

type IconComponent = (props: React.ComponentProps<'svg'>) => JSX.Element;

const DiscordIcon: IconComponent = (props) => (
    <svg viewBox={'0 0 24 24'} fill={'currentColor'} {...props}>
        <path
            d={
                'M19.27 5.33A16.6 16.6 0 0 0 15.2 4.06a.06.06 0 0 0-.07.03c-.18.32-.38.73-.52 1.06a15.3 15.3 0 0 0-4.6 0 10.6 10.6 0 0 0-.53-1.06.06.06 0 0 0-.06-.03c-1.43.24-2.8.68-4.07 1.27a.06.06 0 0 0-.03.02C2.73 9.18 2.02 12.94 2.37 16.65a.07.07 0 0 0 .03.05 16.7 16.7 0 0 0 5.03 2.54.06.06 0 0 0 .07-.02c.39-.53.73-1.09 1.03-1.68a.06.06 0 0 0-.03-.09 11 11 0 0 1-1.57-.75.07.07 0 0 1-.01-.11l.31-.24a.06.06 0 0 1 .07-.01c3.29 1.5 6.85 1.5 10.1 0a.06.06 0 0 1 .07.01l.31.24a.07.07 0 0 1-.01.11c-.5.29-1.02.54-1.57.75a.06.06 0 0 0-.03.09c.3.59.65 1.15 1.03 1.68a.06.06 0 0 0 .07.02 16.6 16.6 0 0 0 5.04-2.54.07.07 0 0 0 .03-.05c.42-4.29-.7-8.02-2.98-11.32a.05.05 0 0 0-.03-.02ZM8.68 14.39c-.99 0-1.81-.91-1.81-2.02 0-1.12.8-2.03 1.81-2.03 1.02 0 1.83.92 1.81 2.03 0 1.11-.8 2.02-1.81 2.02Zm6.68 0c-.99 0-1.81-.91-1.81-2.02 0-1.12.8-2.03 1.81-2.03 1.02 0 1.83.92 1.81 2.03 0 1.11-.79 2.02-1.81 2.02Z'
            }
        />
    </svg>
);

const icons: Record<string, IconComponent> = {
    'book-open': BookOpenIcon,
    chat: ChatIcon,
    discord: DiscordIcon,
    'document-text': DocumentTextIcon,
    'globe-alt': GlobeAltIcon,
    heart: HeartIcon,
    identification: IdentificationIcon,
    'information-circle': InformationCircleIcon,
    link: LinkIcon,
    'question-mark-circle': QuestionMarkCircleIcon,
    scale: ScaleIcon,
    'shield-check': ShieldCheckIcon,
};

/**
 * Hydra: the icon an admin picked for a link in the sidebar.
 */
export const linkIcon = (name: string): IconComponent => icons[name] || DocumentTextIcon;
