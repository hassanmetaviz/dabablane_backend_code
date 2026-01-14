<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\BannerResource;

/**
 * @OA\Schema(
 *     schema="Banner",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Welcome to Dabablane"),
 *     @OA\Property(property="subtitle", type="string", example="Discover amazing services"),
 *     @OA\Property(property="image", type="string", example="https://example.com/banner.jpg"),
 *     @OA\Property(property="link", type="string", example="https://dabablane.com/offers"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BannerController extends BaseController
{
    /**
     * Display a listing of the Banner.
     *
     * @OA\Get(
     *     path="/front/v1/banners",
     *     tags={"Banners"},
     *     summary="Get banner",
     *     description="Retrieve the main banner for the homepage",
     *     operationId="getBanner",
     *     @OA\Response(
     *         response=200,
     *         description="Banner retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Banner")
     *         )
     *     )
     * )
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Banner::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $banner = $query->first();

        return new BannerResource($banner);
    }

    /**
     * Display the specified Banner.
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => [
                    'nullable',
                    'string',
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Banner::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $banner = $query->find($id);

        if (!$banner) {
            return response()->json(['message' => 'Banner not found'], 404);
        }

        return new BannerResource($banner);
    }

    
}
