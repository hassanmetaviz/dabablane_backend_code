<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\Front\V1\BlaneResource;
use App\Http\Resources\Front\V1\BlanImageResource;

/**
 * @OA\Schema(
 *     schema="Blane",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Luxury Spa Treatment"),
 *     @OA\Property(property="slug", type="string", example="luxury-spa-treatment"),
 *     @OA\Property(property="description", type="string", example="Relaxing spa experience"),
 *     @OA\Property(property="price", type="number", format="float", example=299.99),
 *     @OA\Property(property="price_current", type="number", format="float", example=249.99),
 *     @OA\Property(property="type", type="string", enum={"order", "reservation"}, example="reservation"),
 *     @OA\Property(property="status", type="string", enum={"active", "inactive", "expired"}, example="active"),
 *     @OA\Property(property="visibility", type="string", enum={"public", "private", "link"}, example="public"),
 *     @OA\Property(property="stock", type="integer", example=100),
 *     @OA\Property(property="views", type="integer", example=1250),
 *     @OA\Property(property="city", type="string", example="Casablanca"),
 *     @OA\Property(property="category_id", type="integer", example=1),
 *     @OA\Property(property="vendor_id", type="integer", example=1),
 *     @OA\Property(property="is_digital", type="boolean", example=false),
 *     @OA\Property(property="availability_per_day", type="integer", example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="BlaneImage",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="blane_id", type="integer", example=1),
 *     @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
 *     @OA\Property(property="is_primary", type="boolean", example=true)
 * )
 */
