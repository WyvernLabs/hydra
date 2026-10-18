import React, { lazy } from 'react';
import ServerConsole from '@/components/server/console/ServerConsoleContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import StartupContainer from '@/components/server/startup/StartupContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import SettingsContainer from '@/components/server/settings/SettingsContainer';
import AccountOverviewContainer from '@/components/dashboard/AccountOverviewContainer';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import ActivityLogContainer from '@/components/dashboard/activity/ActivityLogContainer';
import ServerActivityLogContainer from '@/components/server/ServerActivityLogContainer';
import {
    ArchiveIcon,
    CalendarIcon,
    ClockIcon,
    CodeIcon,
    CogIcon,
    DatabaseIcon,
    FolderIcon,
    GlobeAltIcon,
    KeyIcon,
    PlayIcon,
    TerminalIcon,
    UserCircleIcon,
    UsersIcon,
} from '@heroicons/react/outline';

// Each of the router files is already code split out appropriately — so
// all of the items above will only be loaded in when that router is loaded.
//
// These specific lazy loaded routes are to avoid loading in heavy screens
// for the server dashboard when they're only needed for specific instances.
const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));

interface RouteDefinition {
    path: string;
    // Translation key (namespace "client/common" unless given as "ns:key"). If undefined
    // is passed this route is still rendered into the router itself but no navigation
    // link is displayed in the sidebar.
    name: string | undefined;
    component: React.ComponentType;
    exact?: boolean;
    // Icon shown next to the name in the Hydra sidebar.
    icon?: React.ComponentType<{ className?: string }>;
}

interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
}

interface Routes {
    // All of the routes available under "/account"
    account: RouteDefinition[];
    // All of the routes available under "/server/:id"
    server: ServerRouteDefinition[];
}

export default {
    account: [
        {
            path: '/',
            name: 'routes.account',
            icon: UserCircleIcon,
            component: AccountOverviewContainer,
            exact: true,
        },
        {
            path: '/api',
            name: 'routes.api',
            icon: CodeIcon,
            component: AccountApiContainer,
        },
        {
            path: '/ssh',
            name: 'routes.ssh',
            icon: KeyIcon,
            component: AccountSSHContainer,
        },
        {
            path: '/activity',
            name: 'routes.activity',
            icon: ClockIcon,
            component: ActivityLogContainer,
        },
    ],
    server: [
        {
            path: '/',
            permission: null,
            name: 'routes.console',
            icon: TerminalIcon,
            component: ServerConsole,
            exact: true,
        },
        {
            path: '/files',
            permission: 'file.*',
            name: 'routes.files',
            icon: FolderIcon,
            component: FileManagerContainer,
        },
        {
            path: '/files/:action(edit|new)',
            permission: 'file.*',
            name: undefined,
            component: FileEditContainer,
        },
        {
            path: '/databases',
            permission: 'database.*',
            name: 'routes.databases',
            icon: DatabaseIcon,
            component: DatabasesContainer,
        },
        {
            path: '/schedules',
            permission: 'schedule.*',
            name: 'routes.schedules',
            icon: CalendarIcon,
            component: ScheduleContainer,
        },
        {
            path: '/schedules/:id',
            permission: 'schedule.*',
            name: undefined,
            component: ScheduleEditContainer,
        },
        {
            path: '/users',
            permission: 'user.*',
            name: 'routes.users',
            icon: UsersIcon,
            component: UsersContainer,
        },
        {
            path: '/backups',
            permission: 'backup.*',
            name: 'routes.backups',
            icon: ArchiveIcon,
            component: BackupContainer,
        },
        {
            path: '/network',
            permission: 'allocation.*',
            name: 'routes.network',
            icon: GlobeAltIcon,
            component: NetworkContainer,
        },
        {
            path: '/startup',
            permission: 'startup.*',
            name: 'routes.startup',
            icon: PlayIcon,
            component: StartupContainer,
        },
        {
            path: '/settings',
            permission: ['settings.*', 'file.sftp'],
            name: 'routes.settings',
            icon: CogIcon,
            component: SettingsContainer,
        },
        {
            path: '/activity',
            permission: 'activity.*',
            name: 'routes.activity',
            icon: ClockIcon,
            component: ServerActivityLogContainer,
        },
    ],
} as Routes;
