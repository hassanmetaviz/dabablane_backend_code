<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SocialAuthController extends BaseController
{
    /**
     * Social login (Firebase-based)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function socialLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'firebase_uid' => 'required|string',
            'name' => 'nullable|string|max:255',
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
            $user = User::where('firebase_uid', $request->firebase_uid)->first();

            if ($user) {
                $user->update([
                    'email' => $request->email,
                    'name' => $request->name ?? $user->name,
                ]);

                $token = $user->createToken('auth_token')->plainTextToken;
                $roles = $user->getRoleNames();

                return response()->json(
                    [
                        'status' => true,
                        'code' => 200,
                        'message' => 'Login successful!',
                        'data' => [
                            'user_token' => $token,
                            'user' => $user,
                            'roles' => $roles,
                            'token_type' => 'Bearer',
                        ],
                    ],
                    200,
                );
            }

            $user = User::create([
                'email' => $request->email,
                'firebase_uid' => $request->firebase_uid,
                'name' => $request->name ?? '',
                'provider' => 'firebase',
            ]);

            $user->assignRole('user');

            $token = $user->createToken('auth_token')->plainTextToken;
            $roles = $user->getRoleNames();

            return response()->json(
                [
                    'status' => true,
                    'code' => 201,
                    'message' => 'User registered and logged in successfully',
                    'data' => [
                        'user_token' => $token,
                        'user' => $user,
                        'roles' => $roles,
                        'token_type' => 'Bearer',
                    ],
                ],
                201,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 500,
                    'message' => 'Social login failed',
                    'errors' => [
                        'server' => [$e->getMessage()],
                    ],
                ],
                500,
            );
        }
    }
}




