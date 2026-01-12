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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'cloudinary' => [
        'cloud_url' => env('CLOUDINARY_URL', sprintf(
            'cloudinary://%s:%s@%s',
            env('CLOUDINARY_KEY'),
            env('CLOUDINARY_SECRET'),
            env('CLOUDINARY_CLOUD_NAME')
        )),
        'upload_preset' => env('CLOUDINARY_UPLOAD_PRESET'),
        'blanes_folder' => env('CLOUDINARY_BLANES_FOLDER', 'blanes/videos'),
    ],

    'bunny' => [
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'api_key' => env('BUNNY_API_KEY'),
        'cdn_url' => env('BUNNY_CDN_URL'), // e.g., https://daba-blane.b-cdn.net
        'storage_endpoint' => env('BUNNY_STORAGE_ENDPOINT', 'storage.bunnycdn.com'), // Regional endpoint, e.g., jh.storage.bunnycdn.com
        'blanes_folder' => env('BUNNY_BLANES_FOLDER', 'blanes'),
    ],

];
