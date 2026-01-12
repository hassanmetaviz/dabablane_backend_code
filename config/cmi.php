<?php

return [
    // 'merchant_id' => env('CMI_MERCHANT_ID'),
    'client_id' => env('CMI_CLIENT_ID'),
    'store_key' => env('CMI_STORE_KEY'),
    // 'api_key' => env('CMI_API_KEY'),
    'secret_key' => env('CMI_SECRET_KEY'),
    'sandbox' => env('CMI_SANDBOX', true),
    'base_uri' => env('CMI_BASE_URI', 'https://testpayment.cmi.co.ma/fim/est3Dgate'),
    'ok_url' => env('CMI_OK_URL', 'your_ok_url'),
    'ok_fail_url' => env('CMI_OK_FAIL_URL', 'your_ok_fail_url'),
    'fail_url' => env('CMI_FAIL_URL', 'your_fail_url'),
    'shop_url' => env('CMI_SHOP_URL', 'your_shop_url'),
    'callback_url' => env('CMI_CALLBACK_URL', 'your_callback_url'),
    'default_lang' => env('CMI_DEFAULT_LANG', 'fr'),
    'encoding' => env('CMI_ENCODING', 'UTF-8'),
];
