<?php

namespace App\Http\Controllers\Api;

use OpenApi\Annotations as OA;
use App\Http\Requests\Api\Auth\GetUserByTokenRequest;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Http\Resources\Front\V1\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * @OA\Schema(
 *     schema="User",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="LoginResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="code", type="integer", example=200),
 *     @OA\Property(property="message", type="string", example="Login successful!"),
 *     @OA\Property(
 *         property="data",
 *         type="object",
 *         @OA\Property(property="user_token", type="string", example="1|abc123xyz..."),
 *         @OA\Property(property="user", ref="#/components/schemas/User"),
 *         @OA\Property(property="roles", type="array", @OA\Items(type="string"), example={"user"}),
 *         @OA\Property(property="token_type", type="string", example="Bearer")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="RegisterResponse",
 *     type="object",
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="code", type="integer", example=201),
 *     @OA\Property(property="message", type="string", example="User registered successfully"),
 *     @OA\Property(property="data", ref="#/components/schemas/User")
 * )
 */
class AuthController extends BaseController
{
    /**
     * Register a new user
     *
     * @OA\Post(
     *     path="/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     description="Create a new user account with name, email, and password",
     *     operationId="register",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe", description="User's full name"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User's email address"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="Password (min 8 characters)"),
     *             @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="Password confirmation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(ref="#/components/schemas/RegisterResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
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
     *     description="Authenticate user with email and password to receive a Bearer token",
     *     operationId="login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User's email address"),
     *             @OA\Property(property="password", type="string", format="password", example="password123", description="User's password")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/logout",
     *     tags={"Authentication"},
     *     summary="Logout user",
     *     description="Invalidate the current user's authentication token",
     *     operationId="logout",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="code", type="integer", example=200),
     *             @OA\Property(property="message", type="string", example="Logged out successfully!"),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
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
     * @OA\Get(
     *     path="/me",
     *     tags={"Authentication"},
     *     summary="Get current user",
     *     description="Retrieve the currently authenticated user's profile information",
     *     operationId="me",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/User"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     )
     * )
     */
    public function me(\Illuminate\Http\Request $request)
    {
        // Keep backward-compatible response shape (Laravel resource collection).
        return UserResource::collection([$request->user()]);
    }

    /**
     * Get user by token
     *
     * @OA\Post(
     *     path="/getUserByToken",
     *     tags={"Authentication"},
     *     summary="Get user by token",
     *     description="Retrieve user information using a provided authentication token",
     *     operationId="getUserByToken",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="1|abc123xyz...", description="User's authentication token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid or expired token",
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
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
