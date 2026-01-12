<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\MobileBanner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Front\V1\MobileBannerResource;
use Illuminate\Validation\ValidationException;

class MobileBannerController extends Controller
{
    /**
     * Display active mobile banners for mobile app.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'limit' => 'nullable|integer|min:1|max:50',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        }

        try {
            $limit = $request->input('limit', 10);

            $mobileBanners = MobileBanner::active()
                ->ordered()
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Mobile banners retrieved successfully',
                'data' => MobileBannerResource::collection($mobileBanners),
                'meta' => [
                    'total' => $mobileBanners->count(),
                    'limit' => $limit,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve mobile banners',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a specific mobile banner by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id): JsonResponse
    {
        try {
            $mobileBanner = MobileBanner::active()->find($id);

            if (!$mobileBanner) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Mobile banner not found or not active',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Mobile banner retrieved successfully',
                'data' => new MobileBannerResource($mobileBanner),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve mobile banner',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

