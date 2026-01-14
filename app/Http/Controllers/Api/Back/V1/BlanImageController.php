<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use App\Models\BlaneImage;
use App\Models\VendorCoverMedia;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Helpers\FileHelper;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BlanImageResource;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\BunnyService;
use Illuminate\Http\UploadedFile;

/**
 * @OA\Tag(name="Back - Blane Images", description="Blane image and media management")
 *
 * @OA\Schema(
 *     schema="BlaneImage",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="blane_id", type="integer"),
 *     @OA\Property(property="image_url", type="string"),
 *     @OA\Property(property="media_type", type="string", enum={"image","video"}),
 *     @OA\Property(property="is_cloudinary", type="boolean"),
 *     @OA\Property(property="cloudinary_public_id", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BlanImageController extends BaseController
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'flv', 'webm'];

    /**
     * Display a listing of the BlaneImages.
     *
     * @OA\Get(
     *     path="/back/v1/blane-images",
     *     tags={"Back - Blane Images"},
     *     summary="List all blane images",
     *     operationId="backBlaneImageIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string"), description="Relations to include (blane)"),
     *     @OA\Parameter(name="paginationSize", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"created_at","blane_id"})),
     *     @OA\Parameter(name="sort_order", in="query", @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="blane_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Blane images list"),
     *     @OA\Response(response=400, description="Validation error")
     * )
     *
     * @param Request $request
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,blane_id',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'blane_id' => 'nullable|integer',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = BlaneImage::query();

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $blaneImages = $query->paginate($paginationSize);

        return BlanImageResource::collection($blaneImages);
    }

    /**
     * Display the specified BlaneImage.
     *
     * @OA\Get(
     *     path="/back/v1/blane-images/{id}",
     *     tags={"Back - Blane Images"},
     *     summary="Get single blane image",
     *     operationId="backBlaneImageShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Blane image details"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Blane image not found")
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
                    function ($attribute, $value, $fail) {
                        $validIncludes = ['blane']; // Valid relationships
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = BlaneImage::query();

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $blaneImage = $query->find($id);

        if (!$blaneImage) {
            return response()->json(['message' => 'BlaneImage not found'], 404);
        }

        return new BlanImageResource($blaneImage);
    }

    /**
     * Store a newly created BlaneImage.
     *
     * @OA\Post(
     *     path="/back/v1/blane-images",
     *     tags={"Back - Blane Images"},
     *     summary="Create a new blane image",
     *     operationId="backBlaneImageStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="blane_id", type="integer"),
     *             @OA\Property(property="image_file", type="string", format="binary", description="Image file (jpeg,png,jpg,gif,heic,heif)")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Blane image created"),
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
                'blane_id' => 'nullable|integer|exists:blanes,id',
                'image_file' => 'nullable|file|mimes:jpeg,png,jpg,gif,heic,heif|max:2048',
            ]);

            Log::info('Validated data:', $validatedData);

            if ($request->hasFile('image_file')) {
                $bunnyResult = BunnyService::uploadImage($request->file('image_file'));
                $validatedData = array_merge($validatedData, [
                    'image_url' => $bunnyResult['url'],
                    'media_type' => 'image',
                    'is_cloudinary' => true,
                    'cloudinary_public_id' => $bunnyResult['path'],
                ]);
            }
            $blaneImage = BlaneImage::create($validatedData);

            return response()->json([
                'message' => 'BlaneImage created successfully',
                'data' => new BlanImageResource($blaneImage),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create BlaneImage',
            ], 500);
        }
    }

    /**
     * Update the specified BlaneImage.
     *
     * @OA\Put(
     *     path="/back/v1/blane-images/{id}",
     *     tags={"Back - Blane Images"},
     *     summary="Update blane image",
     *     operationId="backBlaneImageUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"blane_id", "image_url"},
     *         @OA\Property(property="blane_id", type="integer"),
     *         @OA\Property(property="image_url", type="string")
     *     )),
     *     @OA\Response(response=200, description="Blane image updated"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Blane image not found"),
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
            $validatedData = $request->validate([
                'blane_id' => 'required|integer|exists:blanes,id',
                'image_url' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $blaneImage = BlaneImage::find($id);

        if (!$blaneImage) {
            return response()->json(['message' => 'BlaneImage not found'], 404);
        }

        try {
            $blaneImage->update($validatedData);
            return response()->json([
                'message' => 'BlaneImage updated successfully',
                'data' => new BlanImageResource($blaneImage),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update BlaneImage',
            ], 500);
        }
    }

    /**
     * Remove the specified BlaneImage.
     *
     * @OA\Delete(
     *     path="/back/v1/blane-images/{id}",
     *     tags={"Back - Blane Images"},
     *     summary="Delete blane image",
     *     operationId="backBlaneImageDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=204, description="Blane image deleted"),
     *     @OA\Response(response=404, description="Blane image not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $blaneImage = BlaneImage::find($id);

        if (!$blaneImage) {
            return response()->json(['message' => 'BlaneImage not found'], 404);
        }

        try {
            $blaneImage->delete();
            return response()->json([
                'message' => 'BlaneImage deleted successfully',
            ], 204);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete BlaneImage',
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
        if ($request->has('blane_id')) {
            $query->where('blane_id', $request->input('blane_id'));
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
            $query->where('image_url', 'like', "%$search%");
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

        $allowedSortBy = ['created_at', 'blane_id'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Upload vendor images (logo, cover, certificates).
     *
     * @OA\Post(
     *     path="/back/v1/vendor/images",
     *     tags={"Back - Blane Images"},
     *     summary="Upload vendor images",
     *     operationId="backVendorImageUpload",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"type", "image_files"},
     *             @OA\Property(property="type", type="string", enum={"logo","cover","rcCertificate","RIB"}),
     *             @OA\Property(property="image_files", type="array", @OA\Items(type="string", format="binary"))
     *         )
     *     )),
     *     @OA\Response(response=200, description="Images uploaded successfully"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function uploadVendorImages(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No user logged in',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'image_files.*' => 'required|file|mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi,mkv,wmv,flv,webm,pdf|max:20480',
            'type' => 'required|string|in:logo,cover,rcCertificate,RIB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please Select format "jpeg,png,jpg,gif,webp,mp4,mov,avi,mkv,wmv,flv,webm,pdf" with 20mb max',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->hasFile('image_files')) {
                $uploadedFiles = [];
                $files = $request->file('image_files');

                foreach ($files as $file) {
                    $fileExtension = strtolower($file->getClientOriginalExtension());
                    $isVideo = in_array($fileExtension, self::VIDEO_EXTENSIONS, true);
                    $isPdf = $fileExtension === 'pdf';

                    $mediaType = $isVideo ? 'video' : ($isPdf ? 'document' : 'image');

                    try {
                        if ($isVideo) {
                            $bunnyResult = BunnyService::uploadVideo($file, 'vendor_images');
                        } else {
                            $bunnyResult = BunnyService::uploadImage($file, 'vendor_images');
                        }

                        $mediaUrl = $bunnyResult['url'];

                        if ($request->type === 'cover') {
                            $vendorCoverMedia = VendorCoverMedia::create([
                                'user_id' => $user->id,
                                'media_url' => $mediaUrl,
                                'media_type' => $mediaType,
                            ]);
                            $uploadedFiles[] = [
                                'media_url' => $vendorCoverMedia->media_url,
                                'media_type' => $vendorCoverMedia->media_type,
                            ];
                        } else {
                            $fieldMap = [
                                'logo' => 'logoUrl',
                                'rcCertificate' => 'rcCertificateUrl',
                                'RIB' => 'ribUrl',
                            ];

                            $field = $fieldMap[$request->type];
                            $user->update([$field => $mediaUrl]);
                            $uploadedFiles[] = [
                                'media_url' => $mediaUrl,
                                'media_type' => $mediaType,
                            ];
                        }
                    } catch (\RuntimeException $e) {
                        Log::error('Bunny.net upload failed for vendor image', [
                            'file_name' => $file->getClientOriginalName(),
                            'type' => $request->type,
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'File upload to Bunny.net failed.',
                            'errors' => $this->safeExceptionMessage($e),
                        ], 422);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => ucfirst($request->type) . ' uploaded successfully',
                    'data' => [
                        'user' => $user->load('coverMedia'),
                        'uploaded_files' => $uploadedFiles,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'No files provided',
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to upload vendor images', [
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'errors' => $this->safeExceptionMessage($e),
            ], 500);
        }
    }

    /**
     * Upload blane media (images and videos).
     *
     * @OA\Post(
     *     path="/back/v1/blane-images/upload",
     *     tags={"Back - Blane Images"},
     *     summary="Upload blane media files",
     *     operationId="backBlaneMediaUpload",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"image_file"},
     *             @OA\Property(property="blane_id", type="integer"),
     *             @OA\Property(property="image_file", type="array", @OA\Items(type="string", format="binary"), description="Image/video files")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Media uploaded successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=422, description="Upload failed"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function uploadBlaneMedia(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'blane_id' => 'nullable|integer|exists:blanes,id',
                'image_file' => 'required|array|min:1',
                'image_file.*' => 'file|mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi,mkv,wmv,flv,webm|max:20480',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            Log::info('Validated data:', $validatedData);

            $uploadedMedia = [];
            $storageDirectory = 'blanes_images';
            $blane = isset($validatedData['blane_id'])
                ? Blane::find($validatedData['blane_id'])
                : null;

            foreach ($request->file('image_file') as $file) {
                $mediaPayload = $this->handleMediaUpload($file, $storageDirectory);

                $blaneImage = BlaneImage::create(array_merge(
                    ['blane_id' => $validatedData['blane_id'] ?? null],
                    $mediaPayload['blane_image']
                ));

                if ($blane && $mediaPayload['blane_video']) {
                    $blane->update($mediaPayload['blane_video']);
                }

                $uploadedMedia[] = $blaneImage;
            }

            return response()->json([
                'message' => 'Blane media uploaded successfully',
                'data' => BlanImageResource::collection($uploadedMedia),
            ], 201);
        } catch (\RuntimeException $exception) {
            Log::error('Failed to upload Blane media', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Failed to create Blane media', ['error' => $exception->getMessage()]);

            return response()->json([
                'message' => 'Failed to create Blane media',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle the upload and payload building for a media file.
     *
     * @return array{blane_image: array, blane_video: array|null}
     */
    private function handleMediaUpload(UploadedFile $file, string $storageDirectory): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $isVideo = in_array($extension, self::VIDEO_EXTENSIONS, true);

        Log::info('Processing Blane media', [
            'original_name' => $file->getClientOriginalName(),
            'extension' => $extension,
            'is_video' => $isVideo,
        ]);

        if ($isVideo) {
            $bunnyResult = BunnyService::uploadVideo($file);

            return [
                'blane_image' => [
                    'image_url' => $bunnyResult['url'],
                    'media_type' => 'video',
                    'is_cloudinary' => true,
                    'cloudinary_public_id' => $bunnyResult['path'],
                ],
                'blane_video' => [
                    'video_url' => $bunnyResult['url'],
                    'video_public_id' => $bunnyResult['path'],
                ],
            ];
        }

        $bunnyResult = BunnyService::uploadImage($file);

        return [
            'blane_image' => [
                'image_url' => $bunnyResult['url'],
                'media_type' => 'image',
                'is_cloudinary' => true,
                'cloudinary_public_id' => $bunnyResult['path'],
            ],
            'blane_video' => null,
        ];
    }

    /**
     * Update blane images with new uploads and existing image management.
     *
     * @OA\Post(
     *     path="/back/v1/blane-images/{id}/update",
     *     tags={"Back - Blane Images"},
     *     summary="Update blane images",
     *     operationId="backBlaneImageUpdateMedia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="blane_id", type="integer"),
     *             @OA\Property(property="image_file", type="array", @OA\Items(type="string", format="binary")),
     *             @OA\Property(property="existing_images", type="array", @OA\Items(type="string"), description="URLs of images to keep"),
     *             @OA\Property(property="image_ids_to_update", type="array", @OA\Items(type="integer"), description="IDs of images to replace")
     *         )
     *     )),
     *     @OA\Response(response=200, description="Blane images updated"),
     *     @OA\Response(response=404, description="Blane not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateBlaneImage(Request $request, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'blane_id' => 'nullable|integer|exists:blanes,id',
                'image_file' => 'nullable|array|min:1',
                'image_file.*' => 'file|mimes:jpeg,png,jpg,gif,webp,heic,heif,mp4,mov,avi,mkv,wmv,flv,webm|max:20480',
                'existing_images' => 'nullable|array',
                'existing_images.*' => 'string',
                'image_ids_to_update' => 'nullable|array',
                'image_ids_to_update.*' => 'integer|exists:blane_images,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => $validator->errors()
                ], 422);
            }

            $blaneId = $request->input('blane_id');
            if (!$blaneId) {
                return response()->json(['error' => 'blane_id is required'], 422);
            }

            $existingImages = $request->input('existing_images', []);
            $imageIdsToUpdate = $request->input('image_ids_to_update', []);
            $storageDirectory = 'blanes_images';
            $uploadedMedia = [];
            $blane = Blane::find($blaneId);

            if (!$blane) {
                return response()->json(['error' => 'Blane not found'], 404);
            }

            BlaneImage::where('blane_id', $blaneId)
                ->whereNotIn('image_url', $existingImages)
                ->whereNotIn('id', $imageIdsToUpdate)
                ->get()
                ->each(function ($image) {
                    $image->deletePhysicalFile();
                    $image->delete();
                });

            if (!empty($imageIdsToUpdate) && $request->hasFile('image_file')) {
                $newFiles = $request->file('image_file');
                $numUpdates = min(count($imageIdsToUpdate), count($newFiles));

                for ($i = 0; $i < $numUpdates; $i++) {
                    $targetImageId = $imageIdsToUpdate[$i];
                    $file = $newFiles[$i];

                    \Log::info('Updating specific image', [
                        'target_id' => $targetImageId,
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'mime' => $file->getClientMimeType()
                    ]);

                    $targetImage = BlaneImage::find($targetImageId);
                    if (!$targetImage || $targetImage->blane_id != $blaneId) {
                        return response()->json(['error' => 'Invalid target image ID: ' . $targetImageId], 404);
                    }

                    $mediaPayload = $this->handleMediaUpload($file, $storageDirectory);

                    $targetImage->deletePhysicalFile();
                    $targetImage->update($mediaPayload['blane_image']);

                    if ($mediaPayload['blane_video']) {
                        $blane->update($mediaPayload['blane_video']);
                    }

                    $uploadedMedia[] = $targetImage->fresh();
                }

                $extraFiles = array_slice($newFiles, $numUpdates);
                if (!empty($extraFiles)) {
                    foreach ($extraFiles as $file) {
                        $mediaPayload = $this->handleMediaUpload($file, $storageDirectory);

                        $newBlaneImage = BlaneImage::create(array_merge(
                            ['blane_id' => $blaneId],
                            $mediaPayload['blane_image']
                        ));

                        if ($mediaPayload['blane_video']) {
                            $blane->update($mediaPayload['blane_video']);
                        }

                        $uploadedMedia[] = $newBlaneImage;
                    }
                }
            } else if ($request->hasFile('image_file')) {
                $newFiles = $request->file('image_file');

                foreach ($newFiles as $file) {
                    $mediaPayload = $this->handleMediaUpload($file, $storageDirectory);

                    $newBlaneImage = BlaneImage::create(array_merge(
                        ['blane_id' => $blaneId],
                        $mediaPayload['blane_image']
                    ));

                    if ($mediaPayload['blane_video']) {
                        $blane->update($mediaPayload['blane_video']);
                    }

                    $uploadedMedia[] = $newBlaneImage;
                }
            }

            $keptImages = BlaneImage::where('blane_id', $blaneId)
                ->whereIn('image_url', $existingImages)
                ->whereNotIn('id', $imageIdsToUpdate)
                ->get();

            $uploadedMedia = array_merge($uploadedMedia, $keptImages->all());

            return response()->json([
                'message' => 'BlaneImage(s) updated/created successfully',
                'data' => BlanImageResource::collection($uploadedMedia),
            ]);

        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update/create BlaneImage(s)',
            ], 500);
        }
    }

}