<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BannerResource;
use App\Services\BunnyService;

class BannerController extends Controller
{
    /**
     * Display a listing of the Banner.
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

    /**
     * Store a newly created Banner.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified Banner.
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified Banner.
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
                'error' => $e->getMessage(),
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
