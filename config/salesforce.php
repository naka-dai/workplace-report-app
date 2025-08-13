<?php

return [
    'login_url'     => env('SF_LOGIN_URL', 'https://login.salesforce.com'),
    'audience'      => env('SF_AUDIENCE', 'https://login.salesforce.com'),
    'client_id'     => env('SF_CLIENT_ID'),
    'username'      => env('SF_USERNAME'),
    'private_key_b64' => env('SF_PRIVATE_KEY_B64'),
    'api_version'   => env('SF_API_VERSION', 'v61.0'),
    'defect_object' => env('SF_DEFECT_OBJECT', 'Defect_Report__c'),
    'max_photo_mb'  => (int) env('MAX_PHOTO_MB', 8),
];
