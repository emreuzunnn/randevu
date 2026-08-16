<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    'openai_vision' => [
        'model' => env('OPENAI_VISION_MODEL', 'gpt-4.1-mini'),
    ],

    'firebase' => [
        'project_id'       => env('FIREBASE_PROJECT_ID', 'tattoodesk-3390d'),
        'credentials_path' => env('FIREBASE_CREDENTIALS'),
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        'client_email'    => env('FIREBASE_CLIENT_EMAIL'),
        'private_key'     => env('FIREBASE_PRIVATE_KEY'),
        'web_vapid_key'   => env('FIREBASE_WEB_VAPID_KEY'),
        'web' => [
            'apiKey'            => env('FIREBASE_WEB_API_KEY', 'AIzaSyBgzMo3I1MEQ_BlZUQy8rq8rfPl6NIUFTs'),
            'authDomain'        => env('FIREBASE_WEB_AUTH_DOMAIN', 'tattoodesk-3390d.firebaseapp.com'),
            'projectId'         => env('FIREBASE_WEB_PROJECT_ID', 'tattoodesk-3390d'),
            'storageBucket'     => env('FIREBASE_WEB_STORAGE_BUCKET', 'tattoodesk-3390d.firebasestorage.app'),
            'messagingSenderId' => env('FIREBASE_WEB_MESSAGING_SENDER_ID', '391315089383'),
            'appId'             => env('FIREBASE_WEB_APP_ID', '1:391315089383:web:9578be319024c273bfff42'),
            'measurementId'     => env('FIREBASE_WEB_MEASUREMENT_ID', 'G-ME4PNK55YY'),
        ],
    ],

    'whatsapp' => [
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'validate_signature' => env('WHATSAPP_VALIDATE_SIGNATURE', false),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v23.0'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'tr'),
        'auto_reply_enabled' => env('WHATSAPP_AUTO_REPLY_ENABLED', true),
        'auto_reply_message' => env(
            'WHATSAPP_AUTO_REPLY_MESSAGE',
            'Bu numara otomatik bilgilendirme amaçlıdır. Lütfen bu mesaja cevap vermeyiniz.'
        ),
        'templates' => [
            'appointment_created' => env('WHATSAPP_TEMPLATE_APPOINTMENT_CREATED', 'musteri_hatirlatma_tr'),
            'appointment_reminder' => env('WHATSAPP_TEMPLATE_APPOINTMENT_REMINDER', 'mteri_randevu_hatrlatma'),
        ],
    ],

];
