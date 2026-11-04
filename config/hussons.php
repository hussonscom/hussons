<?php

return [
    'locales' => ['az', 'ru', 'en'],

    'default_currency' => env('HUSSONS_CURRENCY', 'AZN'),

    'expiry_warning_days' => (int) env('EXPIRY_WARNING_DAYS', 60),

    'industries' => [
        'electronics' => 'Electronics',
        'pharmacy' => 'Pharmacy',
        'restaurant' => 'Restaurant & Cafe',
        'retail' => 'Universal Retail',
    ],

    'roles' => [
        'owner',
        'admin',
        'branch_manager',
        'cashier',
        'warehouse',
        'pharmacist',
        'kitchen',
        'auditor',
        'viewer',
    ],
];
