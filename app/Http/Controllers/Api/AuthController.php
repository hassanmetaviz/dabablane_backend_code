<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\Auth\GetUserByTokenRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Front\V1\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Annotations as OA;

class AuthController extends BaseController
{
    /**
     * Register a new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole('user');

        // Keep response shape backward-compatible: data is the user object (not nested).
        return $this->success($user, 'User registered successfully', 201);
    }

    /**
     * Login user
     *
     * @OA\Post(
     *     path="/login",
     *     tags={"Authentication"},
     *     summary="Login user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful!")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->error(
                'The provided credentials are incorrect.',
                ['email' => ['The provided credentials are incorrect.']],
                401
            );
        }
        $token = $user->createToken('auth_token')->plainTextToken;
        $roles = $user->getRoleNames();

        return $this->success(
            [
                'user_token' => $token,
                'user' => new UserResource($user),
                'roles' => $roles,
                'token_type' => 'Bearer',
            ],
            'Login successful!',
            200
        );
    }

    /**
     * Logout user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(\Illuminate\Http\Request $request)
    {
        if (!$request->user()) {
            return $this->error('Unauthenticated. Please log in first.', [], 401);
        }

        $token = $request->user()->currentAccessToken();

        if (!$token) {
            return $this->error('No active session found.', [], 404);
        }

        $token->delete();

        return $this->success(null, 'Logged out successfully!');
    }

    /**
     * Get current authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(\Illuminate\Http\Request $request)
    {
        // Keep backward-compatible response shape (Laravel resource collection).
        return UserResource::collection([$request->user()]);
    }

    /**
     * Get user by token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserByToken(GetUserByTokenRequest $request)
    {
        $data = $request->validated();

        $token = PersonalAccessToken::findToken($data['token']);

        if (!$token) {
            return $this->error(
                'The provided token is invalid or expired.',
                ['token' => ['The provided token is invalid or expired.']],
                401
            );
        }
        $user = $token->tokenable;
        $roles = $user->getRoleNames();
        return $this->success(
            [
                'user_token' => $data['token'],
                'user' => new UserResource($user),
                'roles' => $roles,
                'token_type' => 'Bearer',
            ],
            'User retrieved successfully!',
            200
        );
    }
}
