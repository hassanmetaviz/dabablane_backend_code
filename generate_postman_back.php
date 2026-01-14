<?php
/**
 * Script to add missing endpoints to collectionpostmanBack.json
 */

$filePath = __DIR__ . '/collectionpostmanBack.json';
$collection = json_decode(file_get_contents($filePath), true);

// Find the "Back" item array
$backIndex = null;
foreach ($collection['item'] as $index => $item) {
    if ($item['name'] === 'Back') {
        $backIndex = $index;
        break;
    }
}

if ($backIndex === null) {
    die("Back folder not found in collection\n");
}

// Helper function to create a GET endpoint
function createGetEndpoint($name, $path, $queryParams = [], $responseBody = '{}', $pathVars = []) {
    $pathParts = explode('/', ltrim($path, '/'));
    $endpoint = [
        'name' => $name,
        'request' => [
            'method' => 'GET',
            'header' => [
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}']
            ],
            'url' => [
                'raw' => 'http://your-api-url/' . ltrim($path, '/') . ($queryParams ? '?' . http_build_query($queryParams) : ''),
                'protocol' => 'http',
                'host' => ['your-api-url'],
                'path' => $pathParts
            ]
        ],
        'response' => [
            [
                'name' => 'Example Response',
                'status' => 'OK',
                'code' => 200,
                'body' => $responseBody
            ]
        ]
    ];

    if ($queryParams) {
        $endpoint['request']['url']['query'] = array_map(function($key, $value) {
            return ['key' => $key, 'value' => $value];
        }, array_keys($queryParams), array_values($queryParams));
    }

    if ($pathVars) {
        $endpoint['request']['url']['variable'] = $pathVars;
    }

    return $endpoint;
}

// Helper function to create a POST endpoint
function createPostEndpoint($name, $path, $body = '{}', $responseBody = '{}', $pathVars = []) {
    $pathParts = explode('/', ltrim($path, '/'));
    $endpoint = [
        'name' => $name,
        'request' => [
            'method' => 'POST',
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}']
            ],
            'body' => [
                'mode' => 'raw',
                'raw' => $body
            ],
            'url' => [
                'raw' => 'http://your-api-url/' . ltrim($path, '/'),
                'protocol' => 'http',
                'host' => ['your-api-url'],
                'path' => $pathParts
            ]
        ],
        'response' => [
            [
                'name' => 'Example Response',
                'status' => 'Created',
                'code' => 201,
                'body' => $responseBody
            ]
        ]
    ];

    if ($pathVars) {
        $endpoint['request']['url']['variable'] = $pathVars;
    }

    return $endpoint;
}

// Helper function to create a PUT endpoint
function createPutEndpoint($name, $path, $body = '{}', $responseBody = '{}', $pathVars = []) {
    $pathParts = explode('/', ltrim($path, '/'));
    $endpoint = [
        'name' => $name,
        'request' => [
            'method' => 'PUT',
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}']
            ],
            'body' => [
                'mode' => 'raw',
                'raw' => $body
            ],
            'url' => [
                'raw' => 'http://your-api-url/' . ltrim($path, '/'),
                'protocol' => 'http',
                'host' => ['your-api-url'],
                'path' => $pathParts
            ]
        ],
        'response' => [
            [
                'name' => 'Example Response',
                'status' => 'OK',
                'code' => 200,
                'body' => $responseBody
            ]
        ]
    ];

    if ($pathVars) {
        $endpoint['request']['url']['variable'] = $pathVars;
    }

    return $endpoint;
}

// Helper function to create a PATCH endpoint
function createPatchEndpoint($name, $path, $body = '{}', $responseBody = '{}', $pathVars = []) {
    $pathParts = explode('/', ltrim($path, '/'));
    $endpoint = [
        'name' => $name,
        'request' => [
            'method' => 'PATCH',
            'header' => [
                ['key' => 'Content-Type', 'value' => 'application/json'],
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}']
            ],
            'body' => [
                'mode' => 'raw',
                'raw' => $body
            ],
            'url' => [
                'raw' => 'http://your-api-url/' . ltrim($path, '/'),
                'protocol' => 'http',
                'host' => ['your-api-url'],
                'path' => $pathParts
            ]
        ],
        'response' => [
            [
                'name' => 'Example Response',
                'status' => 'OK',
                'code' => 200,
                'body' => $responseBody
            ]
        ]
    ];

    if ($pathVars) {
        $endpoint['request']['url']['variable'] = $pathVars;
    }

    return $endpoint;
}

// Helper function to create a DELETE endpoint
function createDeleteEndpoint($name, $path, $pathVars = []) {
    $pathParts = explode('/', ltrim($path, '/'));
    $endpoint = [
        'name' => $name,
        'request' => [
            'method' => 'DELETE',
            'header' => [
                ['key' => 'Authorization', 'value' => 'Bearer {{token}}']
            ],
            'url' => [
                'raw' => 'http://your-api-url/' . ltrim($path, '/'),
                'protocol' => 'http',
                'host' => ['your-api-url'],
                'path' => $pathParts
            ]
        ],
        'response' => [
            [
                'name' => 'Example Response',
                'status' => 'No Content',
                'code' => 204,
                'body' => ''
            ]
        ]
    ];

    if ($pathVars) {
        $endpoint['request']['url']['variable'] = $pathVars;
    }

    return $endpoint;
}

