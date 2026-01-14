<?php

namespace App\Http\Controllers\Api\Back\V1;

use Illuminate\Http\Request;
use App\Models\Blane;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Back\V1\BlaneResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Subcategory;

/**
 * @OA\Tag(name="Back - Blane Import", description="Bulk blane import operations")
 */
class BlaneImportController extends BaseController
{
    /**
     * Import blanes from a JSON file.
     *
     * @OA\Post(
     *     path="/back/v1/blanes/import",
     *     tags={"Back - Blane Import"},
     *     summary="Import blanes from JSON data",
     *     operationId="backBlaneImport",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"blanes"},
     *         @OA\Property(property="blanes", type="array", @OA\Items(type="object",
     *             required={"name"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category", type="string"),
     *             @OA\Property(property="subcategory", type="string"),
     *             @OA\Property(property="price_current", type="number"),
     *             @OA\Property(property="price_old", type="number"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="district", type="string"),
     *             @OA\Property(property="status", type="string", enum={"active", "inactive", "expired", "waiting"}),
     *             @OA\Property(property="type", type="string", enum={"reservation", "order"})
     *         ))
     *     )),
     *     @OA\Response(response=201, description="Blanes imported successfully"),
     *     @OA\Response(response=422, description="Validation error or import errors"),
     *     @OA\Response(response=500, description="Server error")
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        try {
            Log::info('Starting import with data:', [
                'request_data' => $request->all()
            ]);

            $blanes = collect($request->blanes)->map(function ($blane, $index) {
                Log::info("Processing blane #{$index}:", [
                    'name' => $blane['name'] ?? 'unknown',
                    'original_category' => $blane['category'] ?? $blane['categorie'] ?? 'not set',
                    'original_subcategory' => $blane['subcategory'] ?? $blane['subcategorie'] ?? 'not set'
                ]);

                $booleanFields = ['online', 'partiel', 'cash', 'is_digital', 'allow_out_of_city'];
                foreach ($booleanFields as $field) {
                    if (isset($blane[$field])) {
                        $blane[$field] = in_array(strtolower($blane[$field]), ['oui', 'true', '1', 1], true);
                    }
                }

                $categoryName = $blane['category'] ?? $blane['categorie'] ?? null;
                if (!empty($categoryName)) {
                    Log::info("Looking up category for blane #{$index}:", [
                        'category_name' => $categoryName
                    ]);

                    $category = Category::where('name', $categoryName)->first();

                    if ($category) {
                        Log::info("Found category for blane #{$index}:", [
                            'category_name' => $categoryName,
                            'category_id' => $category->id
                        ]);
                        $blane['categories_id'] = $category->id;
                    } else {
                        Log::warning("Category not found for blane #{$index}:", [
                            'category_name' => $categoryName,
                            'available_categories' => Category::pluck('name')->toArray()
                        ]);
                    }
                    unset($blane['category'], $blane['categorie']);
                }

                $subcategoryName = $blane['subcategory'] ?? $blane['subcategorie'] ?? null;
                if (!empty($subcategoryName)) {
                    Log::info("Looking up subcategory for blane #{$index}:", [
                        'subcategory_name' => $subcategoryName
                    ]);

                    $subcategory = Subcategory::where('name', $subcategoryName)->first();

                    if ($subcategory) {
                        Log::info("Found subcategory for blane #{$index}:", [
                            'subcategory_name' => $subcategoryName,
                            'subcategory_id' => $subcategory->id
                        ]);
                        $blane['subcategories_id'] = $subcategory->id;
                    } else {
                        Log::warning("Subcategory not found for blane #{$index}:", [
                            'subcategory_name' => $subcategoryName,
                            'available_subcategories' => Subcategory::pluck('name')->toArray()
                        ]);
                    }
                    unset($blane['subcategory'], $blane['subcategorie']);
                }

                if (isset($blane['jours_creneaux']) && !empty($blane['jours_creneaux'])) {
                    Log::info('Processing jours_creneaux:', ['original' => $blane['jours_creneaux']]);

                    if (is_string($blane['jours_creneaux'])) {
                        $decoded = json_decode($blane['jours_creneaux'], true);

                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                            $blane['jours_creneaux'] = $decoded;
                            Log::info('Decoded from JSON:', ['result' => $blane['jours_creneaux']]);
                        } else {
                            $cleanString = str_replace(['[', ']', '"', "'"], '', $blane['jours_creneaux']);
                            Log::info('Cleaned string:', ['cleaned' => $cleanString]);

                            $days = array_map('trim', explode(',', $cleanString));
                            Log::info('Split and trimmed:', ['days' => $days]);

                            $days = array_filter($days, 'strlen');
                            Log::info('Filtered empty values:', ['days' => $days]);

                            if (!empty($days)) {
                                $blane['jours_creneaux'] = array_values($days);
                                Log::info('Final array:', ['result' => $blane['jours_creneaux']]);
                            }
                        }
                    } elseif (!is_array($blane['jours_creneaux'])) {
                        $blane['jours_creneaux'] = null;
                        Log::info('Set to null - not string or array');
                    }
                }

                return $blane;
            })->all();

            Log::info('Processed blanes data:', [
                'total_processed' => count($blanes),
                'sample_blane' => !empty($blanes) ? array_intersect_key($blanes[0], array_flip(['name', 'categories_id', 'subcategories_id', 'district', 'subdistricts', 'allow_out_of_city'])) : null
            ]);

            $request->merge(['blanes' => $blanes]);

            $request->validate([
                'blanes' => 'required|array',
                'blanes.*.name' => 'required|string|max:255',
                'blanes.*.description' => 'nullable|string',
                'blanes.*.category' => 'nullable|string|exists:categories,name',
                'blanes.*.subcategory' => 'nullable|string|exists:subcategories,name',
                'blanes.*.is_digital' => 'nullable|boolean',
                'blanes.*.price_current' => 'nullable|numeric|min:0',
                'blanes.*.price_old' => 'nullable|numeric|min:0',
                'blanes.*.advantages' => 'nullable|string',
                'blanes.*.conditions' => 'nullable|string',
                'blanes.*.city' => 'nullable|string|exists:cities,name',
                'blanes.*.district' => 'nullable|string|max:255',
                'blanes.*.subdistricts' => 'nullable|string|max:255',
                'blanes.*.status' => 'nullable|string|in:active,inactive,expired,waiting',
                'blanes.*.type' => 'nullable|string|in:reservation,order',
                'blanes.*.online' => 'nullable|boolean',
                'blanes.*.partiel' => 'nullable|boolean',
                'blanes.*.cash' => 'nullable|boolean',
                'blanes.*.partiel_field' => 'nullable|numeric|min:0',
                'blanes.*.tva' => 'nullable|numeric|min:0',
                'blanes.*.stock' => 'nullable|integer|min:0',
                'blanes.*.max_orders' => 'nullable|integer|min:0',
                'blanes.*.livraison_in_city' => 'nullable|numeric|min:0',
                'blanes.*.livraison_out_city' => 'nullable|numeric|min:0',
                'blanes.*.allow_out_of_city' => 'nullable|boolean',
                'blanes.*.start_date' => 'nullable|date|date_format:Y-m-d',
                'blanes.*.expiration_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:blanes.*.start_date',
                'blanes.*.type_time' => 'nullable|string|in:time,date',
                'blanes.*.jours_creneaux' => 'nullable|array',
                'blanes.*.dates' => 'nullable|json',
                'blanes.*.heure_debut' => 'nullable|date_format:H:i',
                'blanes.*.heure_fin' => 'nullable|date_format:H:i|after:blanes.*.heure_debut',
                'blanes.*.intervale_reservation' => 'nullable|integer|min:0',
                'blanes.*.nombre_personnes' => 'nullable|integer|min:1',
                'blanes.*.personnes_prestation' => 'nullable|integer|min:1',
                'blanes.*.nombre_max_reservation' => 'nullable|integer|min:1',
                'blanes.*.max_reservation_par_creneau' => 'nullable|integer|min:1',
                'blanes.*.availability_per_day' => 'nullable|integer|min:0',
                'blanes.*.commerce_name' => 'nullable|string|max:255',
                'blanes.*.commerce_phone' => 'nullable|string|max:20'
            ]);

            $importedBlanes = [];
            $errors = [];

            DB::beginTransaction();

            foreach ($request->blanes as $index => $blaneData) {
                try {
                    $processedData = $blaneData;

                    if (!empty($blaneData['category'])) {
                        Log::info('Processing category: ' . $blaneData['category']);
                        $category = Category::where('name', $blaneData['category'])->first();
                        Log::info('Category query result:', ['category' => $category]);

                        if ($category) {
                            $processedData['categories_id'] = $category->id;
                            Log::info('Set categories_id to: ' . $category->id);
                        } else {
                            Log::warning('Category not found: ' . $blaneData['category']);
                        }
                        unset($processedData['category']);
                    }

                    if (!empty($blaneData['subcategory'])) {
                        Log::info('Processing subcategory: ' . $blaneData['subcategory']);
                        $subcategory = Subcategory::where('name', $blaneData['subcategory'])->first();
                        Log::info('Subcategory query result:', ['subcategory' => $subcategory]);

                        if ($subcategory) {
                            $processedData['subcategories_id'] = $subcategory->id;
                            Log::info('Set subcategories_id to: ' . $subcategory->id);
                        } else {
                            Log::warning('Subcategory not found: ' . $blaneData['subcategory']);
                        }
                        unset($processedData['subcategory']);
                    }

                    if (isset($processedData['dates']) && is_string($processedData['dates'])) {
                        $processedData['dates'] = json_decode($processedData['dates'], true);
                    }

                    $processedData['slug'] = Blane::generateUniqueSlug($processedData['name']);
                    $processedData['allow_out_of_city'] = $processedData['allow_out_of_city'] ?? false;

                    $blane = Blane::create($processedData);
                    $importedBlanes[] = new BlaneResource($blane);

                } catch (\Exception $e) {
                    Log::error('Error processing blane:', [
                        'name' => $blaneData['name'] ?? 'unknown',
                    ]);

                    $errors[] = [
                        'index' => $index,
                        'name' => $blaneData['name'] ?? 'Unknown',
                    ];
                }
            }

            if (empty($errors)) {
                DB::commit();
                return response()->json([
                    'message' => 'Blanes imported successfully',
                    'data' => $importedBlanes
                ], 201);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Some blanes failed to import',
                    'errors' => $errors
                ], 422);
            }

        } catch (ValidationException $e) {
            Log::error('Validation error:', ['errors' => $e->errors()]);
            return response()->json(['error' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import error:', [
                'message' => 'An unexpected error occurred.',
            ]);
            return response()->json([
                'message' => 'Failed to import blanes',
            ], 500);
        }
    }
}






