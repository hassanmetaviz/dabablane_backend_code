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
     * @OA\Post(
     *     path="/back/v1/mobile/signup",
     *     tags={"Mobile Authentication"},
     *     summary="Mobile user signup",
     *     description="Register a new user account through mobile app using Firebase authentication",
     *     operationId="mobileSignup",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "firebase_uid", "phone"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255, example="john@example.com"),
     *             @OA\Property(property="firebase_uid", type="string", example="firebase_uid_123"),
     *             @OA\Property(property="phone", type="string", example="+212612345678"),
     *             @OA\Property(property="city", type="string", example="Casablanca")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     )
     * )
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
     * @OA\Post(
     *     path="/back/v1/mobile/login",
     *     tags={"Mobile Authentication"},
     *     summary="Mobile user login",
     *     description="Authenticate a mobile user using Firebase credentials",
     *     operationId="mobileLogin",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "firebase_uid"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="firebase_uid", type="string", example="firebase_uid_123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(ref="#/components/schemas/LoginResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
     *     )
     * )
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
                        'server' => [$this->safeExceptionMessage($e)],
                    ],
                ],
                500,
            );
        }
    }
}




