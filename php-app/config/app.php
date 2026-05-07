<?php

return [
    'name'    => getenv('APP_NAME') ?: 'Connect IT',
    'env'     => getenv('APP_ENV') ?: 'production',
    'url'     => getenv('APP_URL') ?: 'http://localhost/php-app',
    'debug'   => getenv('APP_DEBUG') === 'true',
    'key'     => getenv('APP_KEY') ?: 'default_insecure_key_change_me',

    'session' => [
        'lifetime' => (int)(getenv('SESSION_LIFETIME') ?: 86400),
        'name'     => getenv('SESSION_NAME') ?: 'connectit_session',
    ],

    'upload' => [
        'max_size' => (int)(getenv('UPLOAD_MAX_SIZE') ?: 10485760),
        'path'     => getenv('UPLOAD_PATH') ?: 'public/uploads',
        'allowed'  => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
    ],

    'roles' => [
        'user'              => 'User',
        'agent'             => 'Agent',
        'admin'             => 'Admin',
        'super_admin'       => 'Super Admin',
        'ultra_super_admin' => 'Ultra Super Admin',
    ],

    'ticket_statuses' => [
        'New', 'In Progress', 'On Hold', 'Resolved', 'Closed', 'Canceled', 'Pending Approval',
    ],

    'ticket_priorities' => [
        '1 - Critical', '2 - High', '3 - Moderate', '4 - Low',
    ],

    'ticket_categories' => [
        'Software', 'Hardware', 'Network', 'Database', 'Security',
        'Access', 'Email', 'Printing', 'Other',
    ],
];
