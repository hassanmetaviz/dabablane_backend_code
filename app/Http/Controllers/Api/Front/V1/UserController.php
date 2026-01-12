<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\UserResource;

class UserController extends Controller
{
    // Remove constructor completely

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
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}