<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\VendorCoverMedia;
use App\Services\BunnyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class VendorProfileController extends BaseController
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'];

    /**
     * Update vendor profile (vendor updates their own profile)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVendor(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No user logged in',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20',
            'city' => 'sometimes|string|max:100|nullable',
            'district' => 'sometimes|string|max:100|nullable',
            'subdistrict' => 'sometimes|string|max:100|nullable',
            'landline' => 'sometimes|string|max:20|nullable',
            'company_name' => 'sometimes|string|max:255|nullable',
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
        $coverMediaUrls = $validatedData['cover_media_urls'] ?? [];
        $deleteCoverMediaIds = $validatedData['delete_cover_media_ids'] ?? [];
        $replaceAllMedia = $validatedData['replace_all_media'] ?? false;

        unset($validatedData['cover_media_urls']);
        unset($validatedData['delete_cover_media_ids']);
        unset($validatedData['replace_all_media']);

        $user->update($validatedData);

        if (!empty($coverMediaUrls)) {
            if ($replaceAllMedia) {
                VendorCoverMedia::where('user_id', $user->id)->delete();
            } else {
                $existingMediaUrls = VendorCoverMedia::where('user_id', $user->id)
                    ->pluck('media_url')
                    ->toArray();
            }

            foreach ($coverMediaUrls as $mediaUrl) {
                if ($replaceAllMedia || !in_array($mediaUrl, $existingMediaUrls ?? [])) {
                    $extension = pathinfo($mediaUrl, PATHINFO_EXTENSION);
                    $mediaType = in_array(strtolower($extension), ['mp4', 'mov', 'avi']) ? 'video' :
                        ($extension === 'pdf' ? 'document' : 'image');

                    VendorCoverMedia::create([
                        'user_id' => $user->id,
                        'media_url' => $mediaUrl,
                        'media_type' => $mediaType,
                    ]);
                }
            }
        }

        if (!empty($deleteCoverMediaIds)) {
            $mediaToDelete = VendorCoverMedia::where('user_id', $user->id)
                ->whereIn('id', $deleteCoverMediaIds)
                ->get();

            foreach ($mediaToDelete as $media) {
                $media->delete();
            }
        }

        $user->load('coverMedia');

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user,
        ], 200);
    }

    /**
     * Update vendor password
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateVendorPassword(Request $request)
    {
        // Get the authenticated user
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => 'Unauthenticated. Please log in first.',
                'errors' => [
                    'auth' => ['Unauthenticated.'],
                ],
            ], 401);
        }

        if (!$user->hasRole('vendor')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized. Vendor access required.',
                'errors' => [
                    'auth' => ['Unauthorized. Vendor access required.'],
                ],
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
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
            if (empty($user->password)) {
                return response()->json([
                    'status' => false,
                    'code' => 401,
                    'message' => 'Current password not set. Please contact admin.',
                    'errors' => [
                        'current_password' => ['Current password not set. Please contact admin.'],
                    ],
                ], 401);
            }

            if (!\Illuminate\Support\Facades\Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => false,
                    'code' => 401,
                    'message' => 'Current password is incorrect.',
                    'errors' => [
                        'current_password' => ['The provided current password is incorrect.'],
                    ],
                ], 401);
            }

            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
            ]);

            $roles = $user->getRoleNames();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Password updated successfully',
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Password update failed',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }

    /**
     * Set password for vendor (for vendors who don't have password yet)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setVendorPassword(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'code' => 401,
                'message' => 'Unauthenticated. Please log in first.',
                'errors' => [
                    'auth' => ['Unauthenticated.'],
                ],
            ], 401);
        }

        if (!$user->hasRole('vendor')) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Unauthorized. Vendor access required.',
                'errors' => [
                    'auth' => ['Unauthorized. Vendor access required.'],
                ],
            ], 403);
        }

        if (!empty($user->password)) {
            return response()->json([
                'status' => false,
                'code' => 400,
                'message' => 'Password already set. Use updateVendorPassword to change it.',
                'errors' => [
                    'password' => ['Password already set. Use updateVendorPassword to change it.'],
                ],
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8|confirmed',
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
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->new_password),
            ]);

            $roles = $user->getRoleNames();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Password set successfully. You can now use password-based login.',
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to set vendor password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to set password',
                'errors' => [
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }
}




