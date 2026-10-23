<?php

return [
    'currency' => env('APP_CURRENCY', 'IDR'),
    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Asia/Jakarta'),
    'initial_branch_mode' => 'single',
    'pos_requires_online_connection' => true,
    'costing_method' => 'moving_weighted_average',
    'allow_negative_stock' => false,
    'purchasing' => [
        'require_supplier_product_mapping' => false,
    ],
];
