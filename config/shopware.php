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
    'order' => [
        'sale' => [
            'requirements' => [
                'status' => env('SHOPWARE_ORDER_SALE_REQ_STATUS', 0), // offen
                'cleared' => env('SHOPWARE_ORDER_SALE_REQ_CLEARED', 12), // komplett bezahlt
            ],
            'afterExportStatus' => env('SHOPWARE_ORDER_SALE_AFTER_EXPORT_STATUS', 5), // zur lieferung bereit
        ],
        'return' => [
            'requirements' => [
                'status' => env('SHOPWARE_ORDER_RETURN_REQ_STATUS', 4), // storniert abgelehnt
                'cleared' => env('SHOPWARE_ORDER_RETURN_REQ_CLEARED', 12), // komplett bezahlt
            ],
            'afterExportStatus' => env('SHOPWARE_ORDER_RETURN_AFTER_EXPORT_STATUS', 22), // retoure an intersys
        ],
        'branchNoAccounting' => env('SHOPWARE_BRANCH_ACCOUNTING'),
        'branchNoStock' => env('SHOPWARE_BRANCH_STOCK'),
    ],
];
