<?php

return [
    'stripe_price_id' => env('SAKUSAKU_STRIPE_PRICE_ID'),
    'trial_days' => 30,
    'image_max_width' => 1600,
    'image_max_size' => 20 * 1024 * 1024, // 20MB
    'tag_max_count' => 10,
    'tag_min_score' => 0.1,
];
