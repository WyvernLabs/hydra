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
    Code as CodeIcon,
    History as ClockIcon,
    KeyRound as KeyIcon,
    CircleUser as UserCircleIcon,
} from 'lucide-react';

// Each of the router files is already code split out appropriately — so
// all of the items above will only be loaded in when that router is loaded.
//
// These specific lazy loaded routes are to avoid loading in heavy screens
// for the server dashboard when they're only needed for specific instances.
const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));
const DatabaseManagerContainer = lazy(() => import('@/components/server/databases/DatabaseManagerContainer'));
const ServerDashboardContainer = lazy(() => import('@/components/server/dashboard/ServerDashboardContainer'));

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
    // Hydra: button of the server navigation this page belongs to (Design > Navigation).
    navKey: string;
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
            // Hydra: only reachable while the addon "Server Dashboard" is on.
            path: '/dashboard',
            navKey: 'dashboard',
            permission: null,
            name: 'routes.dashboard',
            component: ServerDashboardContainer,
        },
        {
            path: '/',
            navKey: 'console',
            permission: null,
            name: 'routes.console',
            component: ServerConsole,
            exact: true,
        },
        {
            path: '/files',
            navKey: 'files',
            permission: 'file.*',
            name: 'routes.files',
            component: FileManagerContainer,
        },
        {
            path: '/files/:action(edit|new)',
            navKey: 'files',
            permission: 'file.*',
            name: undefined,
            component: FileEditContainer,
        },
        {
            path: '/databases',
            navKey: 'databases',
            permission: 'database.*',
            name: 'routes.databases',
            component: DatabasesContainer,
        },
        {
            path: '/databases/:database',
            navKey: 'databases',
            permission: 'database.browse',
            name: undefined,
            component: DatabaseManagerContainer,
        },
        {
            path: '/schedules',
            navKey: 'schedules',
            permission: 'schedule.*',
            name: 'routes.schedules',
            component: ScheduleContainer,
        },
        {
            path: '/schedules/:id',
            navKey: 'schedules',
            permission: 'schedule.*',
            name: undefined,
            component: ScheduleEditContainer,
        },
        {
            path: '/users',
            navKey: 'users',
            permission: 'user.*',
            name: 'routes.users',
            component: UsersContainer,
        },
        {
            path: '/backups',
            navKey: 'backups',
            permission: 'backup.*',
            name: 'routes.backups',
            component: BackupContainer,
        },
        {
            path: '/network',
            navKey: 'network',
            permission: 'allocation.*',
            name: 'routes.network',
            component: NetworkContainer,
        },
        {
            path: '/startup',
            navKey: 'startup',
            permission: 'startup.*',
            name: 'routes.startup',
            component: StartupContainer,
        },
        {
            path: '/settings',
            navKey: 'settings',
            permission: ['settings.*', 'file.sftp'],
            name: 'routes.settings',
            component: SettingsContainer,
        },
        {
            path: '/activity',
            navKey: 'activity',
            permission: 'activity.*',
            name: 'routes.activity',
            component: ServerActivityLogContainer,
        },
    ],
} as Routes;