class BlanController extends BaseController
{
    /**
     * Display a listing of the Blanes.
     *
     * @OA\Get(
     *     path="/front/v1/blanes",
     *     tags={"Blanes"},
     *     summary="Get all blanes",
     *     description="Retrieve a paginated list of active blanes (products/services) with optional filtering, sorting, and related resources",
     *     operationId="getBlanes",
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources (comma-separated: blaneImages,subcategory,category,ratings,vendor)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         @OA\Schema(type="string", enum={"created_at", "name", "price_current", "ratings"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by blane type",
     *         @OA\Schema(type="string", enum={"order", "reservation"})
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter by category slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="city",
     *         in="query",
     *         description="Filter by city",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="ratings",
     *         in="query",
     *         description="Filter by minimum rating (1-5)",
     *         @OA\Schema(type="number", minimum=1, maximum=5)
     *     ),
     *     @OA\Parameter(
     *         name="is_diamond",
     *         in="query",
     *         description="Filter by diamond vendor status",
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="pagination_size",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=9)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blanes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Blane")),
     *             @OA\Property(property="links", ref="#/components/schemas/PaginationLinks"),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blaneImages', 'subcategory', 'category', 'ratings', 'vendor'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
            'sort_by' => 'nullable|string|in:created_at,name,price_current,ratings',
            'sort_order' => 'nullable|string|in:asc,desc',
            'search' => 'nullable|string',
            'type' => 'nullable|string|in:order,reservation',
            'status' => 'nullable|string|in:active,inactive,expired',
            'category' => 'nullable|string|exists:categories,slug',
            'city' => 'nullable|string',
            'ratings' => 'nullable|numeric|between:1,5',
            'pagination_size' => 'nullable|integer|min:1|max:100',
            'token' => 'nullable|string|uuid',
            'is_diamond' => 'nullable|boolean',
        ]);

        $query = Blane::query()
            ->active()
            ->notExpired()
            ->whereHas('category', function ($q) {
                $q->where('status', 'active');
            })
            ->where(function ($query) {

                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            })
            ->withActiveVendorOrNoVendor();

        $token = $request->input('token');
        $query->where(function ($q) use ($token) {
            $q->where('visibility', 'public');
            if ($token) {
                $q->orWhere(function ($subQ) use ($token) {
                    $subQ->where('visibility', 'link')
                        ->where('share_token', $token);
                });
            }
        });

        if ($request->has('is_diamond')) {
            $isDiamond = $request->boolean('is_diamond');
            $query->whereHas('vendor', function ($q) use ($isDiamond) {
                $q->where('isDiamond', $isDiamond);
            });
        }

        $this->applyFilters($request, $query);
        $this->applySearch($request, $query);
        $this->applySorting($request, $query);

        $query->with([
            'vendor' => function ($query) {
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status');
            }
        ]);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('pagination_size', 9);
        $blanes = $query->paginate($paginationSize);

        return BlaneResource::collection($blanes);
    }

    /**
     * Display the specified Blane.
     *
     * @OA\Get(
     *     path="/front/v1/blanes/{slug}",
     *     tags={"Blanes"},
     *     summary="Get a specific blane",
     *     description="Retrieve details of a specific blane by its slug. Private blanes require a share token.",
     *     operationId="getBlane",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Blane slug",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Include related resources",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="token",
     *         in="query",
     *         description="Share token (required for link-visibility blanes)",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Blane retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Blane")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied (private blane or invalid token)",
     *         @OA\JsonContent(ref="#/components/schemas/ForbiddenResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Blane not found",
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundResponse")
     *     )
     * )
     */
    public function show($slug, Request $request)
    {
        $request->validate([
            'include' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    $validIncludes = ['blaneImages', 'subcategory', 'category', 'ratings', 'vendor'];
                    $includes = explode(',', $value);
                    foreach ($includes as $include) {
                        if (!in_array($include, $validIncludes)) {
                            $fail('The selected ' . $attribute . ' is invalid.');
                        }
                    }
                },
            ],
            'token' => 'nullable|string|uuid',
        ]);

        $query = Blane::query()
            ->whereHas('category', function ($q) {
                $q->where('status', 'active');
            })
            ->where(function ($query) {
                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            })

            ->withActiveVendorOrNoVendor();

        $query->with([
            'vendor' => function ($query) {
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status');
            }
        ]);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $blane = $query->where('slug', $slug)->first();

        if (!$blane) {
            return $this->notFound('Blane not found');
        }

        if ($blane->visibility === 'private') {
            return $this->forbidden('This Blane is private and not accessible');
        }

        if ($blane->visibility === 'link') {
            $token = $request->input('token');
            if (!$token || $token !== $blane->share_token) {
                return $this->forbidden('Access denied. Valid share token required.');
            }
        }

        if ($blane->type_time === 'date') {
            $blane->append('available_periods');
        }

        $blane->increment('views');

        return new BlaneResource($blane);
    }

    /**
     * Apply filters to the query.
     *
     * @param Request $request
     * @param $query
     */
    protected function applyFilters(Request $request, $query)
    {
        if ($request->has('status')) {
            $query->when($request->input('status'), function ($query) use ($request) {
                $query->where('status', $request->input('status'));
            });
        }

        if ($request->has('city')) {
            $query->when($request->input('city'), function ($query) use ($request) {
                $query->where('city', 'like', '%' . $request->input('city') . '%');
            });
        }
        if ($request->has('category')) {
            $query->when($request->input('category'), function ($query) use ($request) {
                $query->whereHas('category', function ($query) use ($request) {
                    $query->where('slug', $request->input('category'));
                });
            });
        }
        if ($request->has('type')) {
            $query->when($request->input('type'), function ($query) use ($request) {
                $query->where('type', $request->input('type'));
            });
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
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('commerce_name', 'like', "%$search%")
                    ->orWhereHas('vendor', function ($q) use ($search) {
                        $q->where('company_name', 'like', "%$search%")
                            ->orWhere('name', 'like', "%$search%");
                    });
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
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSortBy = ['created_at', 'name', 'price_current', 'ratings'];
        $allowedSortOrder = ['asc', 'desc'];

        if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, $allowedSortOrder)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }

    /**
     * Get the blane image.
     *
     * @param int $blane_id
     * @return JsonResponse
     */
    public function getBlanImage($blane_id)
    {
        $blane = Blane::findOrFail($blane_id);
        $blaneImages = $blane->blaneImages;

        if ($blaneImages->isEmpty()) {
            return $this->notFound('Blane images not found');
        }

        return BlanImageResource::collection($blaneImages);
    }

    /**
     * Get a shared Blane by token.
     *
     * @param string $token
     * @param Request $request
     * @return JsonResponse
     */

    public function getByShareToken($token, Request $request)
    {
        $query = Blane::where('share_token', $token)
            ->whereHas('category', function ($q) {
                $q->where('status', 'active');
            })
            ->where(function ($query) {
                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            })
            ->withActiveVendorOrNoVendor();

        $blane = $query->with([
            'vendor' => function ($query) {
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'status');
            }
        ])->first();

        if (!$blane) {
            return $this->notFound('Blane not found');
        }

        return new BlaneResource($blane);
    }
}
