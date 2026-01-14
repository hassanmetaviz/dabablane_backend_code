<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\MobileBanner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\MobileBannerResource;
use App\Services\BunnyService;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="MobileBanner",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="image_url", type="string"),
 *     @OA\Property(property="link", type="string"),
 *     @OA\Property(property="order", type="integer"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class MobileBannerController extends BaseController
{
    /**
     * Display a listing of the MobileBanners.
     *
     * @OA\Get(
     *     path="/back/v1/mobile-banners",
     *     tags={"Back - Mobile Banners"},
     *     summary="List all mobile banners",
     *     operationId="backMobileBannersIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at", "order", "title"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, description="Mobile banners retrieved", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MobileBanner")))),
     *     @OA\Response(response=400, description="Validation error")
     * )
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string',
                'paginationSize' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:created_at,order,title',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = MobileBanner::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $mobileBanners = $query->paginate($paginationSize);

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'Mobile banners retrieved successfully',
            'data' => MobileBannerResource::collection($mobileBanners),
            'meta' => [
                'total' => $mobileBanners->total(),
                'current_page' => $mobileBanners->currentPage(),
                'last_page' => $mobileBanners->lastPage(),
                'per_page' => $mobileBanners->perPage(),
                'from' => $mobileBanners->firstItem(),
                'to' => $mobileBanners->lastItem(),
            ],
        ], 200);
    }

    /**
     * Display the specified MobileBanner.
     *
     * @OA\Get(
     *     path="/back/v1/mobile-banners/{id}",
     *     tags={"Back - Mobile Banners"},
     *     summary="Get a specific mobile banner",
     *     operationId="backMobileBannersShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mobile banner retrieved"),
     *     @OA\Response(response=404, description="Not found")
     * )
     *
     * @param int $id
     * @param Request $request
     */
    public function show($id, Request $request)
    {
        try {
            $request->validate([
                'include' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = MobileBanner::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $mobileBanner = $query->find($id);

        if (!$mobileBanner) {
            return response()->json(['message' => 'Mobile banner not found'], 404);
        }

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'Mobile banner retrieved successfully',
            'data' => new MobileBannerResource($mobileBanner),
        ], 200);
    }

    /**
     * Store a newly created MobileBanner.
     *
     * @OA\Post(
     *     path="/back/v1/mobile-banners",
     *     tags={"Back - Mobile Banners"},
     *     summary="Create a new mobile banner",
     *     operationId="backMobileBannersStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"title", "image"},
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="link", type="string"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Mobile banner created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480', //20MB
                'link' => 'nullable|string|max:255',
                'order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $dataToSave = [
                'title' => $validatedData['title'],
                'description' => $validatedData['description'] ?? null,
                'link' => $validatedData['link'] ?? null,
                'order' => $validatedData['order'] ?? 0,
                'is_active' => $validatedData['is_active'] ?? true,
                'start_date' => $validatedData['start_date'] ?? null,
                'end_date' => $validatedData['end_date'] ?? null,
            ];

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToSave['image_url'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'code' => 422,
                        'message' => 'File upload failed',
                    ], 422);
                }
            }

            $mobileBanner = MobileBanner::create($dataToSave);

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'Mobile banner created successfully',
                'data' => new MobileBannerResource($mobileBanner),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to create mobile banner',
            ], 500);
        }
    }

    /**
     * Update the specified MobileBanner.
     *
     * @OA\Put(
     *     path="/back/v1/mobile-banners/{id}",
     *     tags={"Back - Mobile Banners"},
     *     summary="Update a mobile banner",
     *     operationId="backMobileBannersUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="image", type="string", format="binary"),
     *             @OA\Property(property="link", type="string"),
     *             @OA\Property(property="order", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date")
     *         )
     *     )),
     *     @OA\Response(response=200, description="Mobile banner updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $mobileBanner = MobileBanner::find($id);

            if (!$mobileBanner) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Mobile banner not found',
                ], 404);
            }

            $validatedData = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string|max:1000',
                'image' => 'required|file|mimes:jpeg,png,jpg,gif,webp,mp4,mov,avi|max:20480',
                'link' => 'nullable|string|max:255',
                'order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $dataToUpdate = [
                'title' => $request->input('title', $mobileBanner->title),
                'description' => $request->input('description', $mobileBanner->description),
                'link' => $request->input('link', $mobileBanner->link),
                'order' => $request->input('order', $mobileBanner->order),
                'is_active' => $request->input('is_active', $mobileBanner->is_active),
                'start_date' => $request->input('start_date', $mobileBanner->start_date),
                'end_date' => $request->input('end_date', $mobileBanner->end_date),
            ];

            if ($request->hasFile('image')) {
                if ($mobileBanner->image_url) {
                    BunnyService::deleteFile($mobileBanner->image_url);
                }

                $file = $request->file('image');
                $extension = strtolower($file->getClientOriginalExtension());
                $isVideo = in_array($extension, ['mp4', 'mov', 'avi']);

                try {
                    if ($isVideo) {
                        $bunnyResult = BunnyService::uploadVideo($file, 'banner');
                    } else {
                        $bunnyResult = BunnyService::uploadImage($file, 'banner');
                    }
                    $dataToUpdate['image_url'] = $bunnyResult['url'];
                } catch (\Exception $e) {
                    return response()->json([
                        'status' => false,
                        'code' => 422,
                        'message' => 'File upload failed',
                    ], 422);
                }
            }

            $mobileBanner->update($dataToUpdate);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Mobile banner updated successfully',
                'data' => new MobileBannerResource($mobileBanner),
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update mobile banner',
            ], 500);
        }
    }

    /**
     * Remove the specified MobileBanner.
     *
     * @OA\Delete(
     *     path="/back/v1/mobile-banners/{id}",
     *     tags={"Back - Mobile Banners"},
     *     summary="Delete a mobile banner",
     *     operationId="backMobileBannersDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mobile banner deleted"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $mobileBanner = MobileBanner::find($id);

        if (!$mobileBanner) {
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Mobile banner not found',
            ], 404);
        }

        try {
            if ($mobileBanner->image_url) {
                BunnyService::deleteFile($mobileBanner->image_url);
            }

            $mobileBanner->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Mobile banner deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete mobile banner',
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
        if ($request->has('is_active')) {
            $query->where('is_active', $request->input('is_active'));
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
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
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
        $sortBy = $request->input('sort_by', 'order');
        $sortOrder = $request->input('sort_order', 'asc');

        $allowedSortBy = ['created_at', 'order', 'title'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('order', 'asc')->orderBy('created_at', 'desc');
        }
    }
}

