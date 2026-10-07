<?php

return [
    'demo_mode' => (bool) env('OSPM_DEMO_MODE', false),
    'payment_mode' => env('PAYMENT_MODE', 'demo'),
    'payment_provider' => env('PAYMENT_PROVIDER', 'demo'),
    'demo_password' => env('DEMO_DEFAULT_PASSWORD'),
    'name' => 'Osun State Park Management System',
    'currency' => env('OSPM_CURRENCY', 'NGN'),
    'timezone' => env('OSPM_TIMEZONE', 'Africa/Lagos'),
    'reference_prefix' => env('OSPM_REFERENCE_PREFIX', 'OSPM'),
    'branding' => [
        'primary' => env('OSPM_BRAND_PRIMARY', '#142a43'),
        'primary_dark' => env('OSPM_BRAND_PRIMARY_DARK', '#102237'),
        'secondary' => env('OSPM_BRAND_SECONDARY', '#235e91'),
        'government_logo' => env('OSPM_GOVERNMENT_LOGO'),
        'powered_by' => env('OSPM_POWERED_BY', 'Technology Solution by Pinnacle Tech Hub'),
    ],
];
