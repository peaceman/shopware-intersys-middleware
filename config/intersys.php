<?php
/**
 * lel since 12.08.18
 */

return [
    'folder' => [
        'base' => env('INTERSYS_FOLDER_BASE'),
        'delta' => env('INTERSYS_FOLDER_DELTA'),
        'order' => env('INTERSYS_FOLDER_ORDER'),
    ],
    'orderExport' => [
        'file' => [
            'numberPrefix' => env('INTERSYS_ORDER_EXPORT_NUMBER_PREFIX')
        ],
    ],
];
