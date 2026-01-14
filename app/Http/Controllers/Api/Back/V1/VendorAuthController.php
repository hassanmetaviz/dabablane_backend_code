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

/**
 * @OA\Schema(
 *     schema="VendorLoginResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="message", type="string", example="Vendor login successful!"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="user_token", type="string", example="1|abc123xyz..."),
 *         @OA\Property(property="user", ref="#/components/schemas/User"),
 *         @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"vendor"}),
 *         @OA\Property(property="token_type", type="string", example="Bearer")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="VendorCheckResponse",
 *     type="object",
 *     @OA\Property(property="email", type="string", example="vendor@example.com"),
 *     @OA\Property(property="vendor_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Vendor"),
 *     @OA\Property(property="has_firebase_uid", type="boolean", example=true),
 *     @OA\Property(property="has_password", type="boolean", example=false),
 *     @OA\Property(property="created_by_admin", type="boolean", example=false),
 *     @OA\Property(property="definitely_admin_created", type="boolean", example=false),
 *     @OA\Property(property="creation_method", type="string", enum={"admin", "mobile_signup", "unknown"}, example="mobile_signup")
 * )
 */
class VendorAuthController extends BaseController
{
    /**
     * Vendor login (supports both password and Firebase)
     *
     * @OA\Post(
     *     path="/back/v1/vendor/login",
     *     tags={"Vendor Authentication"},
     *     summary="Vendor login",
     *     description="Authenticate vendor with email and password or Firebase UID",
     *     operationId="vendorLogin",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="vendor@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Required if account has password"),
     *             @OA\Property(property="firebase_uid", type="string", example="firebase_uid_123", description="Required if account uses Firebase")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vendor login successful",
     *         @OA\JsonContent(ref="#/components/schemas/VendorLoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vendor not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/back/v1/vendor/signup",
     *     tags={"Vendor Authentication"},
     *     summary="Vendor signup",
     *     description="Register a new vendor account using Firebase authentication",
     *     operationId="vendorSignup",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "firebase_uid", "phone", "city", "company_name"},
     *             @OA\Property(property="name", type="string", example="John Vendor"),
     *             @OA\Property(property="email", type="string", format="email", example="vendor@example.com"),
     *             @OA\Property(property="firebase_uid", type="string", example="firebase_uid_123"),
     *             @OA\Property(property="phone", type="string", example="+212612345678"),
     *             @OA\Property(property="city", type="string", example="Casablanca"),
     *             @OA\Property(property="company_name", type="string", example="ABC Company"),
     *             @OA\Property(property="address", type="string", example="123 Main Street"),
     *             @OA\Property(property="district", type="string", example="Downtown"),
     *             @OA\Property(property="subdistrict", type="string", example="Central")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Vendor registered successfully",
     *         @OA\JsonContent(ref="#/components/schemas/VendorLoginResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/back/v1/vendor/forgot-password",
     *     tags={"Vendor Authentication"},
     *     summary="Forgot vendor password",
     *     description="Request a password reset for vendor account",
     *     operationId="forgotVendorPassword",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="vendor@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password reset email sent",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset email sent successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Account has no password set",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/back/v1/vendor/check-admin-created",
     *     tags={"Vendor Authentication"},
     *     summary="Check vendor creation method",
     *     description="Check if a vendor account was created by admin or through mobile signup",
     *     operationId="checkVendorCreatedByAdmin",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="vendor@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Vendor information retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Vendor information retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/VendorCheckResponse")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Vendor not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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




