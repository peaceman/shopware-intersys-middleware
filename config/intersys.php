<?php
/**
 * lel since 12.08.18
 */

return [
    'folder' => [
        'stock' => env('INTERSYS_FOLDER_STOCK'),
        'order' => env('INTERSYS_FOLDER_ORDER'),
    ],
    'orderExport' => [
        'file' => [
            'numberPrefix' => env('INTERSYS_ORDER_EXPORT_NUMBER_PREFIX')
        ],
    ],
];
