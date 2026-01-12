<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\BlaneResource;

class VendorBlanController extends BlanController
{
    /**
     * Display the specified Blane (Vendor version - no restrictions on visibility).
     *
     * @param string $slug
     * @param Request $request
     * @return JsonResponse|BlaneResource
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
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        }

        $query = Blane::query();

        $blane = $query->where('slug', $slug)->first();

        if (!$blane) {
            return response()->json(['message' => 'Blane not found'], 404);
        }

        if ($request->has('include')) {
            $includes = explode(',', $request->input('include'));
            $blane->load($includes);
        }

        return new BlaneResource($blane);
    }
}























