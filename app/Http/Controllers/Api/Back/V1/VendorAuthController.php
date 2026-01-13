<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Back\V1\CheckVendorCreatedByAdminRequest;
use App\Http\Requests\Api\Back\V1\ForgotVendorPasswordRequest;
use App\Http\Requests\Api\Back\V1\VendorLoginRequest;
use App\Http\Requests\Api\Back\V1\VendorSignupRequest;
use App\Mail\VendorRegistrationNotification;
use App\Mail\VendorCredentialsMail;
use App\Models\User;
use App\Notifications\VendorRegistrationNotification as VendorRegistrationNotificationDB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
    public function vendorLogin(VendorLoginRequest $request)
    {
        $data = $request->validated();

        try {
            $user = User::where('email', $data['email'])
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$user) {
                return $this->error(
                    'Vendor not found. Please sign up as a vendor first.',
                    ['credentials' => ['The provided credentials are incorrect or the user is not a vendor.']],
                    404
                );
            }

            $hasPassword = !empty($user->password);

            if ($hasPassword) {
                if (empty($data['password'])) {
                    return $this->validationError(
                        ['password' => ['Password is required for this account.']],
                        'Validation error'
                    );
                }

                if (!Hash::check($data['password'], $user->password)) {
                    return $this->error(
                        'The provided password is incorrect.',
                        ['credentials' => ['The provided password is incorrect.']],
                        401
                    );
                }

                if (!empty($data['firebase_uid']) && empty($user->firebase_uid)) {
                    $user->update(['firebase_uid' => $data['firebase_uid']]);
                }
            } else {
                if (empty($data['firebase_uid'])) {
                    return $this->validationError(
                        ['firebase_uid' => ['Firebase UID is required for this account.']],
                        'Validation error'
                    );
                }

                if ($user->firebase_uid !== $data['firebase_uid']) {
                    if (empty($user->firebase_uid)) {
                        $user->update(['firebase_uid' => $data['firebase_uid']]);
                    } else {
                        return $this->error(
                            'The provided Firebase UID does not match.',
                            ['credentials' => ['The provided Firebase UID is incorrect.']],
                            401
                        );
                    }
                }
            }

            $token = $user->createToken('auth_token')->plainTextToken;
            $roles = $user->getRoleNames();

            $message = 'Vendor login successful!';
            if (!$hasPassword) {
                $message .= ' Consider setting a password for easier future logins.';
            }

            return $this->success(
                [
                    'user_token' => $token,
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
                $message,
                200
            );
        } catch (\Exception $e) {
            Log::error('Vendor login failed', [
                'email' => $data['email'] ?? null,
            ]);
            return $this->error('Vendor login failed', [], 500);
        }
    }

    /**
     * Vendor signup (Firebase-based)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function vendorSignup(VendorSignupRequest $request)
    {
        $data = $request->validated();

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'firebase_uid' => $data['firebase_uid'],
            'phone' => $data['phone'],
            'city' => $data['city'],
            'company_name' => $data['company_name'],
            'status' => 'pending',
        ];

        if (array_key_exists('address', $data)) {
            $userData['address'] = $data['address'];
        }
        if (array_key_exists('district', $data)) {
            $userData['district'] = $data['district'];
        }
        if (array_key_exists('subdistrict', $data)) {
            $userData['subdistrict'] = $data['subdistrict'];
        }

        $user = User::create($userData);

        try {
            Role::firstOrCreate(['name' => 'vendor']);
            $user->assignRole('vendor');
        } catch (\Exception $roleException) {
            Log::error('Failed to assign vendor role: ' . $roleException->getMessage());

            $user->delete();
            return $this->error('Failed to create vendor account. Please contact support.', [], 500);
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
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send vendor registration notification: ' . $e->getMessage(), [
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->getRoleNames();

        return $this->success(
            [
                'user_token' => $token,
                'user' => $user,
                'roles' => $roles,
                'token_type' => 'Bearer',
            ],
            'Vendor registered successfully',
            201
        );
    }

    /**
     * Forgot password for vendor
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotVendorPassword(ForgotVendorPasswordRequest $request)
    {
        $data = $request->validated();

        try {
            $vendor = User::where('email', $data['email'])
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$vendor) {
                return $this->success(null, 'If a vendor account exists with this email, a password reset email has been sent.', 200);
            }

            if (empty($vendor->password)) {
                return $this->error(
                    'This account does not have a password. Please contact admin or use Firebase authentication.',
                    ['account' => ['This account does not have a password set.']],
                    400
                );
            }

            $newPassword = Str::password(12);
            $vendor->update([
                'password' => Hash::make($newPassword),
            ]);

            try {
                Mail::to($vendor->email)->send(new VendorCredentialsMail($vendor, $newPassword));

                return $this->success(null, 'Password reset email sent successfully. Please check your email for the new password.', 200);
            } catch (\Exception $mailException) {
                Log::error('Failed to send vendor password reset email', [
                    'email' => $vendor->email,
                    'error' => $mailException->getMessage(),
                ]);
                return $this->error('Failed to send password reset email. Please contact admin.', [], 500);
            }
        } catch (\Exception $e) {
            Log::error('Failed to process forgot password request', [
                'email' => $data['email'] ?? null,
            ]);

            return $this->error('Failed to process password reset request', [], 500);
        }
    }

    /**
     * Check if vendor email was created by admin
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkVendorCreatedByAdmin(CheckVendorCreatedByAdminRequest $request)
    {
        $data = $request->validated();

        try {
            $vendor = User::where('email', $data['email'])
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'vendor');
                })
                ->first();

            if (!$vendor) {
                return $this->error(
                    'Vendor not found with this email.',
                    ['email' => ['Vendor not found with this email.']],
                    404
                );
            }

            $hasFirebaseUid = !empty($vendor->firebase_uid);
            $createdByAdmin = !$hasFirebaseUid;
            $hasPassword = !empty($vendor->password);
            $definitelyAdminCreated = $hasPassword && !$hasFirebaseUid;

            return $this->success(
                [
                    'email' => $vendor->email,
                    'vendor_id' => $vendor->id,
                    'name' => $vendor->name,
                    'has_firebase_uid' => $hasFirebaseUid,
                    'has_password' => $hasPassword,
                    'created_by_admin' => $createdByAdmin,
                    'definitely_admin_created' => $definitelyAdminCreated,
                    'creation_method' => $definitelyAdminCreated ? 'admin' : ($hasFirebaseUid ? 'mobile_signup' : 'unknown'),
                ],
                'Vendor information retrieved successfully',
                200
            );
        } catch (\Exception $e) {
            Log::error('Failed to check vendor creation method', [
                'email' => $data['email'] ?? null,
            ]);

            return $this->error('Failed to check vendor creation method', [], 500);
        }
    }
}




