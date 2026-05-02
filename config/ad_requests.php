<?php

return [
    'placements' => [
        'featured_products' => [
            'label' => 'featured_products',
            'legacy_ad_type' => 'product',
            'is_vendor_selectable' => true,
            'is_admin_only' => false,
            'description' => 'ad_appears_first_in_featured_products',
        ],
        'home_banner_classic' => [
            'label' => 'classic_home_banner',
            'legacy_ad_type' => 'banner',
            'is_vendor_selectable' => false,
            'is_admin_only' => true,
            'description' => 'classic_home_banner',
        ],
        'home_top' => [
            'label' => 'home_top',
            'legacy_ad_type' => 'banner',
            'is_vendor_selectable' => false,
            'is_legacy' => true,
        ],
        'home_middle' => [
            'label' => 'home_middle',
            'legacy_ad_type' => 'banner',
            'is_vendor_selectable' => false,
            'is_legacy' => true,
        ],
        'home_bottom' => [
            'label' => 'home_bottom',
            'legacy_ad_type' => 'banner',
            'is_vendor_selectable' => false,
            'is_legacy' => true,
        ],
        'product_details' => [
            'label' => 'product_details',
            'legacy_ad_type' => 'product',
            'is_vendor_selectable' => false,
            'is_legacy' => true,
        ],
        'store_page' => [
            'label' => 'store_page',
            'legacy_ad_type' => 'banner',
            'is_vendor_selectable' => false,
            'is_legacy' => true,
        ],
    ],
    'vendor_placements' => [
        'featured_products' => [
            'label' => 'featured_products',
            'is_vendor_selectable' => true,
        ],
    ],
    'admin_only_placements' => [
        'home_banner_classic' => [
            'label' => 'classic_home_banner',
            'is_vendor_selectable' => false,
        ],
    ],
    'default_vendor_placement' => 'featured_products',
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
