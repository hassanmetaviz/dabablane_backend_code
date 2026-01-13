<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BlaneResource;
use App\Helpers\FileHelper;
use App\Services\BunnyService;
use App\Mail\BlaneCreationNotification;
use App\Notifications\BlaneCreationNotification as BlaneCreationNotificationDB;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Services\BlaneQueryService;
use Illuminate\Support\Facades\Gate;

class BlanController extends BaseController
{
    protected $queryService;

    public function __construct(BlaneQueryService $queryService)
    {
        $this->queryService = $queryService;
    }
    /**
     * Display a listing of the Blanes.
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
                        $validIncludes = ['blaneImages', 'subcategory', 'category', 'ratings'];
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'paginationSize' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|string|in:created_at,name,price_current',
                'sort_order' => 'nullable|string|in:asc,desc',
                'search' => 'nullable|string',
                'status' => 'nullable|string',
                'city' => 'nullable|string',
                'district' => 'nullable|string',
                'subdistricts' => 'nullable|string',
                'ratings' => 'nullable|numeric|between:1,5',
                'show_inactive_subcategories' => 'nullable|boolean',
                'filter_inactive_subcategories' => 'nullable|boolean',
                'commerce_name' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->withActiveVendorOrNoVendor();

        if ($request->input('filter_inactive_subcategories', false)) {
            $query->where(function ($query) {
                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            });
        }

        $this->queryService->applyFilters($request, $query);
        $this->queryService->applySearch($request, $query);
        $this->queryService->applySorting($request, $query);

        $query->with([
            'vendor' => function ($query) {
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status', 'blane_limit');
            }
        ]);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $blanes = $query->paginate($paginationSize);

        return BlaneResource::collection($blanes);
    }

    /**
     * Display the specified Blane.
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
                        $validIncludes = ['blaneImages', 'subcategory', 'category', 'ratings'];
                        $includes = explode(',', $value);
                        foreach ($includes as $include) {
                            if (!in_array($include, $validIncludes)) {
                                $fail('The selected ' . $attribute . ' is invalid.');
                            }
                        }
                    },
                ],
                'show_inactive_subcategories' => 'nullable|boolean',
                'filter_inactive_subcategories' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        try {
            $query = Blane::query();

            // Auto-filter by vendor if authenticated user is vendor (but allow admins to see all)
            if (auth()->check()) {
                $user = auth()->user();
                if ($user && $user->hasRole('vendor') && !$user->hasRole('admin')) {
                    $query->where(function ($q) use ($user) {
                        $q->where('vendor_id', $user->id)
                            ->orWhere(function ($subQ) use ($user) {
                                $subQ->whereNull('vendor_id')
                                    ->where('commerce_name', $user->company_name);
                            });
                    });
                }
            }

            // Note: We don't apply withActiveVendorOrNoVendor() here for the show method
            // as it might filter out blanes incorrectly when viewing by ID
            // Instead, we rely on the policy check below

            if ($request->input('filter_inactive_subcategories', false)) {
                $query->where(function ($query) {
                    $query->whereNull('subcategories_id')
                        ->orWhereHas('subcategory', function ($q) {
                            $q->where('status', 'active');
                        });
                });
            }

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $query->with($includes);
            }

            $blane = $query->find($id);

            if (!$blane) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Blane not found'
                ], 404);
            }

            // Policy check for viewing blane
            try {
                Gate::authorize('view', $blane);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'message' => 'An unexpected error occurred.',
                ], 403);
            }

            return new BlaneResource($blane);

        } catch (\Exception $e) {
            Log::error('Error in BlanController@show: ' . $e->getMessage(), [
                'id' => $id,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'An error occurred',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created Blane.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Policy check for creating blane
            try {
                Gate::authorize('create', Blane::class);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'An unexpected error occurred.',
                ], 403);
            }

            $validatedData = $request->validate([
                'subcategories_id' => 'nullable|integer|exists:subcategories,id',
                'categories_id' => 'nullable|integer|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'commerce_name' => 'nullable|string|max:255',
                'commerce_phone' => 'nullable|string|max:20',
                'price_current' => 'nullable|numeric',
                'price_old' => 'nullable|numeric',
                'advantages' => 'nullable|string',
                'conditions' => 'nullable|string',
                'city' => 'nullable|string|exists:cities,name',
                'district' => 'nullable|string|max:255',
                'subdistricts' => 'nullable|string|max:255', // Added subdistricts
                'status' => 'nullable|string|in:active,inactive,expired,waiting',
                'type' => 'nullable|string|in:reservation,order',
                'reservation_type' => 'nullable|string',
                'online' => 'nullable|boolean',
                'partiel' => 'nullable|boolean',
                'cash' => 'nullable|boolean',
                'partiel_field' => 'nullable|integer',
                'on_top' => 'nullable|boolean',
                'is_digital' => 'nullable|boolean',
                'views' => 'nullable|integer',
                'start_day' => 'nullable|date',
                'end_day' => 'nullable|date',
                'stock' => 'nullable|integer',
                'nombre_personnes' => 'nullable|integer',
                'max_orders' => 'nullable|integer',
                'livraison_in_city' => 'nullable|numeric',
                'livraison_out_city' => 'nullable|numeric',
                'allow_out_of_city' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'expiration_date' => 'nullable|date',
                'jours_creneaux' => 'nullable|array',
                'dates' => 'nullable|json',
                'type_time' => 'nullable|string|in:time,date',
                'heure_debut' => 'nullable|date_format:H:i',
                'heure_fin' => 'nullable|date_format:H:i',
                'intervale_reservation' => 'nullable|integer',
                'personnes_prestation' => 'nullable|integer',
                'nombre_max_reservation' => 'nullable|integer',
                'availability_per_day' => 'nullable|integer|min:0', // Added validation
                'tva' => 'nullable|integer',
                'max_reservation_par_creneau' => 'nullable|integer',
                'images' => 'nullable|array',
                'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'visibility' => 'nullable|string|in:private,public,link',
            ]);
            Log::info($validatedData);

            $validatedData['slug'] = Blane::generateUniqueSlug($validatedData['name']);

            if (isset($validatedData['dates']) && is_string($validatedData['dates'])) {
                $validatedData['dates'] = json_decode($validatedData['dates'], true);
            }

            if (isset($validatedData['visibility']) && $validatedData['visibility'] === 'link') {
                $validatedData['share_token'] = (string) Str::uuid();
            }

            $validatedData['allow_out_of_city'] = $validatedData['allow_out_of_city'] ?? false;

            DB::beginTransaction();
            if (!empty($validatedData['on_top'])) {
                $onTopCount = Blane::where('on_top', true)->count();
                if ($onTopCount >= 2) {
                    $oldestFeatured = Blane::where('on_top', true)
                        ->orderBy('updated_at', 'asc')
                        ->first();
                    if ($oldestFeatured) {
                        $oldestFeatured->update(['on_top' => false]);
                    }
                }
            }

            $blane = Blane::create($validatedData);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $uploadResult = FileHelper::uploadFile($image, 'blanes_images');
                    if (isset($uploadResult['error'])) {
                        return response()->json(
                            [
                                'error' => 'Image upload failed: ' . $uploadResult['error'],
                            ],
                            422,
                        );
                    }

                    $blane->blaneImages()->create(['image_url' => $uploadResult['file_name']]);
                }
            }

            try {
                $adminEmail = config('mail.contact_address');
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new BlaneCreationNotification($blane));
                }

                $admins = User::role('admin')->get();
                foreach ($admins as $admin) {
                    $admin->notify(new BlaneCreationNotificationDB($blane));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send blane creation notification: ' . $e->getMessage());
            }

            DB::commit();

            return response()->json(
                [
                    'message' => 'Blane created successfully',
                    'data' => new BlaneResource($blane),
                ],
                201,
            );
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(
                [
                    'message' => 'Failed to create Blane',
                ],
                500,
            );
        }
    }

    /**
     * Update the specified Blane.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $blane = Blane::findOrFail($id);

            // Policy check for updating blane (non-breaking, early return if unauthorized)
            try {
                Gate::authorize('update', $blane);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'An unexpected error occurred.',
                ], 403);
            }

            // Ensure vendor can only update their own blanes
            if (auth()->check() && auth()->user()->hasRole('vendor') && !auth()->user()->hasRole('admin')) {
                $isOwner = ($blane->vendor_id === auth()->id()) ||
                    ($blane->vendor_id === null && $blane->commerce_name === auth()->user()->company_name);

                if (!$isOwner) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized: You can only update your own blanes'
                    ], 403);
                }
            }

            $validatedData = $request->validate([
                'subcategories_id' => 'nullable|integer|exists:subcategories,id',
                'categories_id' => 'nullable|integer|exists:categories,id',
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'commerce_name' => 'nullable|string|max:255',
                'commerce_phone' => 'nullable|string|max:20',
                'price_current' => 'nullable|numeric|min:0',
                'price_old' => 'nullable|numeric|min:0',
                'advantages' => 'nullable|string',
                'conditions' => 'nullable|string',
                'city' => 'nullable|string|max:255',
                'district' => 'nullable|string|max:255',
                'subdistricts' => 'nullable|string|max:255', // Added subdistricts
                'status' => 'nullable|string|in:active,inactive,expired,waiting',
                'type' => 'nullable|string|in:reservation,order',
                'reservation_type' => 'nullable|string',
                'online' => 'boolean',
                'partiel' => 'boolean',
                'cash' => 'boolean',
                'partiel_field' => 'nullable|integer|min:0',
                'on_top' => 'boolean',
                'is_digital' => 'boolean',
                'views' => 'nullable|integer|min:0',
                'start_day' => 'nullable|date',
                'end_day' => 'nullable|date|after_or_equal:start_day',
                'stock' => 'nullable|integer',
                'max_orders' => 'nullable|integer|min:0',
                'livraison_in_city' => 'nullable|numeric|min:0',
                'livraison_out_city' => 'nullable|numeric|min:0',
                'allow_out_of_city' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after_or_equal:start_date',
                'jours_creneaux' => 'nullable|array',
                'type_time' => 'nullable|string|in:time,date',
                'dates' => 'nullable|json',
                'heure_debut' => 'nullable|date_format:H:i',
                'heure_fin' => 'nullable|date_format:H:i|after:heure_debut',
                'intervale_reservation' => 'nullable|integer|min:0',
                'personnes_prestation' => 'nullable|integer|min:1',
                'nombre_max_reservation' => 'nullable|integer|min:1',
                'max_reservation_par_creneau' => 'nullable|integer|min:1',
                'availability_per_day' => 'nullable|integer|min:0', // Added validation
                'tva' => 'nullable|integer|min:0',
                'images' => 'nullable|array',
                'images.*' => 'file|mimes:jpeg,png,jpg,gif,webp|max:2048',
                'delete_images' => 'nullable|array',
                'delete_images.*' => 'integer|exists:blane_images,id',
                'visibility' => 'nullable|string|in:private,public,link',
            ]);

            try {
                DB::beginTransaction();

                if (isset($validatedData['name']) && $validatedData['name'] !== $blane->name) {
                    $validatedData['slug'] = Blane::generateUniqueSlug($validatedData['name']);
                }

                if (isset($validatedData['on_top'])) {
                    if ($validatedData['on_top'] && !$blane->on_top) {
                        $onTopCount = Blane::where('on_top', true)->count();
                        if ($onTopCount >= 2) {
                            $oldestFeatured = Blane::where('on_top', true)
                                ->orderBy('updated_at', 'asc')
                                ->first();
                            if ($oldestFeatured) {
                                $oldestFeatured->update(['on_top' => false]);
                            }
                        }
                    }
                }

                if ($request->has('delete_images')) {
                    foreach ($request->input('delete_images') as $imageId) {
                        $image = $blane->blaneImages()->find($imageId);
                        if ($image) {
                            $wasVideo = $image->media_type === 'video';
                            $publicId = $image->cloudinary_public_id;

                            $image->deletePhysicalFile();
                            $image->delete();

                            if (
                                $wasVideo &&
                                $blane->video_public_id &&
                                $blane->video_public_id === $publicId
                            ) {
                                $blane->update([
                                    'video_url' => null,
                                    'video_public_id' => null,
                                ]);
                            }
                        } else {
                            Log::warning('Image not found for deletion', ['image_id' => $imageId]);
                        }
                    }
                }

                if ($request->hasFile('images')) {
                    foreach ($request->file('images') as $index => $image) {
                        $bunnyResult = BunnyService::uploadImage($image);
                        $blane->blaneImages()->create([
                            'image_url' => $bunnyResult['url'],
                            'media_type' => 'image',
                            'is_cloudinary' => true,
                            'cloudinary_public_id' => $bunnyResult['path'],
                        ]);
                    }
                }

                if (isset($validatedData['dates']) && is_string($validatedData['dates'])) {
                    $validatedData['dates'] = json_decode($validatedData['dates'], true);
                }

                if (isset($validatedData['visibility'])) {
                    if ($validatedData['visibility'] === 'link' && (!$blane->share_token || $blane->visibility !== 'link')) {
                        $validatedData['share_token'] = (string) Str::uuid();
                    } elseif ($validatedData['visibility'] !== 'link' && $blane->visibility === 'link') {
                        $validatedData['share_token'] = null;
                    }
                }

                $blane->update($validatedData);

                DB::commit();

                $freshBlane = $blane->fresh(['blaneImages', 'subcategory', 'category', 'ratings']);

                return response()->json([
                    'message' => 'Blane updated successfully',
                    'data' => new BlaneResource($freshBlane)
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Blane not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update Blane',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    /**
     * Update specific fields of a Blane.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateBlane(Request $request, $id): JsonResponse
    {
        try {
            $blane = Blane::findOrFail($id);

            // Policy check for updating blane (non-breaking, early return if unauthorized)
            try {
                Gate::authorize('update', $blane);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'An unexpected error occurred.',
                ], 403);
            }

            // Ensure vendor can only update their own blanes
            if (auth()->check() && auth()->user()->hasRole('vendor') && !auth()->user()->hasRole('admin')) {
                $isOwner = ($blane->vendor_id === auth()->id()) ||
                    ($blane->vendor_id === null && $blane->commerce_name === auth()->user()->company_name);

                if (!$isOwner) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Unauthorized: You can only update your own blanes'
                    ], 403);
                }
            }

            $validatedData = $request->validate([
                'subcategories_id' => 'nullable|integer|exists:subcategories,id',
                'categories_id' => 'nullable|integer|exists:categories,id',
                'name' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'commerce_name' => 'nullable|string|max:255',
                'commerce_phone' => 'nullable|string|max:20',
                'price_current' => 'nullable|numeric|min:0',
                'price_old' => 'nullable|numeric|min:0',
                'advantages' => 'nullable|string',
                'conditions' => 'nullable|string',
                'city' => 'nullable|string|max:255',
                'district' => 'nullable|string|max:255',
                'subdistricts' => 'nullable|string|max:255',
                'status' => 'nullable|string|in:active,inactive,expired,waiting',
                'type' => 'nullable|string|in:reservation,order',
                'reservation_type' => 'nullable|string',
                'online' => 'nullable|boolean',
                'partiel' => 'nullable|boolean',
                'cash' => 'nullable|boolean',
                'partiel_field' => 'nullable|integer|min:0',
                'on_top' => 'nullable|boolean',
                'is_digital' => 'nullable|boolean',
                'views' => 'nullable|integer|min:0',
                'start_day' => 'nullable|date',
                'end_day' => 'nullable|date|after_or_equal:start_day',
                'stock' => 'nullable|integer',
                'max_orders' => 'nullable|integer|min:0',
                'livraison_in_city' => 'nullable|numeric|min:0',
                'livraison_out_city' => 'nullable|numeric|min:0',
                'allow_out_of_city' => 'nullable|boolean',
                'start_date' => 'nullable|date',
                'expiration_date' => 'nullable|date|after_or_equal:start_date',
                'jours_creneaux' => 'nullable|array',
                'type_time' => 'nullable|string|in:time,date',
                'dates' => 'nullable|json',
                'heure_debut' => 'nullable|date_format:H:i',
                'heure_fin' => 'nullable|date_format:H:i|after:heure_debut',
                'intervale_reservation' => 'nullable|integer|min:0',
                'personnes_prestation' => 'nullable|integer|min:1',
                'nombre_max_reservation' => 'nullable|integer|min:1',
                'availability_per_day' => 'nullable|integer|min:0',
                'max_reservation_par_creneau' => 'nullable|integer|min:1',
                'tva' => 'nullable|integer|min:0',
                'visibility' => 'nullable|string|in:private,public,link',
            ]);

            try {
                DB::beginTransaction();

                if (isset($validatedData['name']) && $validatedData['name'] !== $blane->name) {
                    $validatedData['slug'] = Blane::generateUniqueSlug($validatedData['name']);
                }

                if (isset($validatedData['on_top']) && $validatedData['on_top'] && !$blane->on_top) {
                    $onTopCount = Blane::where('on_top', true)->count();
                    if ($onTopCount >= 2) {
                        $oldestFeatured = Blane::where('on_top', true)
                            ->orderBy('updated_at', 'asc')
                            ->first();
                        if ($oldestFeatured) {
                            $oldestFeatured->update(['on_top' => false]);
                        }
                    }
                }

                if (isset($validatedData['dates']) && is_string($validatedData['dates'])) {
                    $validatedData['dates'] = json_decode($validatedData['dates'], true);
                }

                if (isset($validatedData['visibility'])) {
                    if ($validatedData['visibility'] === 'link' && (!$blane->share_token || $blane->visibility !== 'link')) {
                        $validatedData['share_token'] = (string) Str::uuid();
                    } elseif ($validatedData['visibility'] !== 'link' && $blane->visibility === 'link') {
                        $validatedData['share_token'] = null;
                    }
                }

                $blane->update($validatedData);

                DB::commit();

                $freshBlane = $blane->fresh(['blaneImages', 'subcategory', 'category', 'ratings']);

                return response()->json([
                    'message' => 'Blane updated successfully',
                    'data' => new BlaneResource($freshBlane)
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Blane not found'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update Blane',
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    }

    /**
     * Remove the specified Blane.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id): JsonResponse
    {
        $blane = Blane::find($id);

        if (!$blane) {
            return response()->json(['message' => 'Blane not found'], 404);
        }

        // Policy check for deleting blane (non-breaking, early return if unauthorized)
        try {
            Gate::authorize('delete', $blane);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
            ], 403);
        }

        // Ensure vendor can only delete their own blanes
        if (auth()->check() && auth()->user()->hasRole('vendor') && !auth()->user()->hasRole('admin')) {
            $isOwner = ($blane->vendor_id === auth()->id()) ||
                ($blane->vendor_id === null && $blane->commerce_name === auth()->user()->company_name);

            if (!$isOwner) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: You can only delete your own blanes'
                ], 403);
            }
        }

        try {
            $blane->delete();
            return response()->json(
                [
                    'message' => 'Blane deleted successfully',
                ],
                204,
            );
        } catch (\Exception $e) {
            return response()->json(
                [
                    'message' => 'Failed to delete Blane',
                ],
                500,
            );
        }
    }

    /**
     * Remove multiple Blanes in bulk.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer|exists:blanes,id'
            ]);

            $blanes = Blane::whereIn('id', $validatedData['ids'])->get();

            if ($blanes->isEmpty()) {
                return response()->json(['message' => 'No valid blanes found to delete'], 404);
            }

            DB::beginTransaction();
            try {
                foreach ($blanes as $blane) {
                    foreach ($blane->blaneImages as $image) {
                        $image->deletePhysicalFile();
                    }
                    $blane->delete();
                }
                DB::commit();

                return response()->json([
                    'message' => count($blanes) . ' blanes deleted successfully',
                    'deleted_count' => count($blanes)
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete blanes',
            ], 500);
        }
    }


    /**
     * Change the status of a blane
     *
     * @param Request $request
     */
    public function updateStatus(Request $request, $id)
    {
        $blane = Blane::find($id);

        if (!$blane) {
            return response()->json(['message' => 'Blane not found'], 404);
        }

        // Policy check for updating blane status (non-breaking, early return if unauthorized)
        try {
            Gate::authorize('updateStatus', $blane);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred.',
            ], 403);
        }

        $request->validate([
            'status' => 'required|string',
        ]);

        $blane->status = $request->input('status');
        $blane->save();

        return response()->json(['message' => 'Blane status updated successfully'], 200);
    }
}
