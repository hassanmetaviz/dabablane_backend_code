<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BlaneShareController extends BaseController
{
    /**
     * Generate a share link for a Blane.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function generateShareLink($id): JsonResponse
    {
        try {
            $blane = Blane::findOrFail($id);

            $blane->share_token = (string) Str::uuid();
            $blane->visibility = 'link';
            $blane->save();

            $shareUrl = config('app.frontend_url', 'http://localhost:5173') . "/blane/{$blane->slug}/{$blane->share_token}";

            return response()->json([
                'message' => 'Share link generated successfully',
                'data' => [
                    'share_token' => $blane->share_token,
                    'visibility' => $blane->visibility,
                    'share_url' => $shareUrl
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate share link',
            ], 500);
        }
    }

    /**
     * Revoke a share link for a Blane.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function revokeShareLink($id): JsonResponse
    {
        try {
            $blane = Blane::findOrFail($id);

            $blane->share_token = null;
            $blane->visibility = 'private';
            $blane->save();

            return response()->json([
                'message' => 'Share link revoked successfully',
                'data' => [
                    'visibility' => $blane->visibility
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to revoke share link',
            ], 500);
        }
    }

    /**
     * Update visibility of a Blane.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateVisibility(Request $request, $id): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'visibility' => 'required|string|in:private,public,link',
            ]);

            $blane = Blane::findOrFail($id);

            if ($validatedData['visibility'] === 'link' && !$blane->share_token) {
                $blane->share_token = (string) Str::uuid();
            }

            if ($validatedData['visibility'] !== 'link' && $blane->visibility === 'link') {
                $blane->share_token = null;
            }

            $blane->visibility = $validatedData['visibility'];
            $blane->save();

            $responseData = [
                'message' => 'Blane visibility updated successfully',
                'data' => [
                    'visibility' => $blane->visibility,
                ]
            ];

            if ($blane->visibility === 'link') {
                $responseData['data']['share_token'] = $blane->share_token;
                $responseData['data']['share_url'] = config('app.frontend_url', 'http://localhost:5173') . "/blane/{$blane->slug}/{$blane->share_token}";
            }

            return response()->json($responseData, 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => $e->errors()], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update visibility',
            ], 500);
        }
    }
}






