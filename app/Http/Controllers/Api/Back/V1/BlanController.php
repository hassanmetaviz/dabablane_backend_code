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

/**
 * @OA\Schema(
 *     schema="BackBlane",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Spa Package"),
 *     @OA\Property(property="slug", type="string", example="spa-package"),
 *     @OA\Property(property="description", type="string", example="Relaxing spa treatment"),
 *     @OA\Property(property="commerce_name", type="string", example="Wellness Center"),
 *     @OA\Property(property="commerce_phone", type="string", example="+212600000000"),
 *     @OA\Property(property="price_current", type="number", format="float", example=299.99),
 *     @OA\Property(property="price_old", type="number", format="float", example=399.99),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="district", type="string", example="Maarif"),
 *     @OA\Property(property="subdistricts", type="string", example="Center"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "expired", "waiting"}, example="active"),
 *     @OA\Property(property="type", type="string", enum={"reservation", "order"}, example="reservation"),
 *     @OA\Property(property="visibility", type="string", enum={"private", "public", "link"}, example="public"),
 *     @OA\Property(property="on_top", type="boolean", example=false),
 *     @OA\Property(property="is_digital", type="boolean", example=false),
 *     @OA\Property(property="stock", type="integer", example=100),
 *     @OA\Property(property="views", type="integer", example=150),
 *     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="expiration_date", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="vendor_id", type="integer", example=1),
 *     @OA\Property(property="categories_id", type="integer", example=1),
 *     @OA\Property(property="subcategories_id", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="BlaneCreateRequest",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", maxLength=255, example="Spa Package"),
 *     @OA\Property(property="description", type="string", example="Relaxing spa treatment"),
 *     @OA\Property(property="subcategories_id", type="integer", example=1),
 *     @OA\Property(property="categories_id", type="integer", example=1),
 *     @OA\Property(property="commerce_name", type="string", maxLength=255, example="Wellness Center"),
 *     @OA\Property(property="commerce_phone", type="string", maxLength=20, example="+212600000000"),
 *     @OA\Property(property="price_current", type="number", format="float", example=299.99),
 *     @OA\Property(property="price_old", type="number", format="float", example=399.99),
 *     @OA\Property(property="advantages", type="string", example="Free parking, WiFi"),
 *     @OA\Property(property="conditions", type="string", example="Valid for 6 months"),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="district", type="string", maxLength=255, example="Maarif"),
 *     @OA\Property(property="subdistricts", type="string", maxLength=255, example="Center"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "expired", "waiting"}, example="active"),
 *     @OA\Property(property="type", type="string", enum={"reservation", "order"}, example="reservation"),
 *     @OA\Property(property="reservation_type", type="string", example="hourly"),
 *     @OA\Property(property="online", type="boolean", example=true),
 *     @OA\Property(property="partiel", type="boolean", example=false),
 *     @OA\Property(property="cash", type="boolean", example=true),
 *     @OA\Property(property="partiel_field", type="integer", example=50),
 *     @OA\Property(property="on_top", type="boolean", example=false),
 *     @OA\Property(property="is_digital", type="boolean", example=false),
 *     @OA\Property(property="stock", type="integer", example=100),
 *     @OA\Property(property="nombre_personnes", type="integer", example=2),
 *     @OA\Property(property="max_orders", type="integer", example=50),
 *     @OA\Property(property="livraison_in_city", type="number", format="float", example=20.00),
 *     @OA\Property(property="livraison_out_city", type="number", format="float", example=50.00),
 *     @OA\Property(property="allow_out_of_city", type="boolean", example=true),
 *     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="expiration_date", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="jours_creneaux", type="array", @OA\Items(type="string"), example={"monday", "tuesday"}),
 *     @OA\Property(property="type_time", type="string", enum={"time", "date"}, example="time"),
 *     @OA\Property(property="heure_debut", type="string", format="time", example="09:00"),
 *     @OA\Property(property="heure_fin", type="string", format="time", example="18:00"),
 *     @OA\Property(property="intervale_reservation", type="integer", example=30),
 *     @OA\Property(property="personnes_prestation", type="integer", example=4),
 *     @OA\Property(property="nombre_max_reservation", type="integer", example=10),
 *     @OA\Property(property="availability_per_day", type="integer", example=20),
 *     @OA\Property(property="tva", type="integer", example=20),
 *     @OA\Property(property="max_reservation_par_creneau", type="integer", example=5),
 *     @OA\Property(property="visibility", type="string", enum={"private", "public", "link"}, example="public"),
 *     @OA\Property(property="images", type="array", @OA\Items(type="string", format="binary"), description="Array of image files")
 * )
 */
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
     * @OA\Get(
     *     path="/back/v1/blanes",
     *     tags={"Back - Blanes"},
     *     summary="List all blanes",
     *     description="Get a paginated list of blanes with optional filtering, sorting, and includes",
     *     operationId="backBlanesIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships to include (blaneImages,subcategory,category,ratings)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="paginationSize", in="query", description="Number of items per page", @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="sort_by", in="query", description="Sort field", @OA\Schema(type="string", enum={"created_at", "name", "price_current"})),
     *     @OA\Parameter(name="sort_order", in="query", description="Sort direction", @OA\Schema(type="string", enum={"asc", "desc"})),
     *     @OA\Parameter(name="search", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city", in="query", description="Filter by city", @OA\Schema(type="string")),
     *     @OA\Parameter(name="district", in="query", description="Filter by district", @OA\Schema(type="string")),
     *     @OA\Parameter(name="commerce_name", in="query", description="Filter by vendor/commerce name", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Blanes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/BackBlane")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
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
     * @OA\Get(
     *     path="/back/v1/blanes/{id}",
     *     tags={"Back - Blanes"},
     *     summary="Get a specific blane",
     *     description="Retrieve a single blane by ID with optional relationship includes",
     *     operationId="backBlanesShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Blane ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="include", in="query", description="Comma-separated relationships to include (blaneImages,subcategory,category,ratings)", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Blane retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/BackBlane")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=404, description="Blane not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
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
     * @OA\Post(
     *     path="/back/v1/blanes",
     *     tags={"Back - Blanes"},
     *     summary="Create a new blane",
     *     description="Create a new blane/product/service with optional images",
     *     operationId="backBlanesStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/BlaneCreateRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Blane created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Blane created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackBlane")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
     * @OA\Put(
     *     path="/back/v1/blanes/{id}",
     *     tags={"Back - Blanes"},
     *     summary="Update a blane",
     *     description="Update an existing blane with all fields, can add/delete images",
     *     operationId="backBlanesUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Blane ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/BlaneCreateRequest"),
     *                     @OA\Schema(
     *                         @OA\Property(property="delete_images", type="array", @OA\Items(type="integer"), description="Array of image IDs to delete")
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blane updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Blane updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackBlane")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=404, description="Blane not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
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
     * @OA\Patch(
     *     path="/back/v1/blanes/{id}",
     *     tags={"Back - Blanes"},
     *     summary="Partially update a blane",
     *     description="Update specific fields of an existing blane (without image handling)",
     *     operationId="backBlanesUpdateBlane",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Blane ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/BlaneCreateRequest")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blane updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Blane updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BackBlane")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=404, description="Blane not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
     * @OA\Delete(
     *     path="/back/v1/blanes/{id}",
     *     tags={"Back - Blanes"},
     *     summary="Delete a blane",
     *     description="Delete a blane by ID",
     *     operationId="backBlanesDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Blane ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=204,
     *         description="Blane deleted successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=404, description="Blane not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
     * @OA\Delete(
     *     path="/back/v1/blanes/bulk",
     *     tags={"Back - Blanes"},
     *     summary="Bulk delete blanes",
     *     description="Delete multiple blanes by providing an array of IDs",
     *     operationId="backBlanesBulkDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blanes deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="3 blanes deleted successfully"),
     *             @OA\Property(property="deleted_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=404, description="No valid blanes found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ValidationErrorResponse")),
     *     @OA\Response(response=500, description="Server error", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
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
     * @OA\Patch(
     *     path="/back/v1/blanes/{id}/status",
     *     tags={"Back - Blanes"},
     *     summary="Update blane status",
     *     description="Change the status of a blane (active, inactive, expired, waiting)",
     *     operationId="backBlanesUpdateStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Blane ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "expired", "waiting"}, example="active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blane status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Blane status updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/UnauthorizedResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")),
     *     @OA\Response(response=404, description="Blane not found", @OA\JsonContent(ref="#/components/schemas/NotFoundResponse"))
     * )
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
