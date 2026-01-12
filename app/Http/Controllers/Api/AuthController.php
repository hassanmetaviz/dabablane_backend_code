<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Front\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
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
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole('user');

        return response()->json(['status' => true, 'code' => 201, 'message' => 'User registered successfully', 'data' => $user], 201);
    }

    /**
     * Login user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 401,
                    'message' => 'The provided credentials are incorrect.',
                    'errors' => [
                        'email' => ['The provided credentials are incorrect.'],
                    ],
                ],
                401,
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
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in first.',
            ], 401);
        }

        $token = $request->user()->currentAccessToken();

        if (!$token) {
            return response()->json([
                'message' => 'No active session found.',
            ], 404);
        }

        $token->delete();

        return response()->json([
            'message' => 'Logged out successfully!',
        ]);
    }

    /**
     * Get current authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return UserResource::collection([$request->user()]);
    }

    /**
     * Get user by token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserByToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = PersonalAccessToken::findToken($request->token);

        if (!$token) {
            return response()->json(
                [
                    'status' => false,
                    'code' => 401,
                    'message' => 'The provided token is invalid or expired.',
                    'errors' => [
                        'token' => ['The provided token is invalid or expired.'],
                    ],
                ],
                401,
            );
        }
        $user = $token->tokenable;
        $roles = $user->getRoleNames();
        return response()->json(
            [
                'status' => true,
                'code' => 200,
                'message' => 'User retrieved successfully!',
                'data' => [
                    'user_token' => $request->token,
                    'user' => $user,
                    'roles' => $roles,
                    'token_type' => 'Bearer',
                ],
            ],
            200,
        );
    }
}
