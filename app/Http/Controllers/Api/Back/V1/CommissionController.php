<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Models\VendorCommission;
use App\Models\CommissionSettings;
use App\Models\Category;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    /**
     * List all commission rates.
     */
    public function index(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable|integer|exists:categories,id',
                'vendor_id' => 'nullable|integer|exists:users,id',
                'include_inactive' => 'nullable|boolean',
                'type' => 'nullable|in:defaults,vendor-specific,all',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $type = $request->input('type', 'all');

            $categoryDefaultsQuery = VendorCommission::with(['category'])
                ->whereNull('vendor_id');

            $vendorSpecificQuery = VendorCommission::with(['category', 'vendor'])
                ->whereNotNull('vendor_id');

            if ($request->has('category_id')) {
                $categoryDefaultsQuery->byCategory($request->category_id);
                $vendorSpecificQuery->byCategory($request->category_id);
            }

            if ($request->has('vendor_id')) {
                $vendorSpecificQuery->byVendor($request->vendor_id);
            }

            if (!$request->boolean('include_inactive')) {
                $categoryDefaultsQuery->active();
                $vendorSpecificQuery->active();
            }

            $categoryDefaults = [];
            $vendorSpecific = [];

            if ($type === 'all' || $type === 'defaults') {
                $categoryDefaults = $categoryDefaultsQuery->get();
            }

            if ($type === 'all' || $type === 'vendor-specific') {
                $vendorSpecific = $vendorSpecificQuery->get();
            }

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commissions retrieved successfully',
                'data' => [
                    'category_defaults' => $categoryDefaults,
                    'vendor_specific' => $vendorSpecific,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve commissions',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Create/Update category commission.
     * If vendor_id is null, sets category-level default commission.
     * If vendor_id is provided, sets vendor-specific override for that category.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|integer|exists:categories,id',
                'vendor_id' => 'nullable|integer|exists:users,id',
                'commission_rate' => 'required|numeric|min:0|max:100',
                'partial_commission_rate' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $isCategoryDefault = is_null($request->vendor_id);

            $commission = VendorCommission::updateOrCreate(
                [
                    'category_id' => $request->category_id,
                    'vendor_id' => $request->vendor_id,
                ],
                [
                    'commission_rate' => $request->commission_rate,
                    'partial_commission_rate' => $request->partial_commission_rate,
                    'is_active' => $request->boolean('is_active', true),
                ]
            );

            $commission->load(['category', 'vendor']);

            $message = $isCategoryDefault
                ? 'Category default commission created/updated successfully'
                : 'Vendor-specific commission created/updated successfully';

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => $message,
                'data' => $commission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to create/update commission',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update commission rate.
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'commission_rate' => 'sometimes|numeric|min:0|max:100',
                'partial_commission_rate' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $commission = VendorCommission::findOrFail($id);
            $commission->update($request->only([
                'commission_rate',
                'partial_commission_rate',
                'is_active',
            ]));

            $commission->load(['category', 'vendor']);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission updated successfully',
                'data' => $commission,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update commission',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Delete commission.
     */
    public function destroy($id)
    {
        try {
            $commission = VendorCommission::findOrFail($id);
            $commission->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete commission',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get vendor-specific rate.
     * Shows vendor-specific overrides and effective rates (defaults vs overrides).
     */
    public function getVendorRate($vendorId)
    {
        try {
            $vendor = User::findOrFail($vendorId);

            $vendorCommissions = VendorCommission::where('vendor_id', $vendorId)
                ->with('category')
                ->get();

            $categoryDefaults = VendorCommission::whereNull('vendor_id')
                ->with('category')
                ->active()
                ->get();

            $effectiveRates = [];
            $categoryDefaultsMap = $categoryDefaults->keyBy('category_id');
            $vendorCommissionsMap = $vendorCommissions->keyBy('category_id');

            foreach ($categoryDefaultsMap as $categoryId => $default) {
                $effectiveRates[] = [
                    'category_id' => $categoryId,
                    'category_name' => $default->category->name ?? null,
                    'commission_rate' => $vendorCommissionsMap->has($categoryId)
                        ? $vendorCommissionsMap[$categoryId]->commission_rate
                        : $default->commission_rate,
                    'partial_commission_rate' => $vendorCommissionsMap->has($categoryId)
                        ? $vendorCommissionsMap[$categoryId]->partial_commission_rate
                        : $default->partial_commission_rate,
                    'is_vendor_override' => $vendorCommissionsMap->has($categoryId),
                    'default_rate' => $default->commission_rate,
                    'default_partial_rate' => $default->partial_commission_rate,
                ];
            }

            foreach ($vendorCommissionsMap as $categoryId => $vendorCommission) {
                if (!$categoryDefaultsMap->has($categoryId)) {
                    $effectiveRates[] = [
                        'category_id' => $categoryId,
                        'category_name' => $vendorCommission->category->name ?? null,
                        'commission_rate' => $vendorCommission->commission_rate,
                        'partial_commission_rate' => $vendorCommission->partial_commission_rate,
                        'is_vendor_override' => true,
                        'default_rate' => null,
                        'default_partial_rate' => null,
                    ];
                }
            }

            $customRate = $vendor->custom_commission_rate;

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor commission rates retrieved successfully',
                'data' => [
                    'vendor_id' => $vendorId,
                    'vendor_name' => $vendor->name,
                    'custom_commission_rate' => $customRate,
                    'vendor_specific_overrides' => $vendorCommissions,
                    'effective_rates' => $effectiveRates,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendor rates',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Set vendor override rate.
     */
    public function setVendorRate(Request $request, $vendorId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'custom_commission_rate' => 'nullable|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $vendor = User::findOrFail($vendorId);
            $vendor->update([
                'custom_commission_rate' => $request->custom_commission_rate,
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendor commission rate updated successfully',
                'data' => [
                    'vendor_id' => $vendorId,
                    'custom_commission_rate' => $vendor->custom_commission_rate,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update vendor rate',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get global commission settings.
     */
    public function getSettings()
    {
        try {
            $settings = CommissionSettings::getSettings();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission settings retrieved successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve settings',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update global commission settings.
     */
    public function updateSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'partial_payment_commission_rate' => 'nullable|numeric|min:0|max:100',
                'vat_rate' => 'nullable|numeric|min:0|max:100',
                'daba_blane_account_iban' => 'nullable|string',
                'transfer_processing_day' => 'nullable|in:monday,tuesday,wednesday,thursday,friday',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $settings = CommissionSettings::updateSettings($request->only([
                'partial_payment_commission_rate',
                'vat_rate',
                'daba_blane_account_iban',
                'transfer_processing_day',
            ]));

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission settings updated successfully',
                'data' => $settings,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update settings',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get category-level default commissions.
     * These are the standard commissions that apply to all vendors unless overridden.
     */
    public function getCategoryDefaults(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable|integer|exists:categories,id',
                'include_inactive' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = VendorCommission::with(['category'])
                ->whereNull('vendor_id');

            if ($request->has('category_id')) {
                $query->byCategory($request->category_id);
            }

            if (!$request->boolean('include_inactive')) {
                $query->active();
            }

            $defaults = $query->get();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Category default commissions retrieved successfully',
                'data' => $defaults,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve category defaults',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * List all category default commissions without filters.
     */
    public function listAllCategoryDefaults()
    {
        try {
            $defaults = VendorCommission::with('category')
                ->whereNull('vendor_id')
                ->orderBy('category_id')
                ->get();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'All category default commissions retrieved successfully',
                'data' => $defaults,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve category default commissions',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    /**
     * Set/Update category-level default commission.
     * This sets the standard commission for a category that applies to all vendors.
     */
    public function setCategoryDefault(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|integer|exists:categories,id',
                'commission_rate' => 'required|numeric|min:0|max:100',
                'partial_commission_rate' => 'nullable|numeric|min:0|max:100',
                'is_active' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $commission = VendorCommission::updateOrCreate(
                [
                    'category_id' => $request->category_id,
                    'vendor_id' => null,
                ],
                [
                    'commission_rate' => $request->commission_rate,
                    'partial_commission_rate' => $request->partial_commission_rate,
                    'is_active' => $request->boolean('is_active', true),
                ]
            );

            $commission->load(['category']);

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'Category default commission set successfully',
                'data' => $commission,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to set category default commission',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }
}


