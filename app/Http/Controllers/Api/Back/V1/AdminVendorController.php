<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\AuthResource;
use App\Mail\VendorCredentialsMail;
use App\Mail\VendorStatusChanged;
use App\Models\User;
use App\Models\VendorCoverMedia;
use App\Services\BunnyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminVendorController extends BaseController
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'];

    /**
     * Update vendor by admin
     *
     * @param Request $request
     * @param int|null $vendorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVendorByAdmin(Request $request, $vendorId = null)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $targetVendorId = $vendorId ?? $request->input('vendor_id');

        if (!$targetVendorId) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor ID is required',
            ], 422);
        }

        $vendor = User::whereHas('roles', function ($q) {
            $q->where('name', 'vendor');
        })->find($targetVendorId);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $vendor->id,
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:100|nullable',
            'district' => 'sometimes|string|max:100|nullable',
            'subdistrict' => 'sometimes|string|max:100|nullable',
            'landline' => 'sometimes|string|max:20|nullable',
            'businessCategory' => 'sometimes|string|max:100|nullable',
            'subCategory' => 'sometimes|string|max:100|nullable',
            'description' => 'sometimes|string|nullable',
            'address' => 'sometimes|string|nullable',
            'ice' => 'sometimes|string|max:50|nullable',
            'rc' => 'sometimes|string|max:50|nullable',
            'vat' => 'sometimes|string|max:50|nullable',
            'logoUrl' => 'sometimes|string|nullable',
            'rcCertificateUrl' => 'sometimes|string|nullable',
            'ribUrl' => 'sometimes|string|nullable',
            'facebook' => 'sometimes|string|url|max:255|nullable',
            'tiktok' => 'sometimes|string|url|max:255|nullable',
            'instagram' => 'sometimes|string|url|max:255|nullable',
            'isDiamond' => 'sometimes|boolean',
            'blane_limit' => 'sometimes|integer|min:1|max:100',
            'custom_commission_rate' => 'sometimes|numeric|min:0|max:100',
            'status' => 'sometimes|string|in:active,inactive,pending,suspended,blocked',
            'cover_files.*' => 'sometimes|file|mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi,mkv,wmv,flv,webm,pdf|max:20480',
            'cover_media_urls' => 'sometimes|array',
            'cover_media_urls.*' => 'string',
            'delete_cover_media_ids' => 'sometimes|array',
            'delete_cover_media_ids.*' => 'integer|exists:vendor_cover_media,id',
            'replace_all_media' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validatedData = $validator->validated();

        $deleteCoverMediaIds = $validatedData['delete_cover_media_ids'] ?? [];
        $replaceAllMedia = $validatedData['replace_all_media'] ?? false;

        unset($validatedData['cover_media_urls']);
        unset($validatedData['delete_cover_media_ids']);
        unset($validatedData['replace_all_media']);
        unset($validatedData['cover_files']);

        if (isset($validatedData['blane_limit'])) {
            $validatedData['blane_limit'] = (int) $validatedData['blane_limit'];
        }

        if (isset($validatedData['custom_commission_rate'])) {
            $validatedData['custom_commission_rate'] = (float) $validatedData['custom_commission_rate'];
        }

        $updateData = array_filter($validatedData, function ($value) {
            return $value !== null;
        });

        if (!empty($updateData)) {
            $updated = $vendor->update($updateData);
            Log::info('updateVendorByAdmin - Update result', [
                'updated' => $updated,
                'vendor_blane_limit_after' => $vendor->fresh()->blane_limit,
            ]);
        } else {
            Log::warning('updateVendorByAdmin - No data to update', [
                'validated_data' => $validatedData,
            ]);
        }

        $hasFiles = $request->hasFile('cover_files') || $request->file('cover_files');

        if ($hasFiles) {
            try {
                if ($replaceAllMedia) {
                    VendorCoverMedia::where('user_id', $vendor->id)->get()->each(function ($media) {
                        if ($media->media_url && strpos($media->media_url, 'bunnycdn.com') !== false) {
                            BunnyService::deleteFile($media->media_url);
                        }
                    });
                    VendorCoverMedia::where('user_id', $vendor->id)->delete();
                }

                $files = $request->file('cover_files');
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }

                    $fileExtension = strtolower($file->getClientOriginalExtension());
                    $isVideo = in_array($fileExtension, self::VIDEO_EXTENSIONS, true);
                    $isPdf = $fileExtension === 'pdf';
                    $mediaType = $isVideo ? 'video' : ($isPdf ? 'document' : 'image');

                    try {
                        if ($isVideo) {
                            $bunnyResult = BunnyService::uploadVideo($file, 'vendor_images');
                        } else {
                            $bunnyResult = BunnyService::uploadImage($file, 'vendor_images');
                        }

                        $mediaUrl = $bunnyResult['url'];

                        VendorCoverMedia::create([
                            'user_id' => $vendor->id,
                            'media_url' => $mediaUrl,
                            'media_type' => $mediaType,
                        ]);
                    } catch (\RuntimeException $e) {
                        Log::error('Bunny.net upload failed in admin update', [
                            'error' => $e->getMessage(),
                            'vendor_id' => $vendor->id,
                        ]);
                        continue;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to upload vendor cover media in admin update', [
                    'error' => $e->getMessage(),
                    'vendor_id' => $vendor->id,
                ]);
            }
        }

        $coverMediaUrls = $request->input('cover_media_urls', []);
        if (!empty($coverMediaUrls)) {
            if ($replaceAllMedia) {
                VendorCoverMedia::where('user_id', $vendor->id)->delete();
            }

            foreach ($coverMediaUrls as $mediaUrl) {
                $extension = pathinfo($mediaUrl, PATHINFO_EXTENSION);
                $mediaType = in_array(strtolower($extension), ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm']) ? 'video' :
                    ($extension === 'pdf' ? 'document' : 'image');

                VendorCoverMedia::create([
                    'user_id' => $vendor->id,
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                ]);
            }
        }

        if (!empty($deleteCoverMediaIds)) {
            $mediaToDelete = VendorCoverMedia::where('user_id', $vendor->id)
                ->whereIn('id', $deleteCoverMediaIds)
                ->get();

            foreach ($mediaToDelete as $media) {
                if ($media->media_url && strpos($media->media_url, 'bunnycdn.com') !== false) {
                    try {
                        BunnyService::deleteFile($media->media_url);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete file from Bunny.net', [
                            'url' => $media->media_url,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $media->delete();
            }
        }

        $vendor->load('coverMedia');

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully by admin',
            'data' => $vendor,
        ], 200);
    }

    /**
     * Create vendor by admin
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createVendorByAdmin(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized. Admin access required.',
                'errors' => [
                    'auth' => ['Unauthorized. Admin access required.'],
                ],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'name' => 'nullable|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:100',
            'address' => 'nullable|string|max:500',
            'district' => 'nullable|string|max:100',
            'subdistrict' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:pending,active,inactive',
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

            $password = Str::password(12);

            $email = $request->email;

            $name = $request->name;
            // ?? ucfirst(explode('@', $email)[0]);

            $companyName = $request->company_name;
            // ?? $name . 'Vendor Company';

            $userData = [
                'name' => $name ?? '',
                'email' => $email,
                'password' => Hash::make($password),
                'company_name' => $companyName ?? '',
                'status' => $request->status ?? 'pending',
            ];

            if ($request->has('phone')) {
                $userData['phone'] = $request->phone;
            }

            if ($request->has('city')) {
                $userData['city'] = $request->city;
            }

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

            // Ensure vendor role exists before assigning
            try {
                $vendorRole = Role::firstOrCreate(['name' => 'vendor']);
                $user->assignRole('vendor');
            } catch (\Exception $roleException) {
                Log::error('Failed to assign vendor role: ' . $roleException->getMessage());
                // Rollback user creation if role assignment fails
                $user->delete();
                return response()->json([
                    'status' => false,
                    'code' => 500,
                    'message' => 'Failed to create vendor account. Vendor role is not available.',
                    'errors' => [
                        'server' => ['Vendor role is not available. Please run database seeder: php artisan db:seed --class=RoleSeeder'],
                    ],
                ], 500);
            }

            try {
                Mail::to($user->email)->send(new VendorCredentialsMail($user, $password));

            } catch (\Exception $mailException) {

                return response()->json([
                    'status' => true,
                    'code' => 201,
                    'message' => 'Vendor created successfully, but failed to send credentials email. Please contact the vendor manually.',
                    'data' => [
                        'user' => $user,
                        'roles' => $user->getRoleNames(),
                    ],
                    'warning' => 'Email sending failed. Password: ' . $password, // Include password in response for manual delivery
                ], 201);
            }

            $roles = $user->getRoleNames();

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'Vendor created successfully and credentials email sent',
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create vendor by admin', [
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to create vendor',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Change vendor status
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeVendorStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:active,inactive,pending,suspended,waiting',
                'comment' => 'sometimes|string|nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendor = User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            })->find($id);

            if (!$vendor) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found',
                    'errors' => ['vendor' => ['Vendor not found']],
                ], 404);
            }

            $status = $request->input('status');
            $comment = $request->input('comment');

            $vendor->update(['status' => $status]);

            try {
                Mail::to($vendor->email)->send(new VendorStatusChanged($vendor, $status, $comment));
            } catch (\Exception $e) {
                \Log::error('Failed to send vendor status change email: ' . $e->getMessage());
            }

            $vendor->load('coverMedia');

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor status updated successfully',
                'data' => new AuthResource($vendor),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update vendor status',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Reset vendor password by admin
     *
     * @param Request $request
     * @param int|null $vendorId
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetVendorPasswordByAdmin(Request $request, $vendorId = null)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized. Admin access required.',
                'errors' => [
                    'auth' => ['Unauthorized. Admin access required.'],
                ],
            ], 403);
        }

        $targetVendorId = $vendorId ?? $request->input('vendor_id');

        if (!$targetVendorId) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Vendor ID is required',
                'errors' => [
                    'vendor_id' => ['Vendor ID is required'],
                ],
            ], 422);
        }

        $vendor = User::whereHas('roles', function ($q) {
            $q->where('name', 'vendor');
        })->find($targetVendorId);

        if (!$vendor) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Vendor not found',
                'errors' => [
                    'vendor' => ['Vendor not found'],
                ],
            ], 404);
        }

        try {
            $newPassword = Str::password(12);

            $vendor->update([
                'password' => Hash::make($newPassword),
            ]);

            try {
                Mail::to($vendor->email)->send(new VendorCredentialsMail($vendor, $newPassword));

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'Vendor password reset successfully and credentials email sent',
                    'data' => [
                        'vendor' => [
                            'id' => $vendor->id,
                            'email' => $vendor->email,
                            'name' => $vendor->name,
                        ],
                    ],
                ], 200);
            } catch (\Exception $mailException) {

                return response()->json([
                    'status' => true,
                    'code' => 200,
                    'message' => 'Vendor password reset successfully, but failed to send credentials email. Please contact the vendor manually.',
                    'data' => [
                        'vendor' => [
                            'id' => $vendor->id,
                            'email' => $vendor->email,
                            'name' => $vendor->name,
                        ],
                    ],
                    'warning' => 'Email sending failed. New Password: ' . $newPassword,
                ], 200);
            }
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to reset vendor password',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Get all vendors (with filters)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllVendors(Request $request)
    {
        try {
            $user = $request->user();
            $isAuthenticated = $user !== null;
            $isAdmin = $isAuthenticated && $user->hasRole('admin');
            $statusOptions = $isAdmin
                ? 'nullable|string|in:pending,active,suspended,blocked,inactive'
                : 'nullable|string|in:active';

            $validator = Validator::make($request->all(), [
                'status' => $statusOptions,
                'search' => 'nullable|string|max:255',
                'paginationSize' => 'nullable|integer|min:1|max:100',
                'include' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            $status = $request->input('status');
            if (!$isAuthenticated || !$isAdmin) {
                $query->where('status', 'active');
            } else {
                if ($status) {
                    if ($status === 'active') {
                        $query->active();
                    } elseif ($status === 'pending') {
                        $query->pending();
                    } else {
                        $query->where('status', $status);
                    }
                } else {
                }
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            }

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $query->with($includes);
            }

            $query->orderBy('created_at', 'desc');

            $paginationSize = $request->input('paginationSize', 10);
            $vendors = $query->paginate($paginationSize);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendors retrieved successfully',
                'data' => AuthResource::collection($vendors),
                'meta' => [
                    'total' => $vendors->total(),
                    'current_page' => $vendors->currentPage(),
                    'last_page' => $vendors->lastPage(),
                    'per_page' => $vendors->perPage(),
                    'from' => $vendors->firstItem(),
                    'to' => $vendors->lastItem(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendors',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get vendor by ID or company name (admin)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVendorByIdOrCompanyName(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|integer|exists:users,id',
                'company_name' => 'nullable|string|max:255',
                'include' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (!$request->has('id') && !$request->has('company_name')) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Either id or company_name is required',
                    'errors' => ['input' => ['Either id or company_name is required']],
                ], 422);
            }

            $query = User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            if ($request->has('id')) {
                $query->where('id', $request->input('id'));
            }

            if ($request->has('company_name')) {
                $query->where('company_name', 'like', "%{$request->input('company_name')}%");
            }

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $query->with($includes);
            }

            $vendor = $query->first();

            if (!$vendor) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found',
                    'errors' => ['vendor' => ['Vendor not found']],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor retrieved successfully',
                'data' => new AuthResource($vendor),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get vendor by ID or company name (public - no auth required)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVendorByIdOrCompanyNamePublic(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'nullable|integer|exists:users,id',
                'company_name' => 'nullable|string|max:255',
                'include' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (!$request->has('id') && !$request->has('company_name')) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Either id or company_name is required',
                    'errors' => ['input' => ['Either id or company_name is required']],
                ], 422);
            }

            $query = User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            if ($request->has('id')) {
                $query->where('id', $request->input('id'));
            }

            if ($request->has('company_name')) {
                $query->where('company_name', 'like', "%{$request->input('company_name')}%");
            }

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $query->with($includes);
            }

            $query->where('status', 'active');

            $vendor = $query->first();

            if (!$vendor) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found',
                    'errors' => ['vendor' => ['Vendor not found']],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor retrieved successfully',
                'data' => new AuthResource($vendor),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}



