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
    protected function validationError($errors, $message = 'Validation error')
    {
        return $this->error($message, $errors, 422);
    }

    /**
     * Return a not found JSON response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function notFound($message = 'Resource not found')
    {
        return $this->error($message, [], 404);
    }

    /**
     * Return a forbidden JSON response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function forbidden($message = 'Access denied')
    {
        return $this->error($message, [], 403);
    }

    /**
     * Return a created JSON response (201).
     *
     * @param mixed $data
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function created($data, $message = 'Created successfully')
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Return a deleted JSON response.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deleted($message = 'Deleted successfully')
    {
        return $this->success(null, $message, 200);
    }

    /**
     * Return a paginated success JSON response with consistent structure.
     *
     * @param \Illuminate\Http\Resources\Json\AnonymousResourceCollection $resourceCollection
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedSuccess($resourceCollection, $message = 'Success')
    {
        $paginator = $resourceCollection->resource;

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => $message,
            'data' => $resourceCollection->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], 200);
    }
}






