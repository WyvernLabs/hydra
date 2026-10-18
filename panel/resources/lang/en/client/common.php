<?php

/*
 * Shared strings of the client area (React). Loaded as i18next namespace "client/common".
 * Placeholders use the Laravel syntax (:name) and are converted to {{name}} for the browser.
 */
return [
    'error' => 'Error',
    'success' => 'Success',
    'loading' => 'Loading…',
    'unknown' => 'Unknown',
    'unlimited' => 'Unlimited',
    'none' => 'None',
    'yes' => 'Yes',
    'no' => 'No',

    'actions' => [
        'save' => 'Save',
        'saving' => 'Saving…',
        'cancel' => 'Cancel',
        'close' => 'Close',
        'okay' => 'Okay',
        'confirm' => 'Confirm',
        'continue' => 'Continue',
        'create' => 'Create',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'update' => 'Update',
        'remove' => 'Remove',
        'retry' => 'Retry',
        'back' => 'Go back',
        'copy' => 'Copy',
        'download' => 'Download',
        'submit' => 'Submit',
    ],

    'nav' => [
        'menu' => 'Menu',
        'workspace' => 'Workspace',
        'search' => 'Search',
        'dashboard' => 'Dashboard',
        'administration' => 'Administration',
        'account' => 'Account',
        'server' => 'Server',
        'manage_as_admin' => 'Manage as Admin',
        'sign_out' => 'Sign Out',
    ],

    // Names of the pages in the sidebar (routers/routes.ts).
    'routes' => [
        'account' => 'Account',
        'api' => 'API Credentials',
        'ssh' => 'SSH Keys',
        'activity' => 'Activity',
        'console' => 'Console',
        'files' => 'Files',
        'databases' => 'Databases',
        'schedules' => 'Schedules',
        'users' => 'Users',
        'backups' => 'Backups',
        'network' => 'Network',
        'startup' => 'Startup',
        'settings' => 'Settings',
    ],

    'errors' => [
        'generic_title' => 'Something went wrong',
        'not_found' => 'The requested resource was not found.',
        'access_denied' => 'Access Denied',
        'no_permission' => 'You do not have permission to access this page.',
        'render' => 'An error was encountered by the application while rendering this view. Try refreshing the page.',
    ],

    'clipboard' => [
        'copied_value' => 'Copied ":value" to clipboard.',
        'copied' => 'Copied text to clipboard.',
    ],

    'pagination' => [
        'summary' => 'Showing <bold>:start</bold> to <bold>:end</bold> of <bold>:total</bold> results.',
    ],

    'activity' => [
        'system' => 'System',
        'system_user' => 'System User',
        'using_api_key' => 'Using API Key',
        'using_sftp' => 'Using SFTP',
        'metadata' => 'Metadata',
        'view_metadata' => 'View additional event metadata',
        'empty' => 'No activity logs available for this account.',
        'empty_server' => 'No activity logs available for this server.',
        'filter_event' => 'Event',
        'clear_filter' => 'Clear filter',
    ],

    // Default messages for form validation (yup).
    'validation' => [
        'required' => 'This field is required.',
        'email' => 'Please enter a valid email address.',
        'string_min' => 'Must be at least :min characters long.',
        'string_max' => 'May not be longer than :max characters.',
        'number' => 'Must be a number.',
        'number_min' => 'Must be at least :min.',
        'number_max' => 'May not be greater than :max.',
        'invalid' => 'This value is invalid.',
    ],

    'units' => [
        'mib' => 'MiB',
        'percent' => '%',
    ],
];
