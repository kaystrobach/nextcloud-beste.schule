<?php
declare(strict_types=1);

return [
    'routes' => [
        // Page routes (HTML views)
        ['name' => 'grades#index',  'url' => '/',         'verb' => 'GET'],
        ['name' => 'grades#show',   'url' => '/grades',   'verb' => 'GET'],
        ['name' => 'admin#index',   'url' => '/admin',    'verb' => 'GET'],
    ],
    'ocs' => [
        // Account management
        ['name' => 'api#getAccounts',    'url' => '/api/v1/accounts',                'verb' => 'GET'],
        ['name' => 'api#createAccount',  'url' => '/api/v1/accounts',                'verb' => 'POST'],
        ['name' => 'api#deleteAccount',  'url' => '/api/v1/accounts/{id}',           'verb' => 'DELETE'],
        ['name' => 'api#updateAccount',  'url' => '/api/v1/accounts/{id}',           'verb' => 'PUT'],

        // Admin: manage accounts for any user (admin only)
        ['name' => 'api#adminGetAccounts',   'url' => '/api/v1/admin/accounts',           'verb' => 'GET'],
        ['name' => 'api#adminCreateAccount', 'url' => '/api/v1/admin/accounts',           'verb' => 'POST'],
        ['name' => 'api#adminDeleteAccount', 'url' => '/api/v1/admin/accounts/{id}',      'verb' => 'DELETE'],
        ['name' => 'api#adminSyncAccount',   'url' => '/api/v1/admin/accounts/{id}/sync', 'verb' => 'POST'],

        // Grades data
        ['name' => 'api#getGrades',      'url' => '/api/v1/grades',                  'verb' => 'GET'],
        ['name' => 'api#getFinalGrades', 'url' => '/api/v1/finalgrades',             'verb' => 'GET'],

        // Validate token against beste.schule API
        ['name' => 'api#validateToken',  'url' => '/api/v1/validate',                'verb' => 'POST'],

        // Trigger manual sync for own account
        ['name' => 'api#syncAccount',    'url' => '/api/v1/accounts/{id}/sync',      'verb' => 'POST'],

        // Calendars
        ['name' => 'api#getCalendars',   'url' => '/api/v1/calendars',               'verb' => 'GET'],

        // Sync Logs
        ['name' => 'api#getLogs',        'url' => '/api/v1/accounts/{id}/logs',      'verb' => 'GET'],
    ],
];
