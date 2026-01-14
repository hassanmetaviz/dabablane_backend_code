<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Back - Blane Share", description="Blane sharing and visibility management")
 */
class BlaneShareController extends BaseController
{
    /**
     * Generate a share link for a Blane.
     *
     * @OA\Post(
     *     path="/back/v1/blanes/{id}/share",
     *     tags={"Back - Blane Share"},
     *     summary="Generate share link for a blane",
     *     operationId="backBlaneGenerateShareLink",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Share link generated", @OA\JsonContent(
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="share_token", type="string"),
     *             @OA\Property(property="visibility", type="string"),
     *             @OA\Property(property="share_url", type="string")
     *         )
     *     )),
     *     @OA\Response(response=404, description="Blane not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Delete(
     *     path="/back/v1/blanes/{id}/share",
     *     tags={"Back - Blane Share"},
     *     summary="Revoke share link for a blane",
     *     operationId="backBlaneRevokeShareLink",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Share link revoked"),
     *     @OA\Response(response=404, description="Blane not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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
     * @OA\Patch(
     *     path="/back/v1/blanes/{id}/visibility",
     *     tags={"Back - Blane Share"},
     *     summary="Update blane visibility",
     *     operationId="backBlaneUpdateVisibility",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"visibility"},
     *         @OA\Property(property="visibility", type="string", enum={"private", "public", "link"})
     *     )),
     *     @OA\Response(response=200, description="Visibility updated"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Blane not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
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






