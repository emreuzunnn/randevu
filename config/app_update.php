<?php

return [
    'ios' => [
        'minimum_version' => env('APP_UPDATE_IOS_MIN_VERSION', '1.0.0'),
        'latest_version' => env('APP_UPDATE_IOS_LATEST_VERSION', '1.0.0'),
        'store_url' => env('APP_UPDATE_IOS_STORE_URL', 'https://apps.apple.com/app/id6772962048'),
    ],
    'android' => [
        'minimum_version' => env('APP_UPDATE_ANDROID_MIN_VERSION', '1.0.0'),
        'latest_version' => env('APP_UPDATE_ANDROID_LATEST_VERSION', '1.0.0'),
        'store_url' => env(
            'APP_UPDATE_ANDROID_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.uzunsoft.tattoodesk'
        ),
    ],
    'message' => env(
        'APP_UPDATE_MESSAGE',
        'Tattoodesk uygulamasını kullanmaya devam etmek için güncellemeniz gerekiyor.'
    ),
];
