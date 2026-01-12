<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\BlaneResource;
use App\Http\Resources\Front\V1\BlanImageResource;

class BlanController extends Controller
{
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
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

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
     * @param int $slug
     * @param Request $request
     */
    public function show($slug, Request $request)
    {
        try {
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
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

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
            return response()->json(['message' => 'Blane not found'], 404);
        }

        if ($blane->visibility === 'private') {
            return response()->json(['message' => 'This Blane is private and not accessible'], 403);
        }

        if ($blane->visibility === 'link') {
            $token = $request->input('token');
            if (!$token || $token !== $blane->share_token) {
                return response()->json(['message' => 'Access denied. Valid share token required.'], 403);
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
            return response()->json([
                'status' => false,
                'code' => 404,
                'message' => 'Blane images not found'
            ], 404);
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
            return response()->json(['message' => 'Blane not found'], 404);
        }

        return new BlaneResource($blane);
    }

}