// New categories to add
$newCategories = [];

// 1. Authentication Testing
$newCategories[] = [
    'name' => 'Authentication Testing',
    'item' => [
        createPostEndpoint('Register', 'api/register',
            json_encode(['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'User registered successfully', 'data' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Login', 'api/login',
            json_encode(['email' => 'john@example.com', 'password' => 'password123'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Login successful', 'data' => ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Logout', 'api/logout', '{}',
            json_encode(['message' => 'Logged out successfully'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Mobile Signup', 'api/signup',
            json_encode(['name' => 'Mobile User', 'email' => 'mobile@example.com', 'password' => 'password123', 'phone' => '+1234567890'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Signup successful', 'data' => ['id' => 1, 'name' => 'Mobile User'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Mobile Login', 'api/signin',
            json_encode(['email' => 'mobile@example.com', 'password' => 'password123'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Login successful', 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Social Login', 'api/socialLogin',
            json_encode(['provider' => 'google', 'token' => 'google_oauth_token'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Login successful', 'data' => ['id' => 1, 'name' => 'Social User'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        )
    ]
];

// 2. Vendor Auth Testing
$newCategories[] = [
    'name' => 'Vendor Auth Testing',
    'item' => [
        createPostEndpoint('Vendor Signup', 'api/vendorSignup',
            json_encode(['company_name' => 'My Company', 'email' => 'vendor@example.com', 'password' => 'password123', 'phone' => '+1234567890', 'address' => '123 Main St'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor registered successfully', 'data' => ['id' => 1, 'company_name' => 'My Company'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Vendor Login', 'api/vendorSignin',
            json_encode(['email' => 'vendor@example.com', 'password' => 'password123'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Login successful', 'data' => ['id' => 1, 'company_name' => 'My Company'], 'token' => 'eyJ...'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Forgot Vendor Password', 'api/forgotVendorPassword',
            json_encode(['email' => 'vendor@example.com'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Password reset link sent to your email'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Check Vendor Created By Admin', 'api/checkVendorCreatedByAdmin',
            json_encode(['email' => 'vendor@example.com'], JSON_PRETTY_PRINT),
            json_encode(['created_by_admin' => true, 'needs_password_setup' => true], JSON_PRETTY_PRINT)
        )
    ]
];

// 3. User Profile Testing
$newCategories[] = [
    'name' => 'User Profile Testing',
    'item' => [
        createPutEndpoint('Update Profile', 'api/updateProfile',
            json_encode(['name' => 'Updated Name', 'email' => 'updated@example.com', 'phone' => '+1234567890'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Profile updated successfully', 'data' => ['id' => 1, 'name' => 'Updated Name']], JSON_PRETTY_PRINT)
        ),
        createDeleteEndpoint('Delete Account', 'api/deleteAccount')
    ]
];

// 4. Vendor Profile Testing
$newCategories[] = [
    'name' => 'Vendor Profile Testing',
    'item' => [
        createPostEndpoint('Update Vendor', 'api/updateVendor',
            json_encode(['company_name' => 'Updated Company', 'phone' => '+1234567890', 'address' => '456 New St'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor updated successfully', 'data' => ['id' => 1, 'company_name' => 'Updated Company']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Vendor Password', 'api/updateVendorPassword',
            json_encode(['current_password' => 'oldpassword', 'password' => 'newpassword', 'password_confirmation' => 'newpassword'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Password updated successfully'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Set Vendor Password', 'api/setVendorPassword',
            json_encode(['password' => 'newpassword', 'password_confirmation' => 'newpassword'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Password set successfully'], JSON_PRETTY_PRINT)
        )
    ]
];

// 5. Admin Vendor Testing
$newCategories[] = [
    'name' => 'Admin Vendor Testing',
    'item' => [
        createPostEndpoint('Create Vendor By Admin', 'api/admin/createVendor',
            json_encode(['company_name' => 'New Vendor', 'email' => 'newvendor@example.com', 'phone' => '+1234567890'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor created successfully', 'data' => ['id' => 1, 'company_name' => 'New Vendor']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Vendor By Admin', 'api/admin/updateVendor/:vendorId',
            json_encode(['company_name' => 'Updated Vendor', 'status' => 'active'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor updated successfully'], JSON_PRETTY_PRINT),
            [['key' => 'vendorId', 'value' => '1']]
        ),
        createPostEndpoint('Reset Vendor Password By Admin', 'api/admin/resetVendorPassword/:vendorId', '{}',
            json_encode(['message' => 'Password reset email sent'], JSON_PRETTY_PRINT),
            [['key' => 'vendorId', 'value' => '1']]
        ),
        createGetEndpoint('Get All Vendors', 'api/getAllVendors',
            ['paginationSize' => '10', 'search' => ''],
            json_encode(['data' => [['id' => 1, 'company_name' => 'Vendor 1', 'email' => 'vendor1@example.com', 'status' => 'active']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Vendor By ID or Company Name', 'api/getVendorByIdOrCompanyName',
            ['search' => 'vendor'],
            json_encode(['data' => ['id' => 1, 'company_name' => 'Vendor 1']], JSON_PRETTY_PRINT)
        ),
        createPatchEndpoint('Change Vendor Status', 'api/changeVendorStatus/:id',
            json_encode(['status' => 'inactive'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor status updated', 'data' => ['id' => 1, 'status' => 'inactive']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 6. Admin Subscription Testing
$newCategories[] = [
    'name' => 'Admin Subscription Testing',
    'item' => [
        createPostEndpoint('Create Plan', 'api/back/v1/admin/subscriptions/plans',
            json_encode(['name' => 'Basic Plan', 'price' => 29.99, 'duration_months' => 1, 'features' => ['Feature 1', 'Feature 2']], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Plan created', 'data' => ['id' => 1, 'name' => 'Basic Plan']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Plan', 'api/back/v1/admin/subscriptions/plans/:plan',
            json_encode(['name' => 'Updated Plan', 'price' => 39.99], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Plan updated'], JSON_PRETTY_PRINT),
            [['key' => 'plan', 'value' => '1']]
        ),
        createGetEndpoint('List Plans', 'api/back/v1/admin/subscriptions/plans', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Basic Plan', 'price' => 29.99]]], JSON_PRETTY_PRINT)
        ),
        createDeleteEndpoint('Delete Plan', 'api/back/v1/admin/subscriptions/plans/:plan',
            [['key' => 'plan', 'value' => '1']]
        ),
        createPostEndpoint('Create Add-On', 'api/back/v1/admin/subscriptions/add-ons',
            json_encode(['name' => 'Extra Storage', 'price' => 9.99], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Add-on created', 'data' => ['id' => 1, 'name' => 'Extra Storage']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Add-On', 'api/back/v1/admin/subscriptions/add-ons/:addOn',
            json_encode(['name' => 'Updated Add-On', 'price' => 14.99], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Add-on updated'], JSON_PRETTY_PRINT),
            [['key' => 'addOn', 'value' => '1']]
        ),
        createGetEndpoint('List Add-Ons', 'api/back/v1/admin/subscriptions/add-ons', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Extra Storage', 'price' => 9.99]]], JSON_PRETTY_PRINT)
        ),
        createDeleteEndpoint('Delete Add-On', 'api/back/v1/admin/subscriptions/add-ons/:addOn',
            [['key' => 'addOn', 'value' => '1']]
        ),
        createPostEndpoint('Create Promo Code', 'api/back/v1/admin/subscriptions/promo-codes',
            json_encode(['code' => 'SAVE20', 'discount_percent' => 20, 'valid_until' => '2025-12-31'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Promo code created', 'data' => ['id' => 1, 'code' => 'SAVE20']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Promo Code', 'api/back/v1/admin/subscriptions/promo-codes/:promoCode',
            json_encode(['discount_percent' => 25], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Promo code updated'], JSON_PRETTY_PRINT),
            [['key' => 'promoCode', 'value' => '1']]
        ),
        createGetEndpoint('List Promo Codes', 'api/back/v1/admin/subscriptions/promo-codes', [],
            json_encode(['data' => [['id' => 1, 'code' => 'SAVE20', 'discount_percent' => 20]]], JSON_PRETTY_PRINT)
        ),
        createDeleteEndpoint('Delete Promo Code', 'api/back/v1/admin/subscriptions/promo-codes/:promoCode',
            [['key' => 'promoCode', 'value' => '1']]
        ),
        createGetEndpoint('Get Configuration', 'api/back/v1/admin/subscriptions/configurations', [],
            json_encode(['data' => ['trial_days' => 14, 'grace_period_days' => 7]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Update Configuration', 'api/back/v1/admin/subscriptions/configurations',
            json_encode(['trial_days' => 30, 'grace_period_days' => 14], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Configuration updated'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Activate Purchase', 'api/back/v1/admin/subscriptions/purchases/:purchase/activate', '{}',
            json_encode(['message' => 'Purchase activated'], JSON_PRETTY_PRINT),
            [['key' => 'purchase', 'value' => '1']]
        ),
        createPostEndpoint('Create Manual Purchase', 'api/back/v1/admin/subscriptions/purchases/manual',
            json_encode(['vendor_id' => 1, 'plan_id' => 1, 'payment_method' => 'bank_transfer'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Manual purchase created', 'data' => ['id' => 1]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Vendors With Subscriptions', 'api/back/v1/admin/subscriptions/allVendorsSubscription', [],
            json_encode(['data' => [['vendor_id' => 1, 'company_name' => 'Vendor 1', 'plan' => 'Basic', 'status' => 'active']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get All Vendors List', 'api/back/v1/admin/subscriptions/getAllVendorsList', [],
            json_encode(['data' => [['id' => 1, 'company_name' => 'Vendor 1']]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Upload Commission Chart', 'api/back/v1/admin/subscriptions/commissionChart/upload',
            json_encode(['name' => 'Q1 2024 Chart', 'file' => 'base64_encoded_file'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Commission chart uploaded', 'data' => ['id' => 1]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Update Commission Chart', 'api/back/v1/admin/subscriptions/commissionChart/:commissionChart',
            json_encode(['name' => 'Updated Chart'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Commission chart updated'], JSON_PRETTY_PRINT),
            [['key' => 'commissionChart', 'value' => '1']]
        ),
        createGetEndpoint('List Commission Charts', 'api/back/v1/admin/subscriptions/commissionChart', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Q1 2024 Chart']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Download Commission Chart', 'api/back/v1/admin/subscriptions/commissionChart/:commissionChart/download', [],
            '(Binary file download)',
            [['key' => 'commissionChart', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Commission Chart', 'api/back/v1/admin/subscriptions/commissionChart/:commissionChart',
            [['key' => 'commissionChart', 'value' => '1']]
        )
    ]
];

// 7. Vendor Subscription Testing
$newCategories[] = [
    'name' => 'Vendor Subscription Testing',
    'item' => [
        createGetEndpoint('Get Plans', 'api/back/v1/vendor/subscriptions/plans', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Basic', 'price' => 29.99]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Add-Ons', 'api/back/v1/vendor/subscriptions/add-ons', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Extra Storage', 'price' => 9.99]]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Apply Promo Code', 'api/back/v1/vendor/subscriptions/promo-codes/apply',
            json_encode(['code' => 'SAVE20'], JSON_PRETTY_PRINT),
            json_encode(['valid' => true, 'discount_percent' => 20, 'message' => 'Promo code applied'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Create Purchase', 'api/back/v1/vendor/subscriptions/purchases',
            json_encode(['plan_id' => 1, 'add_on_ids' => [1], 'promo_code' => 'SAVE20'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Purchase initiated', 'payment_url' => 'https://payment.example.com/...'], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Purchase History', 'api/back/v1/vendor/subscriptions/purchases', [],
            json_encode(['data' => [['id' => 1, 'plan' => 'Basic', 'amount' => 29.99, 'status' => 'completed', 'date' => '2024-01-15']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Subscription Status', 'api/back/v1/vendor/subscriptions/status', [],
            json_encode(['data' => ['plan' => 'Basic', 'status' => 'active', 'expires_at' => '2025-01-15', 'add_ons' => []]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Download Invoice', 'api/back/v1/vendor/subscriptions/invoices/:invoice', [],
            '(PDF file download)',
            [['key' => 'invoice', 'value' => '1']]
        ),
        createGetEndpoint('Get Commission Charts', 'api/back/v1/vendor/subscriptions/commissionChartVendor', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Q1 2024 Chart']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Download Commission Chart', 'api/back/v1/vendor/subscriptions/commissionChartVendor/:commissionChart/download', [],
            '(Binary file download)',
            [['key' => 'commissionChart', 'value' => '1']]
        )
    ]
];

// 8. Terms & Conditions Testing
$newCategories[] = [
    'name' => 'Terms & Conditions Testing',
    'item' => [
        createGetEndpoint('List Terms', 'api/terms-conditions', [],
            json_encode(['data' => [['id' => 1, 'title' => 'Terms of Service', 'content' => '...', 'is_active' => true]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Active Terms', 'api/terms-conditions/active', [],
            json_encode(['data' => ['id' => 1, 'title' => 'Terms of Service', 'content' => '...', 'is_active' => true]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Term by ID', 'api/terms-conditions/:id', [],
            json_encode(['data' => ['id' => 1, 'title' => 'Terms of Service', 'content' => '...']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Term', 'api/terms-conditions',
            json_encode(['title' => 'Privacy Policy', 'content' => 'Your privacy matters...', 'is_active' => true], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Terms created', 'data' => ['id' => 2, 'title' => 'Privacy Policy']], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Term', 'api/terms-conditions/:id',
            json_encode(['title' => 'Updated Terms', 'content' => 'Updated content...'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Terms updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPatchEndpoint('Toggle Status', 'api/terms-conditions/:id/toggle-status', '{}',
            json_encode(['message' => 'Status toggled', 'data' => ['id' => 1, 'is_active' => false]], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Term', 'api/terms-conditions/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 9. Blane Catalog Testing
$newCategories[] = [
    'name' => 'Blane Catalog Testing',
    'item' => [
        createGetEndpoint('Search Blanes', 'api/back/v1/blanes/search',
            ['q' => 'restaurant', 'category' => '1', 'city' => 'Casablanca'],
            json_encode(['data' => [['id' => 1, 'name' => 'Restaurant Deal', 'price' => 99.99]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Blanes By Vendor', 'api/back/v1/getBlanesByVendor',
            ['vendor_id' => '1'],
            json_encode(['data' => [['id' => 1, 'name' => 'Vendor Blane 1']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get All Filter Blane', 'api/back/v1/getAllFilterBlane',
            ['status' => 'active', 'category_id' => '1', 'city' => 'Casablanca'],
            json_encode(['data' => [['id' => 1, 'name' => 'Filtered Blane']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Vendor By Blane', 'api/back/v1/getVendorByBlane',
            ['blane_id' => '1'],
            json_encode(['data' => ['id' => 1, 'company_name' => 'Vendor 1', 'email' => 'vendor@example.com']], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Featured Blanes', 'api/back/v1/getFeaturedBlanes', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Featured Deal', 'is_featured' => true]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Blanes By Start Date', 'api/back/v1/getBlanesByStartDate',
            ['start_date' => '2024-01-01'],
            json_encode(['data' => [['id' => 1, 'name' => 'New Blane', 'start_date' => '2024-01-01']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Blanes By Category', 'api/back/v1/getBlanesByCategory',
            ['category_id' => '1'],
            json_encode(['data' => [['id' => 1, 'name' => 'Category Blane']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Blanes By Vendor (Public)', 'api/back/v1/vendors/getBlanesByVendor',
            ['vendor_id' => '1'],
            json_encode(['data' => [['id' => 1, 'name' => 'Public Vendor Blane']]], JSON_PRETTY_PRINT)
        )
    ]
];

// 10. Blane Share Testing
$newCategories[] = [
    'name' => 'Blane Share Testing',
    'item' => [
        createPostEndpoint('Generate Share Link', 'api/back/v1/blanes/:id/share', '{}',
            json_encode(['message' => 'Share link generated', 'data' => ['share_url' => 'https://example.com/share/abc123', 'token' => 'abc123']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Revoke Share Link', 'api/back/v1/blanes/:id/share',
            [['key' => 'id', 'value' => '1']]
        ),
        createPatchEndpoint('Update Visibility', 'api/back/v1/blanes/:id/visibility',
            json_encode(['visibility' => 'public'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Visibility updated', 'data' => ['id' => 1, 'visibility' => 'public']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 11. Blane Import Testing
$newCategories[] = [
    'name' => 'Blane Import Testing',
    'item' => [
        createPostEndpoint('Import Blanes', 'api/back/v1/blanes/import',
            json_encode(['file' => 'base64_encoded_csv_or_excel'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Import started', 'data' => ['imported' => 50, 'failed' => 2, 'errors' => []]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Bulk Delete Blanes', 'api/back/v1/blanes/bulk-delete',
            json_encode(['ids' => [1, 2, 3]], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Blanes deleted successfully', 'deleted_count' => 3], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Blane (Alt)', 'api/back/v1/updateBlane/:id',
            json_encode(['name' => 'Updated Blane Name', 'price_current' => 89.99], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Blane updated', 'data' => ['id' => 1, 'name' => 'Updated Blane Name']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPatchEndpoint('Update Blane Status', 'api/back/v1/blanes/:id/update-status',
            json_encode(['status' => 'inactive'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Status updated', 'data' => ['id' => 1, 'status' => 'inactive']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 12. Blan Image Extended Testing
$newCategories[] = [
    'name' => 'Blan Image Extended Testing',
    'item' => [
        createPostEndpoint('Upload Vendor Images', 'api/back/v1/uploadVendorImages',
            json_encode(['images' => ['base64_image_1', 'base64_image_2'], 'vendor_id' => 1], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Images uploaded', 'data' => [['id' => 1, 'url' => 'https://...']]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Upload Blane Media', 'api/back/v1/uploadBlaneMedia',
            json_encode(['blane_id' => 1, 'media' => ['base64_image']], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Media uploaded', 'data' => [['id' => 1, 'url' => 'https://...']]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Update Blane Image', 'api/back/v1/updateBlaneImage/:id',
            json_encode(['image' => 'base64_new_image'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Image updated', 'data' => ['id' => 1, 'url' => 'https://...']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 13. Analytics Testing
$newCategories[] = [
    'name' => 'Analytics Testing',
    'item' => [
        createGetEndpoint('Get Analytics', 'api/back/v1/analytics', [],
            json_encode(['data' => ['total_blanes' => 150, 'total_orders' => 500, 'total_revenue' => 25000.00, 'active_vendors' => 45]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Blanes Status', 'api/back/v1/analytics/blanes-status', [],
            json_encode(['data' => ['active' => 100, 'inactive' => 30, 'expired' => 20]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Near Expiration', 'api/back/v1/analytics/near-expiration',
            ['days' => '7'],
            json_encode(['data' => [['id' => 1, 'name' => 'Expiring Blane', 'expiration_date' => '2024-01-20']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Status Distribution', 'api/back/v1/analytics/status-distribution', [],
            json_encode(['data' => ['pending' => 50, 'confirmed' => 200, 'completed' => 150, 'cancelled' => 20]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Vendor Analytics', 'api/back/v1/analytics/vendor', [],
            json_encode(['data' => ['total_blanes' => 25, 'total_orders' => 100, 'revenue' => 5000.00, 'rating' => 4.5]], JSON_PRETTY_PRINT)
        )
    ]
];

// 14. Notification Testing
$newCategories[] = [
    'name' => 'Notification Testing',
    'item' => [
        createGetEndpoint('List Notifications', 'api/back/v1/notifications', [],
            json_encode(['data' => [['id' => 1, 'title' => 'New Order', 'message' => 'You have a new order', 'read' => false, 'created_at' => '2024-01-15']]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Mark As Read', 'api/back/v1/notifications/mark-as-read/:id', '{}',
            json_encode(['message' => 'Notification marked as read'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Mark All As Read', 'api/back/v1/notifications/mark-all-as-read', '{}',
            json_encode(['message' => 'All notifications marked as read'], JSON_PRETTY_PRINT)
        ),
        createDeleteEndpoint('Delete Notification', 'api/back/v1/notifications/:id',
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete All Notifications', 'api/back/v1/notifications'),
        createPostEndpoint('Check Expiration', 'api/back/v1/notifications/check-expiration', '{}',
            json_encode(['message' => 'Expiration check completed', 'notifications_sent' => 5], JSON_PRETTY_PRINT)
        )
    ]
];

// 15. Commission Testing
$newCategories[] = [
    'name' => 'Commission Testing',
    'item' => [
        createGetEndpoint('List Commissions', 'api/back/v1/commissions', [],
            json_encode(['data' => [['id' => 1, 'vendor_id' => 1, 'rate' => 10.0, 'category_id' => 1]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Vendor Rate', 'api/back/v1/commissions/vendors/:vendorId', [],
            json_encode(['data' => ['vendor_id' => 1, 'rate' => 10.0, 'effective_date' => '2024-01-01']], JSON_PRETTY_PRINT),
            [['key' => 'vendorId', 'value' => '1']]
        ),
        createGetEndpoint('List All Category Defaults', 'api/back/v1/commissions/category-defaults/all', [],
            json_encode(['data' => [['category_id' => 1, 'category_name' => 'Electronics', 'default_rate' => 15.0]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Settings', 'api/back/v1/commissions/settings', [],
            json_encode(['data' => ['default_rate' => 10.0, 'min_rate' => 5.0, 'max_rate' => 30.0]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Settings', 'api/back/v1/commissions/settings',
            json_encode(['default_rate' => 12.0, 'min_rate' => 5.0, 'max_rate' => 35.0], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Settings updated'], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Create Commission', 'api/back/v1/commissions',
            json_encode(['vendor_id' => 1, 'rate' => 12.0, 'category_id' => 1], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Commission created', 'data' => ['id' => 1]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Commission', 'api/back/v1/commissions/:id',
            json_encode(['rate' => 15.0], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Commission updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Commission', 'api/back/v1/commissions/:id',
            [['key' => 'id', 'value' => '1']]
        ),
        createPutEndpoint('Set Vendor Rate', 'api/back/v1/commissions/vendors/:vendorId/rate',
            json_encode(['rate' => 12.0], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Vendor rate updated'], JSON_PRETTY_PRINT),
            [['key' => 'vendorId', 'value' => '1']]
        ),
        createGetEndpoint('Get Category Defaults', 'api/back/v1/commissions/category-defaults', [],
            json_encode(['data' => [['category_id' => 1, 'default_rate' => 15.0]]], JSON_PRETTY_PRINT)
        ),
        createPostEndpoint('Set Category Default', 'api/back/v1/commissions/category-defaults',
            json_encode(['category_id' => 1, 'default_rate' => 12.0], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Category default set'], JSON_PRETTY_PRINT)
        )
    ]
];

// 16. Vendor Payment Testing
$newCategories[] = [
    'name' => 'Vendor Payment Testing',
    'item' => [
        createGetEndpoint('List Payments', 'api/back/v1/vendor-payments',
            ['status' => 'pending', 'paginationSize' => '10'],
            json_encode(['data' => [['id' => 1, 'vendor_id' => 1, 'amount' => 500.00, 'status' => 'pending']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Payment by ID', 'api/back/v1/vendor-payments/:id', [],
            json_encode(['data' => ['id' => 1, 'vendor_id' => 1, 'amount' => 500.00, 'status' => 'pending', 'bank_details' => []]], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createGetEndpoint('Get Logs', 'api/back/v1/vendor-payments/logs', [],
            json_encode(['data' => [['id' => 1, 'action' => 'status_changed', 'details' => 'Changed to processed', 'created_at' => '2024-01-15']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Dashboard', 'api/back/v1/vendor-payments/dashboard', [],
            json_encode(['data' => ['pending_count' => 10, 'pending_amount' => 5000.00, 'processed_this_month' => 25000.00]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Weekly Summary', 'api/back/v1/vendor-payments/weekly-summary', [],
            json_encode(['data' => ['week_start' => '2024-01-08', 'total_amount' => 10000.00, 'payments_count' => 20]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Export Excel', 'api/back/v1/vendor-payments/export/excel',
            ['from_date' => '2024-01-01', 'to_date' => '2024-01-31'],
            '(Excel file download)'
        ),
        createGetEndpoint('Export PDF', 'api/back/v1/vendor-payments/export/pdf',
            ['from_date' => '2024-01-01', 'to_date' => '2024-01-31'],
            '(PDF file download)'
        ),
        createGetEndpoint('Get Banking Report', 'api/back/v1/vendor-payments/banking-report', [],
            json_encode(['data' => [['bank' => 'Bank A', 'total_amount' => 15000.00, 'count' => 30]]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Mark As Processed', 'api/back/v1/vendor-payments/mark-processed',
            json_encode(['ids' => [1, 2, 3]], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Payments marked as processed', 'count' => 3], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Payment', 'api/back/v1/vendor-payments/:id',
            json_encode(['notes' => 'Updated payment notes'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Payment updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPutEndpoint('Update Status', 'api/back/v1/vendor-payments/:id/status',
            json_encode(['status' => 'processed'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPutEndpoint('Revert To Pending', 'api/back/v1/vendor-payments/:id/revert', '{}',
            json_encode(['message' => 'Payment reverted to pending'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 17. Banner Testing
$newCategories[] = [
    'name' => 'Banner Testing',
    'item' => [
        createGetEndpoint('List Banners', 'api/back/v1/banners', [],
            json_encode(['data' => [['id' => 1, 'title' => 'Summer Sale', 'image_url' => 'https://...', 'is_active' => true]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Banner', 'api/back/v1/banners/:id', [],
            json_encode(['data' => ['id' => 1, 'title' => 'Summer Sale', 'image_url' => 'https://...', 'link' => '/deals']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Banner', 'api/back/v1/banners',
            json_encode(['title' => 'New Banner', 'image_url' => 'https://...', 'link' => '/new', 'is_active' => true], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Banner created', 'data' => ['id' => 1]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Banner', 'api/back/v1/banners/:id',
            json_encode(['title' => 'Updated Banner', 'is_active' => false], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Banner updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Banner', 'api/back/v1/banners/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 18. Mobile Banner Testing
$newCategories[] = [
    'name' => 'Mobile Banner Testing',
    'item' => [
        createGetEndpoint('List Mobile Banners', 'api/back/v1/mobile-banners', [],
            json_encode(['data' => [['id' => 1, 'title' => 'Mobile Promo', 'image_url' => 'https://...']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Mobile Banner', 'api/back/v1/mobile-banners/:id', [],
            json_encode(['data' => ['id' => 1, 'title' => 'Mobile Promo', 'image_url' => 'https://...']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Mobile Banner', 'api/back/v1/mobile-banners',
            json_encode(['title' => 'New Mobile Banner', 'image_url' => 'https://...', 'is_active' => true], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Mobile banner created', 'data' => ['id' => 1]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Mobile Banner', 'api/back/v1/mobile-banners/:id',
            json_encode(['title' => 'Updated Mobile Banner'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Mobile banner updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Mobile Banner', 'api/back/v1/mobile-banners/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 19. Contact Testing
$newCategories[] = [
    'name' => 'Contact Testing',
    'item' => [
        createGetEndpoint('List Contacts', 'api/back/v1/contacts', [],
            json_encode(['data' => [['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'message' => 'Hello']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Contact', 'api/back/v1/contacts/:id', [],
            json_encode(['data' => ['id' => 1, 'name' => 'John', 'email' => 'john@example.com', 'message' => 'Hello', 'created_at' => '2024-01-15']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Contact', 'api/back/v1/contacts',
            json_encode(['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'I have a question'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Contact created', 'data' => ['id' => 2]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Contact', 'api/back/v1/contacts/:id',
            json_encode(['status' => 'resolved'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Contact updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Contact', 'api/back/v1/contacts/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 20. Customer Testing
$newCategories[] = [
    'name' => 'Customer Testing',
    'item' => [
        createGetEndpoint('List Customers', 'api/back/v1/customers', [],
            json_encode(['data' => [['id' => 1, 'name' => 'Customer 1', 'email' => 'customer@example.com', 'orders_count' => 5]]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Customer', 'api/back/v1/customers/:id', [],
            json_encode(['data' => ['id' => 1, 'name' => 'Customer 1', 'email' => 'customer@example.com', 'phone' => '+1234567890']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Customer', 'api/back/v1/customers',
            json_encode(['name' => 'New Customer', 'email' => 'new@example.com', 'phone' => '+1234567890'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Customer created', 'data' => ['id' => 2]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Customer', 'api/back/v1/customers/:id',
            json_encode(['name' => 'Updated Customer'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Customer updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Customer', 'api/back/v1/customers/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// 21. Rating Testing
$newCategories[] = [
    'name' => 'Rating Testing',
    'item' => [
        createGetEndpoint('List Ratings', 'api/back/v1/ratings', [],
            json_encode(['data' => [['id' => 1, 'blane_id' => 1, 'user_id' => 1, 'rating' => 5, 'comment' => 'Great!']]], JSON_PRETTY_PRINT)
        ),
        createGetEndpoint('Get Rating', 'api/back/v1/ratings/:id', [],
            json_encode(['data' => ['id' => 1, 'blane_id' => 1, 'rating' => 5, 'comment' => 'Great!', 'created_at' => '2024-01-15']], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createPostEndpoint('Create Rating', 'api/back/v1/ratings',
            json_encode(['blane_id' => 1, 'rating' => 4, 'comment' => 'Good experience'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Rating created', 'data' => ['id' => 2]], JSON_PRETTY_PRINT)
        ),
        createPutEndpoint('Update Rating', 'api/back/v1/ratings/:id',
            json_encode(['rating' => 5, 'comment' => 'Updated: Excellent!'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Rating updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        ),
        createDeleteEndpoint('Delete Rating', 'api/back/v1/ratings/:id',
            [['key' => 'id', 'value' => '1']]
        )
    ]
];

// Add all new categories to the Back item
foreach ($newCategories as $category) {
    $collection['item'][$backIndex]['item'][] = $category;
}

// Find and update existing categories with extended endpoints

// Find Order Testing and add extended endpoints
foreach ($collection['item'][$backIndex]['item'] as $index => &$category) {
    if ($category['name'] === 'Order Testing') {
        $category['item'][] = createGetEndpoint('Get Orders List', 'api/back/v1/getOrdersList',
            ['status' => 'pending', 'vendor_id' => '1'],
            json_encode(['data' => [['id' => 1, 'status' => 'pending', 'total' => 99.99]]], JSON_PRETTY_PRINT)
        );
        $category['item'][] = createPatchEndpoint('Update Order Status', 'api/back/v1/orders/:id/update-status',
            json_encode(['status' => 'confirmed'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Order status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        );
    }

    if ($category['name'] === 'Reservation Testing') {
        $category['item'][] = createGetEndpoint('Reservation List', 'api/back/v1/reservationslist', [],
            json_encode(['data' => [['id' => 1, 'status' => 'pending']]], JSON_PRETTY_PRINT)
        );
        $category['item'][] = createGetEndpoint('Get ID By Number', 'api/back/v1/reservations/get-id-by-number/:num_res', [],
            json_encode(['data' => ['id' => 1]], JSON_PRETTY_PRINT),
            [['key' => 'num_res', 'value' => 'RES001']]
        );
        $category['item'][] = createGetEndpoint('Get Reservations And Orders', 'api/back/v1/getReservationsAndOrders', [],
            json_encode(['data' => ['reservations' => [], 'orders' => []]], JSON_PRETTY_PRINT)
        );
        $category['item'][] = createGetEndpoint('Get Vendor Reservations And Orders', 'api/back/v1/getVendorReservationsAndOrders', [],
            json_encode(['data' => ['reservations' => [], 'orders' => []]], JSON_PRETTY_PRINT)
        );
        $category['item'][] = createGetEndpoint('Get Vendor Pending Reservations', 'api/back/v1/getVendorPendingReservations', [],
            json_encode(['data' => [['id' => 1, 'status' => 'pending']]], JSON_PRETTY_PRINT)
        );
        $category['item'][] = createPatchEndpoint('Update Reservation Status', 'api/back/v1/reservations/:id/update-status',
            json_encode(['status' => 'confirmed'], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Reservation status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        );
    }

    if ($category['name'] === 'Coupon Testing') {
        $category['item'][] = createPatchEndpoint('Update Coupon Status', 'api/back/v1/coupons/:id/update-status',
            json_encode(['is_active' => false], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Coupon status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        );
    }

    if ($category['name'] === 'Menu Items Testing') {
        $category['item'][] = createPatchEndpoint('Update Menu Item Status', 'api/back/v1/menu-items/:id/update-status',
            json_encode(['is_active' => false], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Menu item status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        );
    }

    if ($category['name'] === 'Category Testing') {
        $category['item'][] = createPatchEndpoint('Update Category Status', 'api/back/v1/categories/:id/status',
            json_encode(['is_active' => false], JSON_PRETTY_PRINT),
            json_encode(['message' => 'Category status updated'], JSON_PRETTY_PRINT),
            [['key' => 'id', 'value' => '1']]
        );
    }
}

// Remove duplicate Reservation Testing if exists
$reservationCount = 0;
$toRemove = [];
foreach ($collection['item'][$backIndex]['item'] as $index => $category) {
    if ($category['name'] === 'Reservation Testing') {
        $reservationCount++;
        if ($reservationCount > 1) {
            $toRemove[] = $index;
        }
    }
}
foreach (array_reverse($toRemove) as $index) {
    array_splice($collection['item'][$backIndex]['item'], $index, 1);
}

// Save the updated collection
file_put_contents($filePath, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Collection updated successfully!\n";
echo "Added " . count($newCategories) . " new categories.\n";
