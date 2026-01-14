<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\VendorCoverMedia;
use App\Services\BunnyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="VendorProfile",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Vendor Name"),
 *     @OA\Property(property="email", type="string", format="email", example="vendor@example.com"),
 *     @OA\Property(property="phone", type="string", example="+212612345678"),
 *     @OA\Property(property="company_name", type="string", example="ABC Company"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="district", type="string", example="Anfa"),
 *     @OA\Property(property="address", type="string", example="123 Main St"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="logoUrl", type="string"),
 *     @OA\Property(property="facebook", type="string"),
 *     @OA\Property(property="instagram", type="string"),
 *     @OA\Property(property="tiktok", type="string"),
 *     @OA\Property(property="isDiamond", type="boolean", example=false),
 *     @OA\Property(property="cover_media", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class VendorProfileController extends BaseController
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'];

    /**
     * Update vendor profile (vendor updates their own profile)
     *
     * @OA\Put(
     *     path="/back/v1/vendor/profile",
     *     tags={"Back - Vendor Profile"},
     *     summary="Update vendor profile",
     *     operationId="backVendorProfileUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string", maxLength=255),
     *         @OA\Property(property="email", type="string", format="email", maxLength=255),
     *         @OA\Property(property="phone", type="string", maxLength=20),
     *         @OA\Property(property="city", type="string", maxLength=100),
     *         @OA\Property(property="district", type="string", maxLength=100),
     *         @OA\Property(property="company_name", type="string", maxLength=255),
     *         @OA\Property(property="businessCategory", type="string", maxLength=100),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="address", type="string"),
     *         @OA\Property(property="logoUrl", type="string"),
     *         @OA\Property(property="facebook", type="string", format="url"),
     *         @OA\Property(property="instagram", type="string", format="url"),
     *         @OA\Property(property="tiktok", type="string", format="url"),
     *         @OA\Property(property="isDiamond", type="boolean"),
     *         @OA\Property(property="cover_media_urls", type="array", @OA\Items(type="string")),
     *         @OA\Property(property="delete_cover_media_ids", type="array", @OA\Items(type="integer")),
     *         @OA\Property(property="replace_all_media", type="boolean")
     *     )),
     *     @OA\Response(response=200, description="Profile updated", @OA\JsonContent(@OA\Property(property="success", type="boolean"), @OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/VendorProfile"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=422, description="Validation error")
     * )
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
     * @OA\Post(
     *     path="/back/v1/vendor/password",
     *     tags={"Back - Vendor Profile"},
     *     summary="Update vendor password",
     *     operationId="backVendorPasswordUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"current_password", "new_password", "new_password_confirmation"},
     *         @OA\Property(property="current_password", type="string"),
     *         @OA\Property(property="new_password", type="string", minLength=8),
     *         @OA\Property(property="new_password_confirmation", type="string")
     *     )),
     *     @OA\Response(response=200, description="Password updated", @OA\JsonContent(@OA\Property(property="status", type="boolean"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=401, description="Unauthenticated or wrong password"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
                    'server' => [$this->safeExceptionMessage($e)],
                ],
            ], 500);
        }
    }

    /**
     * Set password for vendor (for vendors who don't have password yet)
     *
     * @OA\Post(
     *     path="/back/v1/vendor/set-password",
     *     tags={"Back - Vendor Profile"},
     *     summary="Set password for vendor (first time)",
     *     operationId="backVendorPasswordSet",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"new_password", "new_password_confirmation"},
     *         @OA\Property(property="new_password", type="string", minLength=8),
     *         @OA\Property(property="new_password_confirmation", type="string")
     *     )),
     *     @OA\Response(response=200, description="Password set", @OA\JsonContent(@OA\Property(property="status", type="boolean"), @OA\Property(property="message", type="string"))),
     *     @OA\Response(response=400, description="Password already set"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to set password',
                'errors' => [
                    'server' => [$this->safeExceptionMessage($e)],
                ],
            ], 500);
        }
    }
}




