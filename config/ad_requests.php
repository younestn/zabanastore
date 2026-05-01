<?php

return [
    'placements' => [
        'home_top' => [
            'label' => 'home_top',
            'legacy_ad_type' => 'banner',
        ],
        'home_middle' => [
            'label' => 'home_middle',
            'legacy_ad_type' => 'banner',
        ],
        'home_bottom' => [
            'label' => 'home_bottom',
            'legacy_ad_type' => 'banner',
        ],
        'product_details' => [
            'label' => 'product_details',
            'legacy_ad_type' => 'product',
        ],
        'store_page' => [
            'label' => 'store_page',
            'legacy_ad_type' => 'banner',
        ],
    ],
    'legacy_ad_types' => [
        'banner',
        'sidebar',
        'product',
        'popup',
        'email',
    ],
    'upload' => [
        'max_kb' => 5120,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'receipt_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    ],
    'payment_settings' => [
        'ad_payment_method_name' => '',
        'ad_payment_account_name' => '',
        'ad_payment_account_number' => '',
        'ad_payment_instructions' => '',
        'ad_default_price' => 0,
        'ad_currency' => 'DZD',
        'ad_receipt_required' => 1,
    ],
];
