<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BannerResource;
use App\Services\BunnyService;

/**
 * @OA\Schema(
 *     schema="BackBanner",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="Summer Sale"),
 *     @OA\Property(property="description", type="string", example="Get 50% off on all items"),
 *     @OA\Property(property="image_url", type="string", example="https://cdn.example.com/banner1.jpg"),
 *     @OA\Property(property="link", type="string", example="https://example.com/sale"),
 *     @OA\Property(property="btname1", type="string", example="Shop Now"),
 *     @OA\Property(property="title2", type="string", example="New Arrivals"),
 *     @OA\Property(property="description2", type="string", example="Check out our latest products"),
 *     @OA\Property(property="image_url2", type="string", example="https://cdn.example.com/banner2.jpg"),
 *     @OA\Property(property="btname2", type="string", example="Explore"),
 *     @OA\Property(property="link2", type="string", example="https://example.com/new"),
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
     *     path="/back/v1/banners",
     *     tags={"Back - Banners"},
     *     summary="Get banner",
     *     description="Retrieve the main banner",
     *     operationId="backBannersIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Banner retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackBanner"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse"))
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
     * @OA\Get(
     *     path="/back/v1/banners/{id}",
     *     tags={"Back - Banners"},
     *     summary="Get a specific banner",
     *     operationId="backBannersShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Banner retrieved", @OA\JsonContent(@OA\Property(property="data", ref="#/components/schemas/BackBanner"))),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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

    /**
     * Store a newly created Banner.
     *
     * @OA\Post(
     *     path="/back/v1/banners",
     *     tags={"Back - Banners"},
     *     summary="Create a new banner",
     *     operationId="backBannersStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=255),
     *             @OA\Property(property="image", type="string", format="binary", description="Image or video file"),
     *             @OA\Property(property="link", type="string", maxLength=255),
     *             @OA\Property(property="btname1", type="string", maxLength=255),
     *             @OA\Property(property="title2", type="string", maxLength=255),
     *             @OA\Property(property="description2", type="string", maxLength=255),
     *             @OA\Property(property="image2", type="string", format="binary", description="Second image or video"),
     *             @OA\Property(property="btname2", type="string", maxLength=255),
     *             @OA\Property(property="link2", type="string", maxLength=255)
     *         )
     *     )),
     *     @OA\Response(response=201, description="Banner created", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackBanner"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=422, description="Upload failed"),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {

            Banner::query()->delete();

            $validatedData = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:255',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,wmv|max:20480',  // Added video formats and increased max size
                'link' => 'nullable|string|max:255',
                'btname1' => 'nullable|string|max:255',
                'title2' => 'nullable|string|max:255',
                'description2' => 'nullable|string|max:255',
                'image2' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,wmv|max:20480', // Added video formats and increased max size
                'btname2' => 'nullable|string|max:255',
                'link2' => 'nullable|string|max:255',
            ]);

            $dataToSave = [
                'title' => $validatedData['title'] ?? null,
                'description' => $validatedData['description'] ?? null,
                'link' => $validatedData['link'] ?? null,
                'btname1' => $validatedData['btname1'] ?? null,
                'title2' => $validatedData['title2'] ?? null,
                'description2' => $validatedData['description2'] ?? null,
                'btname2' => $validatedData['btname2'] ?? null,
                'link2' => $validatedData['link2'] ?? null,
            ];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'wmv']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToSave['image_url'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'First image/video upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            if ($request->hasFile('image2')) {
                $file = $request->file('image2');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'wmv']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToSave['image_url2'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'Second image/video upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            $banner = Banner::create($dataToSave);

            return response()->json([
                'message' => 'Banner created successfully',
                'data' => new BannerResource($banner),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create banner',
            ], 500);
        }
    }

    /**
     * Update the specified Banner.
     *
     * @OA\Post(
     *     path="/back/v1/banners/{id}",
     *     tags={"Back - Banners"},
     *     summary="Update a banner",
     *     operationId="backBannersUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="title", type="string", maxLength=255),
     *             @OA\Property(property="description", type="string", maxLength=255),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="link", type="string", maxLength=255),
     *             @OA\Property(property="btname1", type="string", maxLength=255),
     *             @OA\Property(property="title2", type="string", maxLength=255),
     *             @OA\Property(property="description2", type="string", maxLength=255),
     *             @OA\Property(property="image2", type="string", format="binary"),
     *             @OA\Property(property="btname2", type="string", maxLength=255),
     *             @OA\Property(property="link2", type="string", maxLength=255)
     *         )
     *     )),
     *     @OA\Response(response=200, description="Banner updated", @OA\JsonContent(@OA\Property(property="message", type="string"), @OA\Property(property="data", ref="#/components/schemas/BackBanner"))),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=422, description="Upload failed"),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $banner = Banner::find($id);

            if (!$banner) {
                $existingBanner = Banner::first();
                if ($existingBanner) {
                    $banner = $existingBanner;
                } else {
                    return response()->json(['message' => 'Banner not found'], 404);
                }
            }

            $validatedData = $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:255',
                'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,wmv|max:20480',
                'link' => 'nullable|string|max:255',
                'btname1' => 'nullable|string|max:255',
                'title2' => 'nullable|string|max:255',
                'description2' => 'nullable|string|max:255',
                'image2' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,wmv|max:20480',
                'btname2' => 'nullable|string|max:255',
                'link2' => 'nullable|string|max:255',
            ]);

            $dataToUpdate = [
                'title' => $request->input('title', $banner->title),
                'description' => $request->input('description', $banner->description),
                'link' => $request->input('link', $banner->link),
                'btname1' => $request->input('btname1', $banner->btname1),
                'title2' => $request->input('title2', $banner->title2),
                'description2' => $request->input('description2', $banner->description2),
                'btname2' => $request->input('btname2', $banner->btname2),
                'link2' => $request->input('link2', $banner->link2),
            ];

            if ($request->hasFile('image')) {
                if ($banner->image_url) {
                    BunnyService::deleteFile($banner->image_url);
                }

                $file = $request->file('image');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'wmv']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToUpdate['image_url'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'First image/video upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            if ($request->hasFile('image2')) {
                if ($banner->image_url2) {
                    BunnyService::deleteFile($banner->image_url2);
                }

                $file = $request->file('image2');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi', 'wmv']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToUpdate['image_url2'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => 'Second image/video upload failed: ' . $e->getMessage()
                    ], 422);
                }
            }

            $banner->update($dataToUpdate);

            return response()->json([
                'message' => 'Banner updated successfully',
                'data' => new BannerResource($banner),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update banner',
            ], 500);
        }
    }

    /**
     * Remove the specified Banner.
     *
     * @OA\Delete(
     *     path="/back/v1/banners/{id}",
     *     tags={"Back - Banners"},
     *     summary="Delete a banner",
     *     operationId="backBannersDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Banner deleted"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json(['message' => 'Banner not found'], 404);
        }

        try {
            if ($banner->image_url) {
                BunnyService::deleteFile($banner->image_url);
            }
            if ($banner->image_url2) {
                BunnyService::deleteFile($banner->image_url2);
            }

            $banner->delete();
            return response()->json([
                'message' => 'Banner deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Banner',
            ], 500);
        }
    }

    /**
     * Apply filters to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->input('title') . '%');
        }

    }

    /**
     * Apply search to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySearch(Request $request, $query)
    {
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%$search%");
        }
    }

    /**
     * Apply sorting to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applySorting(Request $request, $query)
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortBy = ['created_at', 'city'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
