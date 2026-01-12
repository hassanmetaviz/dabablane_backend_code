<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Mail\VendorRegistrationNotification;
use App\Mail\VendorCredentialsMail;
use App\Models\User;
use App\Notifications\VendorRegistrationNotification as VendorRegistrationNotificationDB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class VendorAuthController extends BaseController
{
    /**
     * Vendor login (supports both password and Firebase)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function vendorLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'nullable|string',
            'firebase_uid' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found. Please sign up as a vendor first.',
                    'errors' => [
                        'credentials' => ['The provided credentials are incorrect or the user is not a vendor.'],
                    ],
                ], 404);
            }

            $hasPassword = !empty($user->password);

            if ($hasPassword) {
                if (!$request->has('password') || empty($request->password)) {
                    return response()->json([
                        'status' => false,
                        'code' => 422,
                        'message' => 'Password is required for this account.',
                        'errors' => [
                            'password' => ['Password is required for this account.'],
                        ],
                    ], 422);
                }

                if (!Hash::check($request->password, $user->password)) {
                    return response()->json([
                        'status' => false,
                        'code' => 401,
                        'message' => 'The provided password is incorrect.',
                        'errors' => [
                            'credentials' => ['The provided password is incorrect.'],
                        ],
                    ], 401);
                }

                if ($request->has('firebase_uid') && !empty($request->firebase_uid) && empty($user->firebase_uid)) {
                    $user->update(['firebase_uid' => $request->firebase_uid]);
                }
            } else {
                if (!$request->has('firebase_uid') || empty($request->firebase_uid)) {
                    return response()->json([
                        'status' => false,
                        'code' => 422,
                        'message' => 'Firebase UID is required for this account.',
                        'errors' => [
                            'firebase_uid' => ['Firebase UID is required for this account.'],
                        ],
                    ], 422);
                }

                if ($user->firebase_uid !== $request->firebase_uid) {
                    if (empty($user->firebase_uid)) {
                        $user->update(['firebase_uid' => $request->firebase_uid]);
                    } else {
                        return response()->json([
                            'status' => false,
                            'code' => 401,
                            'message' => 'The provided Firebase UID does not match.',
                            'errors' => [
                                'credentials' => ['The provided Firebase UID is incorrect.'],
                            ],
                        ], 401);
                    }
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $roles = $user->getRoleNames();

            $message = 'Vendor login successful!';
            if (!$hasPassword) {
                $message .= ' Consider setting a password for easier future logins.';
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => $message,
                'data' => [
                    'user_token' => $token,
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Vendor login failed',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Vendor signup (Firebase-based)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function vendorSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'firebase_uid' => 'required|string|unique:users,firebase_uid',
            'phone' => 'required|string|max:20',
            'city' => 'required|string|max:100',
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'district' => 'nullable|string|max:100',
            'subdistrict' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'firebase_uid' => $request->firebase_uid,
            'phone' => $request->phone,
            'city' => $request->city,
            'company_name' => $request->company_name,
            'status' => 'pending',
        ];

        if ($request->has('address')) {
            $userData['address'] = $request->address;
        }

        if ($request->has('district')) {
            $userData['district'] = $request->district;
        }

        if ($request->has('subdistrict')) {
            $userData['subdistrict'] = $request->subdistrict;
        }

        $user = User::create($userData);

        try {
            $vendorRole = Role::firstOrCreate(['name' => 'vendor']);
            $user->assignRole('vendor');
        } catch (\Exception $roleException) {
            Log::error('Failed to assign vendor role: ' . $roleException->getMessage());

            $user->delete();
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to create vendor account. Please contact support.',
                'errors' => [
                    'server' => ['Vendor role is not available. Please run database seeder.'],
                ],
            ], 500);
        }

        try {
            $adminEmail = config('mail.contact_address');
            if ($adminEmail) {
                try {
                    Mail::to($adminEmail)->send(new VendorRegistrationNotification($user));
                } catch (\Exception $mailException) {
                    \Log::warning('Failed to send vendor registration email: ' . $mailException->getMessage());
                }
            }

            $admins = User::role('admin')->get();
            if ($admins->isEmpty()) {
                \Log::warning('No admin users found to notify about vendor registration');
            } else {
                foreach ($admins as $admin) {
                    try {
                        $admin->notify(new VendorRegistrationNotificationDB($user));

                    } catch (\Exception $notifyException) {
                        \Log::error('Failed to send database notification to admin', [
                            'admin_id' => $admin->id,
                            'error' => $notifyException->getMessage(),
                            'trace' => $notifyException->getTraceAsString(),
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send vendor registration notification: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->getRoleNames();

        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => 'Vendor registered successfully',
            'data' => [
                'user_token' => $token,
                'user' => $user,
                'roles' => $roles,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }

    /**
     * Forgot password for vendor
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotVendorPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $vendor = User::where('email', $request->email)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$vendor) {

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'If a vendor account exists with this email, a password reset email has been sent.',
                ], 200);
            }

            if (empty($vendor->password)) {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'message' => 'This account does not have a password. Please contact admin or use Firebase authentication.',
                    'errors' => [
                        'account' => ['This account does not have a password set.'],
                    ],
                ], 400);
            }

            $newPassword = Str::password(12);
            $vendor->update([
                'password' => Hash::make($newPassword),
            ]);

            try {
                Mail::to($vendor->email)->send(new VendorCredentialsMail($vendor, $newPassword));

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'Password reset email sent successfully. Please check your email for the new password.',
                ], 200);
            } catch (\Exception $mailException) {

                return response()->json([
                    'status' => false,
                    'code' => 500,
                    'message' => 'Failed to send password reset email. Please contact admin.',
                    'errors' => [
                        'email' => ['Failed to send password reset email. Please contact admin.'],
                    ],
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process forgot password request', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to process password reset request',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Check if vendor email was created by admin
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkVendorCreatedByAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $vendor = User::where('email', $request->email)
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$vendor) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found with this email.',
                    'errors' => [
                        'email' => ['Vendor not found with this email.'],
                    ],
                ], 404);
            }

            $hasFirebaseUid = !empty($vendor->firebase_uid);
            $createdByAdmin = !$hasFirebaseUid;
            $hasPassword = !empty($vendor->password);
            $definitelyAdminCreated = $hasPassword && !$hasFirebaseUid;

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor information retrieved successfully',
                'data' => [
                    'email' => $vendor->email,
                    'vendor_id' => $vendor->id,
                    'name' => $vendor->name,
                    'has_firebase_uid' => $hasFirebaseUid,
                    'has_password' => $hasPassword,
                    'created_by_admin' => $createdByAdmin,
                    'definitely_admin_created' => $definitelyAdminCreated,
                    'creation_method' => $definitelyAdminCreated ? 'admin' : ($hasFirebaseUid ? 'mobile_signup' : 'unknown'),
                ],
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to check vendor creation method',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }
}




