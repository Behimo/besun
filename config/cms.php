<?php

return [
    'site_name' => env('CMS_SITE_NAME', 'BISAN Holding'),
    'site_name_fa' => env('CMS_SITE_NAME_FA', 'بیسان'),
    'default_og_image' => env('CMS_OG_IMAGE', '/favicon.svg'),
    'twitter_handle' => env('CMS_TWITTER', '@bisan_ir'),
    'contact_email' => env('CMS_CONTACT_EMAIL', 'info@bisan.ir'),
    'contact_phone' => env('CMS_CONTACT_PHONE'),
    'social' => [
        'linkedin' => env('CMS_SOCIAL_LINKEDIN'),
        'telegram' => env('CMS_SOCIAL_TELEGRAM'),
        'instagram' => env('CMS_SOCIAL_INSTAGRAM'),
    ],
    'admin_email' => env('CMS_ADMIN_EMAIL', 'admin@bisan.ir'),
    'admin_password' => env('CMS_ADMIN_PASSWORD'),
];
