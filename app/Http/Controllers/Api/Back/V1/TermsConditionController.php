<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTermsConditionRequest;
use App\Http\Requests\UpdateTermsConditionRequest;
use App\Http\Resources\TermsConditionResource;
use App\Models\TermsCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TermsConditionController extends Controller
{
    /**
     * Display a listing of the terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Store a newly created terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Display the specified terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update the specified terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Remove the specified terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get active terms & conditions.
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
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Toggle active status of terms & conditions.
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
                'errors' => [$e->getMessage()],
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