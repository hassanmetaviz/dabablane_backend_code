<?php

namespace App\Http\Controllers\Api\Front\V1;

use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Front\V1\BannerResource;

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

    
}
