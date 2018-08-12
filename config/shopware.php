<?php
/**
 * lel since 11.08.18
 */

return [
    'baseUri' => env('SHOPWARE_BASE_URI'),
    'auth' => [
        'username' => env('SHOPWARE_AUTH_USERNAME'),
        'apiKey' => env('SHOPWARE_AUTH_APIKEY'),
    ],
    'branchesToImport' => env('SHOPWARE_BRANCHES_TO_IMPORT'),
];