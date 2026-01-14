<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\StoreTermsConditionRequest;
use App\Http\Requests\UpdateTermsConditionRequest;
use App\Http\Resources\TermsConditionResource;
use App\Models\TermsCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="TermsCondition",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="type", type="string", enum={"user", "vendor"}),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="file_name", type="string"),
 *     @OA\Property(property="file_path", type="string"),
 *     @OA\Property(property="file_size", type="string"),
 *     @OA\Property(property="version", type="string"),
 *     @OA\Property(property="is_active", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TermsConditionController extends BaseController
{
    /**
     * Display a listing of the terms & conditions.
     *
     * @OA\Get(
     *     path="/back/v1/terms-conditions",
     *     tags={"Back - Terms & Conditions"},
     *     summary="List all terms & conditions",
     *     operationId="backTermsConditionsIndex",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"user", "vendor"})),
     *     @OA\Response(response=200, description="Terms retrieved", @OA\JsonContent(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TermsCondition")))),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'per_page' => 'nullable|integer|min:1|max:100',
                'is_active' => 'nullable|boolean',
                'type' => 'nullable|in:user,vendor',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = $request->input('per_page', 10);
            $isActive = $request->input('is_active');
            $type = $request->input('type');

            $query = TermsCondition::latestFirst();

            if ($type !== null) {
                $query->ofType($type);
            }

            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }

            $terms = $query->paginate($perPage);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Terms & conditions retrieved successfully',
                'data' => TermsConditionResource::collection($terms),
                'meta' => [
                    'total' => $terms->total(),
                    'current_page' => $terms->currentPage(),
                    'last_page' => $terms->lastPage(),
                    'per_page' => $terms->perPage(),
                    'from' => $terms->firstItem(),
                    'to' => $terms->lastItem(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Store a newly created terms & conditions.
     *
     * @OA\Post(
     *     path="/back/v1/terms-conditions",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Upload new terms & conditions PDF",
     *     operationId="backTermsConditionsStore",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             required={"type", "title", "pdf_file"},
     *             @OA\Property(property="type", type="string", enum={"user", "vendor"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="pdf_file", type="string", format="binary"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="version", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )),
     *     @OA\Response(response=201, description="Terms created"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(StoreTermsConditionRequest $request)
    {
        try {
            $file = $request->file('pdf_file');

            $fileName = 'terms_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('terms_conditions', $fileName, 'public');

            $fileSize = $this->formatFileSize($file->getSize());

            $terms = TermsCondition::create([
                'type' => $request->type,
                'title' => $request->title,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_type' => 'pdf',
                'description' => $request->description,
                'version' => $request->version,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'Terms & Conditions PDF uploaded successfully',
                'data' => new TermsConditionResource($terms),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to upload terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Display the specified terms & conditions.
     *
     * @OA\Get(
     *     path="/back/v1/terms-conditions/{id}",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Get specific terms & conditions",
     *     operationId="backTermsConditionsShow",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Terms retrieved"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show($id)
    {
        try {
            $terms = TermsCondition::find($id);

            if (!$terms) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Terms & conditions not found',
                    'errors' => ['id' => ['Terms & conditions not found']],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Terms & conditions retrieved successfully',
                'data' => new TermsConditionResource($terms),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Update the specified terms & conditions.
     *
     * @OA\Put(
     *     path="/back/v1/terms-conditions/{id}",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Update terms & conditions",
     *     operationId="backTermsConditionsUpdate",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(
     *         mediaType="multipart/form-data",
     *         @OA\Schema(
     *             @OA\Property(property="type", type="string", enum={"user", "vendor"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="pdf_file", type="string", format="binary"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="version", type="string"),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     )),
     *     @OA\Response(response=200, description="Terms updated"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(UpdateTermsConditionRequest $request, $id)
    {
        try {
            $terms = TermsCondition::find($id);

            if (!$terms) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Terms & conditions not found',
                    'errors' => ['id' => ['Terms & conditions not found']],
                ], 404);
            }

            $updateData = $request->only(['type', 'title', 'description', 'version']);

            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            if ($request->hasFile('pdf_file')) {
                $file = $request->file('pdf_file');

                Storage::disk('public')->delete($terms->file_path);

                $fileName = 'terms_' . time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                $filePath = $file->storeAs('terms_conditions', $fileName, 'public');

                $updateData['file_name'] = $file->getClientOriginalName();
                $updateData['file_path'] = $filePath;
                $updateData['file_size'] = $this->formatFileSize($file->getSize());
            }

            $terms->update($updateData);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Terms & conditions updated successfully',
                'data' => new TermsConditionResource($terms->fresh()),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Remove the specified terms & conditions.
     *
     * @OA\Delete(
     *     path="/back/v1/terms-conditions/{id}",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Delete terms & conditions",
     *     operationId="backTermsConditionsDestroy",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Terms deleted"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy($id)
    {
        try {
            $terms = TermsCondition::find($id);

            if (!$terms) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Terms & conditions not found',
                    'errors' => ['id' => ['Terms & conditions not found']],
                ], 404);
            }

            Storage::disk('public')->delete($terms->file_path);

            $terms->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Terms & conditions deleted successfully',
                'data' => null,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Get active terms & conditions.
     *
     * @OA\Get(
     *     path="/back/v1/terms-conditions/active",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Get active terms & conditions",
     *     operationId="backTermsConditionsGetActive",
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string", enum={"user", "vendor"})),
     *     @OA\Response(response=200, description="Active terms retrieved"),
     *     @OA\Response(response=404, description="No active terms found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getActiveTerms(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:user,vendor',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $type = $request->input('type', 'user');

            $query = TermsCondition::active()->ofType($type)->latestFirst();
            $terms = $query->first();

            if (!$terms) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'No active terms & conditions found',
                    'errors' => ['terms' => ['No active terms & conditions found']],
                ], 404);
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Active terms & conditions retrieved successfully',
                'data' => new TermsConditionResource($terms),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve active terms & conditions',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Toggle active status of terms & conditions.
     *
     * @OA\Patch(
     *     path="/back/v1/terms-conditions/{id}/toggle-status",
     *     tags={"Back - Terms & Conditions"},
     *     summary="Toggle terms & conditions active status",
     *     operationId="backTermsConditionsToggleStatus",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Status toggled"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function toggleStatus($id)
    {
        try {
            $terms = TermsCondition::find($id);

            if (!$terms) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Terms & conditions not found',
                    'errors' => ['id' => ['Terms & conditions not found']],
                ], 404);
            }

            $terms->update(['is_active' => !$terms->is_active]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Terms & conditions status updated successfully',
                'data' => new TermsConditionResource($terms->fresh()),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update terms & conditions status',
                'errors' => [$this->safeExceptionMessage($e)],
            ], 500);
        }
    }

    /**
     * Format file size to human readable format.
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}