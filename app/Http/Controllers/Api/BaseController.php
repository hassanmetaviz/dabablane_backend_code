<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

abstract class BaseController extends Controller
{
    /**
     * Return a success JSON response.
     *
     * @param mixed $data
     * @param string $message
     * @param int $code
     * @return \Illuminate\Http\JsonResponse
     */
    protected function success($data, $message = 'Success', $code = 200)
    {
        return response()->json([
            'status' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Return an error JSON response.
     *
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param mixed $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function error($message, $errors = [], $code = 400, $data = null)
    {
        $payload = [
            'status' => false,
            'code' => $code,
            'message' => $message,
        ];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * Return a safe exception message for client responses.
     * - In production (APP_DEBUG=false): do not leak internal details.
     * - In debug: return the actual exception message.
     */
    protected function safeExceptionMessage(\Throwable $e): string
    {
        return (bool) config('app.debug')
            ? $e->getMessage()
            : 'Internal server error.';
    }

    /**
     * Return a validation error JSON response.
     *
     * @param array $errors
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function validationError($errors, $message = 'validation error')
    {
        return $this->error($message, $errors, 422);
    }
}






