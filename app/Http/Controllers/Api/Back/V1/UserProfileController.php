<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(name="Back - User Profile", description="User profile management")
 */
class UserProfileController extends BaseController
{
    /**
     * Update user profile
     *
     * @OA\Put(
     *     path="/back/v1/profile",
     *     tags={"Back - User Profile"},
     *     summary="Update authenticated user profile",
     *     operationId="backProfileUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         @OA\Property(property="name", type="string", maxLength=255),
     *         @OA\Property(property="email", type="string", format="email"),
     *         @OA\Property(property="phone", type="string", maxLength=20),
     *         @OA\Property(property="city", type="string", maxLength=255)
     *     )),
     *     @OA\Response(response=200, description="Profile updated successfully", @OA\JsonContent(
     *         @OA\Property(property="status", type="boolean"),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="string"))
     *         )
     *     )),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 401,
                    'message' => 'Unauthenticated. Please log in first.',
                    'errors' => [
                        'auth' => ['Unauthenticated.'],
                    ],
                ],
                401,
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 422,
                    'message' => 'validation error',
                    'errors' => $validator->errors(),
                ],
                422,
            );
        }

        try {
            $user->update($request->only(['name', 'email', 'phone', 'city']));

            $roles = $user->getRoleNames();

            return response()->json(
                [
                    'status' => true,
                    'code' => 200,
                    'message' => 'Profile updated successfully',
                    'data' => [
                        'user' => $user,
                        'roles' => $roles,
                        'token_type' => 'Bearer',
                    ],
                ],
                200,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 500,
                    'message' => 'Profile update failed',
                    'errors' => [
                        'server' => [$this->safeExceptionMessage($e)],
                    ],
                ],
                500,
            );
        }
    }

    /**
     * Delete user account
     *
     * @OA\Delete(
     *     path="/back/v1/profile",
     *     tags={"Back - User Profile"},
     *     summary="Delete authenticated user account",
     *     operationId="backProfileDelete",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Account deleted successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteAccount(Request $request)
    {
        try {
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

            $user->tokens()->delete();
            $user->roles()->detach();
            $user->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Account deleted successfully',
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete account',
                'errors' => [
                    'server' => [$this->safeExceptionMessage($e)],
                ],
            ], 500);
        }
    }
}




