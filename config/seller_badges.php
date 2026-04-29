<?php

return [
    'business_setting_key' => 'seller_badge_settings',

    'weights' => [
        'completion_rate' => 30,
        'low_cancellation_rate' => 20,
        'low_delay_rate' => 15,
        'average_rating' => 15,
        'sales_volume' => 10,
        'account_age' => 5,
        'document_verification' => 5,
    ],

    'minimum_orders_for_scoring' => 3,
    'sales_volume_target_orders' => 50,
    'account_age_target_days' => 180,
    'completed_status' => 'delivered',
    'cancelled_statuses' => ['canceled', 'failed', 'returned'],

    'thresholds' => [
        'rising_seller' => [
            'score' => 50,
        ],
        'verified_seller' => [
            'score' => 65,
            'requires_documents' => true,
        ],
        'trusted_seller' => [
            'score' => 80,
            'requires_documents' => true,
            'max_cancellation_rate' => 10,
            'min_average_rating' => 4,
        ],
        'elite_seller' => [
            'score' => 92,
            'requires_documents' => true,
            'min_completed_orders' => 50,
            'max_delay_rate' => 5,
            'min_average_rating' => 4.7,
        ],
    ],

    'new_seller' => [
        'min_published_products' => 1,
        'max_orders_before_scoring' => 2,
    ],

    'badges' => [
        'new_seller' => [
            'translation_key' => 'new_seller',
            'description_key' => 'new_seller_badge_description',
            'icon' => 'sparkles',
            'color' => '#0ea5e9',
            'level' => 0,
        ],
        'rising_seller' => [
            'translation_key' => 'rising_seller',
            'description_key' => 'rising_seller_badge_description',
            'icon' => 'seedling',
            'color' => '#22c55e',
            'level' => 1,
        ],
        'verified_seller' => [
            'translation_key' => 'verified_seller',
            'description_key' => 'verified_seller_badge_description',
            'icon' => 'badge-check',
            'color' => '#2563eb',
            'level' => 2,
        ],
        'trusted_seller' => [
            'translation_key' => 'trusted_seller',
            'description_key' => 'trusted_seller_badge_description',
            'icon' => 'shield-check',
            'color' => '#0891b2',
            'level' => 3,
        ],
        'elite_seller' => [
            'translation_key' => 'elite_seller',
            'description_key' => 'elite_seller_badge_description',
            'icon' => 'crown',
            'color' => '#d97706',
            'level' => 4,
        ],
    ],
];
