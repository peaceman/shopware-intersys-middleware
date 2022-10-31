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
    'glnToImport' => env('SHOPWARE_GLN_TO_IMPORT'),
    'glnBranchMapping' => [],
    'order' => [
        'dailyOverviewRecipients' => env('SHOPWARE_ORDER_DAILY_OVERVIEW_RECIPIENTS', ''),
        'prePaymentId' => env('SHOPWARE_ORDER_PRE_PAYMENT_ID', 5),
        'cancelWaitingTimeInDays' => env('SHOPWARE_ORDER_CANCEL_WAITING_TIME_IN_DAYS', 14),
        'paymentStatus' => [
            'unpaid' => [env('SHOPWARE_ORDER_PAYMENT_STATUS_UNPAID_1', 17)],
        ],
        'sale' => [
            'requirements' => [
                [
                    'status' => env('SHOPWARE_ORDER_SALE_REQ_STATUS', 0), // offen
                    'cleared' => env('SHOPWARE_ORDER_SALE_REQ_CLEARED', 12), // komplett bezahlt
                ],
                [
                    'status' => env('SHOPWARE_ORDER_SALE_REQ_2_STATUS', 0), // offen
                    'cleared' => env('SHOPWARE_ORDER_SALE_REQ_2_CLEARED_2', 17), // offen
                    'paymentId' => env('SHOPWARE_ORDER_SALE_REQ_2_PAYMENT', 5), // vorkasse
                ]
            ],
            'afterExportStatus' => env('SHOPWARE_ORDER_SALE_AFTER_EXPORT_STATUS', 5), // zur lieferung bereit
        ],
        'return' => [
            'requirements' => [
                [
                    'status' => env('SHOPWARE_ORDER_RETURN_REQ_STATUS', 4), // storniert abgelehnt
                    'cleared' => env('SHOPWARE_ORDER_RETURN_REQ_CLEARED', 12), // komplett bezahlt
                ],
                [
                    'status' => env('SHOPWARE_ORDER_RETURN_REQ_STATUS', 4), // storniert abgelehnt
                    'cleared' => 17, // offen
                    'paymentId' => 5, // vorkasse
                ],
                [
                    'status' => env('SHOPWARE_ORDER_RETURN_REQ_STATUS', 4), // storniert abgelehnt
                    'cleared' => 20, // wiedergutschrift
                    'paymentId' => 7, // paypal
                ],
            ],
            'requiredPositionStatus' => env('SHOPWARE_ORDER_RETURN_REQ_POSITION_STATUS', 4), // retoure (export ausstehend),
            'afterExportStatus' => env('SHOPWARE_ORDER_RETURN_AFTER_EXPORT_STATUS', 22), // retoure an intersys
            'afterExportPositionStatus' => env('SHOPWARE_ORDER_RETURN_AFTER_EXPORT_POSITION_STATUS', 5), // retoure (exportiert)
        ],
        'branchNoAccounting' => env('SHOPWARE_BRANCH_ACCOUNTING'),
    ],
    'ignoreDeltaStockUpdates' => env('SHOPWARE_IGNORE_DELTA_STOCK_UPDATES', false),
];
