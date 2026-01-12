<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends BaseController
{
    /**
     * Update user profile
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
                        'server' => [$e->getMessage()],
                    ],
                ],
                500,
            );
        }
    }

    /**
     * Delete user account
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
                    'server' => [$e->getMessage()],
                ],
            ], 500);
        }
    }
}




