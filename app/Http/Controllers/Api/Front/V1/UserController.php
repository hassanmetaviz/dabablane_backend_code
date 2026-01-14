<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\UserResource;

class UserController extends BaseController
{
    /**
     * Get user profile
     *
     * @OA\Get(
     *     path="/front/v1/user/profile",
     *     tags={"User Profile"},
     *     summary="Get user profile",
     *     description="Retrieve the authenticated user's profile with roles, permissions, reservations and orders",
     *     operationId="getUserProfile",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            return response()->json([
                'success' => true,
                'data' => new UserResource($user->load(['roles', 'permissions', 'reservations', 'orders']))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
            ], 500);
        }
    }

    /**
     * Update user profile
     *
     * @OA\Put(
     *     path="/front/v1/user/profile",
     *     tags={"User Profile"},
     *     summary="Update user profile",
     *     description="Update the authenticated user's profile information",
     *     operationId="updateUserProfile",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/User"),
     *             @OA\Property(property="token", type="string", nullable=true, description="New token if email was changed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
            ]);

            $user->update($validatedData);

            if (isset($validatedData['email'])) {
                $user->tokens()->delete();
                $token = $user->createToken('auth_token')->plainTextToken;
            }

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => new UserResource($user),
                'token' => $token ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
            ], 500);
        }
    }

    /**
     * Change user password
     *
     * @OA\Post(
     *     path="/front/v1/user/change-password",
     *     tags={"User Profile"},
     *     summary="Change password",
     *     description="Change the authenticated user's password",
     *     operationId="changePassword",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "new_password_confirmation"},
     *             @OA\Property(property="current_password", type="string", format="password", example="oldpassword123"),
     *             @OA\Property(property="new_password", type="string", format="password", minLength=8, example="newpassword123"),
     *             @OA\Property(property="new_password_confirmation", type="string", format="password", example="newpassword123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password changed successfully"),
     *             @OA\Property(property="token", type="string", description="New authentication token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or incorrect password",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            $user = $request->user();

            if (!Hash::check($validatedData['current_password'], $user->password)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            $user->update([
                'password' => Hash::make($validatedData['new_password'])
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'token' => $token
            ]);

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to change password',
            ], 500);
        }
    }

    /**
     * Get user activity history
     *
     * @OA\Get(
     *     path="/front/v1/user/activity",
     *     tags={"User Profile"},
     *     summary="Get activity history",
     *     description="Retrieve the authenticated user's reservations and orders history",
     *     operationId="getActivityHistory",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Activity history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="reservations", type="array", @OA\Items(ref="#/components/schemas/Reservation")),
     *                 @OA\Property(property="orders", type="array", @OA\Items(ref="#/components/schemas/Order"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     */
    public function activityHistory(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $activities = $user->load([
                'reservations' => function($query) {
                    $query->orderBy('date', 'desc')
                          ->orderBy('time', 'desc');
                },
                'orders'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reservations' => $activities->reservations,
                    'orders' => $activities->orders
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch activity history',
            ], 500);
        }
    }

    /**
     * Delete user account
     *
     * @OA\Delete(
     *     path="/front/v1/user",
     *     tags={"User Profile"},
     *     summary="Delete user account",
     *     description="Delete the authenticated user's account and revoke all tokens",
     *     operationId="deleteUserAccount",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens
            $user->tokens()->delete();
            
            // Delete the user
            $user->delete();

            return response()->json([
                'message' => 'User deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete user',
            ], 500);
        }
    }


}