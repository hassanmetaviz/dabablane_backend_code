<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends BaseController
{
    /**
     * Mobile user signup (Firebase-based)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobileSignup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'firebase_uid' => 'required|string',
            'phone' => 'required|string',
            'city' => 'nullable|string',
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

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'firebase_uid' => $request->firebase_uid,
            'phone' => $request->phone,
            'city' => $request->city ?? null,
        ]);
        $user->assignRole('user');

        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->getRoleNames();

        return response()->json(
            [
                'status' => true,
                'code' => 201,
                'message' => 'User registered successfully',
                'data' => [
                    'user_token' => $token,
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
            ],
            201,
        );
    }

    /**
     * Mobile user login (Firebase-based)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function mobileLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'firebase_uid' => 'required|string',
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
            $user = User::where('email', $request->email)
                ->where('firebase_uid', $request->firebase_uid)
                ->first();
            if (!$user) {
                return response()->json(
                    [
                        'status' => false,
                        'code' => 404,
                        'message' => 'User not found. Please sign up first.',
                        'errors' => [
                            'credentials' => ['The provided credentials are incorrect.'],
                        ],
                    ],
                    404,
                );
            }

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
        } catch (\Exception $e) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 500,
                    'message' => 'Login failed',
                    'errors' => [
                        'server' => [$e->getMessage()],
                    ],
                ],
                500,
            );
        }
    }
}




