<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BlaneResource;
use App\Services\BlaneQueryService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class BlaneCatalogController extends BaseController
{
    protected $queryService;

    public function __construct(BlaneQueryService $queryService)
    {
        $this->queryService = $queryService;
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'query' => 'nullable|string|min:1|max:255',
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
                'paginationSize' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|string|in:created_at,name,price_current,views',
                'sort_order' => 'nullable|string|in:asc,desc',
                'status' => 'nullable|string|in:active,inactive,expired,waiting',
                'type' => 'nullable|string|in:reservation,order',
                'city' => 'nullable|string',
                'district' => 'nullable|string',
                'subdistricts' => 'nullable|string',
                'min_price' => 'nullable|numeric|min:0',
                'max_price' => 'nullable|numeric|min:0',
                'ratings' => 'nullable|numeric|between:1,5',
                'filter_inactive_subcategories' => 'nullable|boolean',
                'include_expired' => 'nullable|boolean',
                'search_fields' => 'nullable|array',
                'search_fields.*' => 'in:name,description,city,district,subdistricts,commerce_name,advantages,conditions,vendor_company',
                'is_diamond' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->withActiveVendorOrNoVendor();

        if ($request->has('query') && !empty($request->input('query'))) {
            $this->queryService->applySearchQuery($request, $query);
        }

        $this->queryService->applySearchFilters($request, $query);

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

        Log::info('Search performed', [
            'query' => $request->input('query'),
            'total_results' => $blanes->total(),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        if ($blanes->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No blanes found matching your search criteria',
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'per_page' => $paginationSize,
                    'current_page' => $blanes->currentPage(),
                    'last_page' => $blanes->lastPage(),
                    'from' => $blanes->firstItem(),
                    'to' => $blanes->lastItem(),
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Search completed successfully',
            'data' => BlaneResource::collection($blanes),
            'meta' => [
                'total' => $blanes->total(),
                'per_page' => $blanes->perPage(),
                'current_page' => $blanes->currentPage(),
                'last_page' => $blanes->lastPage(),
                'from' => $blanes->firstItem(),
                'to' => $blanes->lastItem(),
            ]
        ]);
    }

    /**
     * Get featured Blanes.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getFeaturedBlanes(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
                'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
                'is_diamond' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->where('on_top', true)
            ->where('expiration_date', '>=', Carbon::today()->toDateString())
            ->withActiveVendorOrNoVendor()
            ->active();

        $query->where(function ($query) {
            $query->where(function ($q) {
                $q->where('type', 'reservation')
                    ->where('nombre_max_reservation', '!=', 0);
            })
                ->orWhere(function ($q) {
                    $q->where('type', 'order')
                        ->where('stock', '!=', 0);
                })
                ->orWhereNotIn('type', ['reservation', 'order']);
        });

        if ($request->has('category_id')) {
            $query->where('categories_id', $request->input('category_id'));
        }

        if ($request->has('subcategory_id')) {
            $query->where('subcategories_id', $request->input('subcategory_id'));
        }

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
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'blane_limit');
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

    public function getBlanesByStartDate(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
                'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
                'is_diamond' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->where('expiration_date', '>=', Carbon::today()->toDateString())
            ->withActiveVendorOrNoVendor()
            ->active();

        $query->where(function ($query) {
            $query->where(function ($q) {
                $q->where('type', 'reservation')
                    ->where('nombre_max_reservation', '!=', 0);
            })
                ->orWhere(function ($q) {
                    $q->where('type', 'order')
                        ->where('stock', '!=', 0);
                })
                ->orWhereNotIn('type', ['reservation', 'order']);
        });

        if ($request->has('category_id')) {
            $query->where('categories_id', $request->input('category_id'));
        }

        if ($request->has('subcategory_id')) {
            $query->where('subcategories_id', $request->input('subcategory_id'));
        }

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
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'blane_limit');
            }
        ]);

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $blanes = $query->orderBy('start_date', 'asc')->paginate($paginationSize);

        return BlaneResource::collection($blanes);
    }

    /**
     * Get Blanes by vendor.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getBlanesByVendor(Request $request)
    {
        try {
            $request->validate([
                'commerce_name' => 'nullable|string',
                'vendor_id' => 'nullable|integer|exists:users,id',
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
                'ratings' => 'nullable|numeric|between:1,5',
                'show_inactive_subcategories' => 'nullable|boolean',
                'filter_inactive_subcategories' => 'nullable|boolean',
                'include_expired' => 'nullable|boolean',
            ]);

            // Validate that at least one of commerce_name or vendor_id is provided
            if (!$request->filled('commerce_name') && !$request->filled('vendor_id')) {
                // Auto-detect vendor if authenticated user is vendor
                if (auth()->check() && auth()->user()->hasRole('vendor')) {
                    $request->merge(['vendor_id' => auth()->id()]);
                } else {
                    return response()->json([
                        'error' => 'Either commerce_name or vendor_id is required'
                    ], 422);
                }
            }
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }
        $query = Blane::query();

        // Support both vendor_id (new way) and commerce_name (old way) for backward compatibility
        if ($request->filled('vendor_id')) {
            // New preferred way: use vendor_id
            $vendor = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            })->find($request->input('vendor_id'));

            if ($vendor) {
                $query->where('vendor_id', $vendor->id);
                $query->whereHas('vendor', function ($q) {
                    $q->where('status', 'active');
                });
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found'
                ], 404);
            }
        } elseif ($request->filled('commerce_name')) {
            // Old way: support commerce_name for backward compatibility
            $vendor = \App\Models\User::where('company_name', $request->input('commerce_name'))
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'vendor');
                })
                ->first();

            if ($vendor) {
                // Use vendor_id if available, otherwise fall back to commerce_name
                if ($vendor->id) {
                    $query->where(function ($q) use ($vendor) {
                        $q->where('vendor_id', $vendor->id)
                            ->orWhere(function ($subQ) use ($vendor) {
                                $subQ->whereNull('vendor_id')
                                    ->where('commerce_name', $vendor->company_name);
                            });
                    });
                } else {
                    $query->where('commerce_name', $request->input('commerce_name'));
                }
                $query->whereHas('vendor', function ($q) {
                    $q->where('status', 'active');
                });
            } else {
                // Fallback to old behavior for backward compatibility
                $query->where('commerce_name', $request->input('commerce_name'));
            }
        }

        if (!$request->input('include_expired', false)) {
            $query->where('expiration_date', '>', Carbon::now());
        }
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
        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }
        $paginationSize = $request->input('paginationSize', 10);
        $blanes = $query->paginate($paginationSize);
        return BlaneResource::collection($blanes);
    }

    public function getBlanesByVendorPublic(Request $request)
    {
        try {
            $request->validate([
                'id' => 'nullable|integer|exists:users,id',
                'commerce_name' => 'nullable|string',
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
                'ratings' => 'nullable|numeric|between:1,5',
                'show_inactive_subcategories' => 'nullable|boolean',
                'filter_inactive_subcategories' => 'nullable|boolean',
                'include_expired' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        // Support both id/vendor_id (new way) and commerce_name (old way) for backward compatibility
        if ($request->filled('id')) {
            $vendor = \App\Models\User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            })->find($request->input('id'));

            if ($vendor) {
                // New preferred way: use vendor_id
                $query->where(function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id)
                        ->orWhere(function ($subQ) use ($vendor) {
                            $subQ->whereNull('vendor_id')
                                ->where('commerce_name', $vendor->company_name);
                        });
                });
                $query->whereHas('vendor', function ($q) {
                    $q->where('status', 'active');
                });
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Vendor not found'
                ], 404);
            }
        } elseif ($request->filled('commerce_name')) {
            // Old way: support commerce_name for backward compatibility
            $vendor = \App\Models\User::where('company_name', $request->input('commerce_name'))
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'vendor');
                })
                ->first();

            if ($vendor) {
                // Use vendor_id if available, otherwise fall back to commerce_name
                $query->where(function ($q) use ($vendor) {
                    $q->where('vendor_id', $vendor->id)
                        ->orWhere(function ($subQ) use ($vendor) {
                            $subQ->whereNull('vendor_id')
                                ->where('commerce_name', $vendor->company_name);
                        });
                });
                $query->whereHas('vendor', function ($q) {
                    $q->where('status', 'active');
                });
            } else {
                // Fallback to old behavior for backward compatibility
                $query->where('commerce_name', $request->input('commerce_name'));
            }
        } else {
            return response()->json([
                'status' => false,
                'code' => 422,
                'message' => 'Either vendor id or commerce_name is required',
                'errors' => [
                    'input' => ['Either vendor id or commerce_name is required']
                ]
            ], 422);
        }

        if (!$request->boolean('include_expired')) {
            $query->where('expiration_date', '>', now());
        }

        if ($request->boolean('filter_inactive_subcategories')) {
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

    public function getBlanesByCategory(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
                'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
                'is_diamond' => 'nullable|boolean',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->where('expiration_date', '>', Carbon::now())
            ->withActiveVendorOrNoVendor()
            ->active();

        $query->where(function ($query) {
            $query->where(function ($q) {
                $q->where('type', 'reservation')
                    ->where('nombre_max_reservation', '!=', 0);
            })
                ->orWhere(function ($q) {
                    $q->where('type', 'order')
                        ->where('stock', '!=', 0);
                })
                ->orWhereNotIn('type', ['reservation', 'order']);
        });

        if ($request->has('category_id')) {
            $query->where('categories_id', $request->input('category_id'));
        }

        if ($request->has('subcategory_id')) {
            $query->where('subcategories_id', $request->input('subcategory_id'));
        }

        if ($request->input('filter_inactive_subcategories', false)) {
            $query->where(function ($query) {
                $query->whereNull('subcategories_id')
                    ->orWhereHas('subcategory', function ($q) {
                        $q->where('status', 'active');
                    });
            });
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhereHas('vendor', function ($vendorQuery) use ($search) {
                        $vendorQuery->where('company_name', 'like', "%$search%");
                    });
            });
        }

        $this->queryService->applyFilters($request, $query);
        $this->queryService->applySorting($request, $query);

        $query->with([
            'vendor' => function ($query) {
                $query->select('id', 'company_name', 'name', 'email', 'isDiamond', 'blane_limit');
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
     * Get Blanes with all filters.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getAllFilterBlane(Request $request)
    {
        try {
            $request->validate([
                'category_id' => 'nullable|integer|exists:categories,id',
                'subcategory_id' => 'nullable|integer|exists:subcategories,id',
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
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $query->where('expiration_date', '>=', Carbon::today()->toDateString())
            ->withActiveVendorOrNoVendor()
            ->active();

        $query->where(function ($query) {
            $query->where(function ($q) {
                $q->where('type', 'reservation')
                    ->where('nombre_max_reservation', '!=', 0);
            })
                ->orWhere(function ($q) {
                    $q->where('type', 'order')
                        ->where('stock', '!=', 0);
                })
                ->orWhereNotIn('type', ['reservation', 'order']);
        });

        if ($request->has('category_id')) {
            $query->where('categories_id', $request->input('category_id'));
        }

        if ($request->has('subcategory_id')) {
            $query->where('subcategories_id', $request->input('subcategory_id'));
        }

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

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $query->with($includes);
        }

        $paginationSize = $request->input('paginationSize', 10);
        $blanes = $query->paginate($paginationSize);

        return BlaneResource::collection($blanes);
    }

    public function getVendorByBlane(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blane_id' => 'nullable|integer|exists:blanes,id',
            'commerce_name' => 'nullable|string|max:255',
        ], [
            'blane_id.exists' => 'The specified Blane ID does not exist.',
            'commerce_name.string' => 'The commerce name must be a string.',
        ]);

        if (!$request->has('blane_id') && !$request->has('commerce_name')) {
            return response()->json([
                'success' => false,
                'message' => 'Either blane_id or commerce_name must be provided.',
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = Blane::query();

            if ($request->filled('blane_id')) {
                $query->where('id', $request->input('blane_id'));
            } elseif ($request->filled('commerce_name')) {
                $query->where('commerce_name', $request->input('commerce_name'));
            }

            $blane = $query->with(['vendor.coverMedia'])->first();

            if (!$blane) {
                return response()->json([
                    'success' => false,
                    'message' => 'Blane not found.',
                ], 404);
            }

            if (!$blane->vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'No vendor associated with this Blane.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vendor data retrieved successfully.',
                'data' => [
                    'blane' => [
                        'id' => $blane->id,
                        'name' => $blane->name,
                        'commerce_name' => $blane->commerce_name,
                    ],
                    'vendor' => $blane->vendor,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve vendor data.',
                'errors' => $this->safeExceptionMessage($e),
            ], 500);
        }
    }
}

