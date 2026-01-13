<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WrapApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only wrap API routes
        if (!$request->is('api/*')) {
            return $response;
        }

        // Only wrap JSON responses (avoid breaking file downloads, plain text webhooks, etc.)
        if (!$response instanceof JsonResponse) {
            return $response;
        }

        $statusCode = $response->getStatusCode();
        $payload = $response->getData(true);

        // If already in our standard envelope, don't wrap again
        if (is_array($payload)
            && array_key_exists('status', $payload)
            && array_key_exists('code', $payload)
            && array_key_exists('message', $payload)
            && (array_key_exists('data', $payload) || array_key_exists('errors', $payload))
        ) {
            return $response;
        }

        $isSuccess = $statusCode < 400;

        $message = $isSuccess ? 'Success' : 'Error';
        if (is_array($payload) && isset($payload['message']) && is_string($payload['message'])) {
            $message = $payload['message'];
        }

        // Avoid duplicating message inside data/errors
        if (is_array($payload) && array_key_exists('message', $payload)) {
            unset($payload['message']);
        }

        if ($isSuccess) {
            return response()->json([
                'status' => true,
                'code' => $statusCode,
                'message' => $message,
                'data' => $payload,
            ], $statusCode);
        }

        $errors = $payload;
        $data = null;

        if (is_array($payload)) {
            $errors = $payload['errors'] ?? $payload;
            $data = $payload['data'] ?? null;
        }

        $wrapped = [
            'status' => false,
            'code' => $statusCode,
            'message' => $message,
            'errors' => $errors,
        ];

        if ($data !== null) {
            $wrapped['data'] = $data;
        }

        return response()->json($wrapped, $statusCode);
    }
}

