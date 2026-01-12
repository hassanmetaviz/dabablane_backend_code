<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\AddOn;
use App\Models\PromoCode;
use App\Models\Configuration;
use App\Models\Purchase;
use App\Models\Invoice;
use App\Models\CommissionChart;
use App\Models\Category;
use App\Mail\InvoiceMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\PDF;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class AdminSubscriptionController extends Controller
{

    public function createPlan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:plans',
            'price_ht' => 'required|numeric|min:0',
            'original_price_ht' => 'nullable|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'is_recommended' => 'boolean',
            'display_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $plan = Plan::create($validator->validated());

        return response()->json(['status' => true, 'code' => 201, 'message' => 'Plan created', 'data' => $plan], 201);
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:plans,slug,' . $plan->id,
            'price_ht' => 'sometimes|numeric|min:0',
            'original_price_ht' => 'nullable|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'is_recommended' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $plan->update($validator->validated());

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Plan updated', 'data' => $plan], 200);
    }

    public function deletePlan(Request $request, Plan $plan)
    {
        try {
            $purchaseCount = Purchase::where('plan_id', $plan->id)->count();

            if ($purchaseCount > 0) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Cannot delete plan. It is being used in ' . $purchaseCount . ' purchase(s).',
                    'data' => [
                        'purchase_count' => $purchaseCount,
                        'alternative' => 'You can deactivate the plan instead by setting is_active to false.'
                    ]
                ], 422);
            }

            $plan->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Plan deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error deleting plan: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listPlans(Request $request)
    {
        $plans = Plan::orderBy('display_order')->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $plans], 200);
    }

    // Add-ons
    public function createAddOn(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'price_ht' => 'required|numeric|min:0',
            'tooltip' => 'nullable|string|max:255',
            'max_quantity' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $addOn = AddOn::create($validator->validated());

        return response()->json(['status' => true, 'code' => 201, 'message' => 'Add-on created', 'data' => $addOn], 201);
    }

    public function updateAddOn(Request $request, AddOn $addOn)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'price_ht' => 'sometimes|numeric|min:0',
            'tooltip' => 'nullable|string|max:255',
            'max_quantity' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $addOn->update($validator->validated());

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Add-on updated', 'data' => $addOn], 200);
    }

    public function deleteAddOn(Request $request, AddOn $addOn)
    {
        try {
            $purchaseCount = DB::table('purchase_add_ons')
                ->where('add_on_id', $addOn->id)
                ->count();

            if ($purchaseCount > 0) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Cannot delete add-on. It is being used in ' . $purchaseCount . ' purchase(s).',
                    'data' => [
                        'purchase_count' => $purchaseCount,
                        'alternative' => 'You can deactivate the add-on instead by setting is_active to false.'
                    ]
                ], 422);
            }

            $addOn->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Add-on deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error deleting add-on: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete add-on',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listAddOns(Request $request)
    {
        $addOns = AddOn::all();
        return response()->json(['status' => true, 'code' => 200, 'data' => $addOns], 200);
    }

    public function createPromoCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:promo_codes',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $promoCode = PromoCode::create($validator->validated());

        return response()->json(['status' => true, 'code' => 201, 'message' => 'Promo code created', 'data' => $promoCode], 201);
    }

    public function updatePromoCode(Request $request, PromoCode $promoCode)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:50|unique:promo_codes,code,' . $promoCode->id,
            'discount_percentage' => 'sometimes|numeric|min:0|max:100',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $promoCode->update($validator->validated());

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Promo code updated', 'data' => $promoCode], 200);
    }

    public function deletePromoCode(Request $request, PromoCode $promoCode)
    {
        try {
            $purchaseCount = Purchase::where('promo_code_id', $promoCode->id)->count();

            if ($purchaseCount > 0) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Cannot delete promo code. It is being used in ' . $purchaseCount . ' purchase(s).',
                    'data' => [
                        'purchase_count' => $purchaseCount,
                        'alternative' => 'You can deactivate the promo code instead by setting is_active to false.'
                    ]
                ], 422);
            }

            $promoCode->delete();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Promo code deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error deleting promo code: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete promo code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listPromoCodes(Request $request)
    {
        $promoCodes = PromoCode::all();
        return response()->json(['status' => true, 'code' => 200, 'data' => $promoCodes], 200);
    }

    public function updateConfiguration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'billing_email' => 'nullable|email',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:20',
            'invoice_logo_path' => 'nullable|file|mimes:png,jpg,jpeg',
            'invoice_legal_mentions' => 'nullable|string',
            'invoice_prefix' => 'nullable|string|max:20',
            'commission_pdf_path' => 'nullable|file|mimes:pdf',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $config = Configuration::firstOrNew([]);
        $data = $request->only([
            'billing_email',
            'contact_email',
            'contact_phone',
            'invoice_legal_mentions',
            'invoice_prefix',
        ]);

        if ($request->hasFile('invoice_logo_path')) {
            $path = $request->file('invoice_logo_path')->store('public/logos');
            $data['invoice_logo_path'] = Storage::url($path);
        }

        if ($request->hasFile('commission_pdf_path')) {
            $path = $request->file('commission_pdf_path')->store('public/commissions');
            $data['commission_pdf_path'] = Storage::url($path);
        }

        $config->fill($data)->save();

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Configuration updated', 'data' => $config], 200);
    }

    public function getConfiguration(Request $request)
    {
        $config = Configuration::first() ?? new Configuration([
            'billing_email' => 'contact@dabablane.com',
            'contact_email' => 'contact@dabablane.com',
            'contact_phone' => '+212615170064',
            'invoice_prefix' => 'DABA-INV-',
        ]);

        return response()->json(['status' => true, 'code' => 200, 'data' => $config], 200);
    }
    // Manual Activation
    public function activatePurchase(Request $request, Purchase $purchase)
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:online,manual',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $purchase->update([
            'payment_method' => $request->payment_method,
            'status' => 'completed',
            'start_date' => now(),
            'end_date' => now()->addDays($purchase->plan->duration_days),
        ]);

        $this->updateOrCreateInvoice($purchase);

        return response()->json(['status' => true, 'code' => 200, 'message' => 'Purchase activated'], 200);
    }

    private function updateOrCreateInvoice(Purchase $purchase)
    {
        $config = Configuration::first() ?? new Configuration([
            'billing_email' => 'contact@dabablane.com',
            'contact_email' => 'contact@dabablane.com',
            'contact_phone' => '+212615170064',
            'invoice_prefix' => 'DABA-INV-',
        ]);

        $invoice = Invoice::where('purchase_id', $purchase->id)->first();

        if ($invoice) {
            \Log::info('Updating existing invoice for purchase: ' . $purchase->id);

            $this->generateInvoicePdf($purchase, $invoice, $config);

            Mail::to($purchase->user->email)->send(new InvoiceMail($purchase, $invoice, $config));

        } else {
            \Log::info('Creating new invoice for purchase: ' . $purchase->id);
            $this->generateAndSendInvoice($purchase);
        }

        return $invoice;
    }

    private function generateInvoicePdf(Purchase $purchase, Invoice $invoice, Configuration $config)
    {
        try {
            $pdfFileName = 'invoice-' . $purchase->id . '-' . now()->format('YmdHis') . '.pdf';
            $storageDirectory = 'invoices';
            $storagePath = 'public/uploads/' . $storageDirectory . '/' . $pdfFileName;
            $publicPath = 'uploads/' . $storageDirectory . '/' . $pdfFileName;

            $directory = storage_path('app/public/uploads/' . $storageDirectory);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $pdf = app(PDF::class);
            $pdf->loadView('invoices.invoice-pdf', [
                'purchase' => $purchase->load('plan', 'addOns', 'promoCode', 'user'),
                'invoice' => $invoice,
                'config' => $config,
            ]);

            $pdf->save(storage_path('app/' . $storagePath));

            $fullPath = storage_path('app/' . $storagePath);
            if (!file_exists($fullPath)) {
                \Log::error('PDF file was not created: ' . $fullPath);
                throw new \Exception('PDF file creation failed');
            }

            $invoice->update([
                'pdf_path' => $publicPath,
                'issued_at' => now(),
            ]);

            \Log::info('PDF updated successfully: ' . $fullPath);

        } catch (\Exception $e) {
            \Log::error('Invoice PDF update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function generateAndSendInvoice(Purchase $purchase)
    {
        $config = Configuration::first() ?? new Configuration([
            'billing_email' => 'contact@dabablane.com',
            'contact_email' => 'contact@dabablane.com',
            'contact_phone' => '+212615170064',
            'invoice_prefix' => 'DABA-INV-',
        ]);

        $invoiceNumber = $config->invoice_prefix . $purchase->id . '-' . now()->format('Ymd');

        $pdfFileName = 'invoice-' . $purchase->id . '-' . now()->format('YmdHis') . '.pdf';
        $storageDirectory = 'invoices';

        $storagePath = 'public/uploads/' . $storageDirectory . '/' . $pdfFileName;

        $publicPath = 'uploads/' . $storageDirectory . '/' . $pdfFileName;

        $invoice = Invoice::create([
            'purchase_id' => $purchase->id,
            'invoice_number' => $invoiceNumber,
            'pdf_path' => $publicPath,
            'issued_at' => now(),
        ]);

        try {
            $directory = storage_path('app/public/uploads/' . $storageDirectory);
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }

            $pdf = app(PDF::class);
            $pdf->loadView('invoices.invoice-pdf', [
                'purchase' => $purchase->load('plan', 'addOns', 'promoCode', 'user'),
                'invoice' => $invoice,
                'config' => $config,
            ]);

            $pdf->save(storage_path('app/' . $storagePath));

            $fullPath = storage_path('app/' . $storagePath);
            if (!file_exists($fullPath)) {
                \Log::error('PDF file was not created: ' . $fullPath);
                throw new \Exception('PDF file creation failed');
            }

            \Log::info('PDF created successfully: ' . $fullPath);
            \Log::info('Public path: ' . $publicPath);

            return $invoice;

        } catch (\Exception $e) {
            \Log::error('Invoice generation failed: ' . $e->getMessage());
            return $invoice;
        }
    }
    public function getVendorsWithSubscriptions(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:pending,completed,manual,cancelled',
                'plan_id' => 'nullable|exists:plans,id',
                'payment_method' => 'nullable|in:online,manual',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
            }

            $perPage = $request->per_page ?? 15;

            $query = Purchase::with([
                'user' => function ($query) {
                    $query->select('id', 'name', 'email', 'phone', 'company_name', 'created_at');
                },
                'plan' => function ($query) {
                    $query->select('id', 'title', 'slug', 'price_ht', 'duration_days');
                },
                'addOns' => function ($query) {
                    $query->select(
                        'add_ons.id',
                        'add_ons.title',
                        'add_ons.price_ht',
                        'purchase_add_ons.quantity',
                        'purchase_add_ons.unit_price_ht',
                        'purchase_add_ons.total_price_ht'
                    );
                },
                'promoCode' => function ($query) {
                    $query->select('id', 'code', 'discount_percentage');
                },
                'invoice'
            ])
                ->whereHas('user', function ($query) {
                    $query->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'vendor');
                    });
                });

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('plan_id')) {
                $query->where('plan_id', $request->plan_id);
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->whereHas('user', function ($userQuery) use ($searchTerm) {
                    $userQuery->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%")
                        ->orWhere('company_name', 'like', "%{$searchTerm}%");
                });
            }

            $purchases = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $vendorsData = $purchases->map(function ($purchase) {
                return [
                    'purchase_id' => $purchase->id,
                    'purchase_status' => $purchase->status,
                    'payment_method' => $purchase->payment_method,
                    'purchase_date' => $purchase->created_at,
                    'start_date' => $purchase->start_date,
                    'end_date' => $purchase->end_date,
                    'total_amount' => [
                        'subtotal_ht' => $purchase->subtotal_ht,
                        'vat_amount' => $purchase->vat_amount,
                        'total_ttc' => $purchase->total_ttc,
                        'discount_amount' => $purchase->discount_amount,
                    ],
                    'vendor' => [
                        'id' => $purchase->user->id,
                        'name' => $purchase->user->name,
                        'email' => $purchase->user->email,
                        'phone' => $purchase->user->phone,
                        'company_name' => $purchase->user->company_name,
                        'registration_date' => $purchase->user->created_at,
                    ],
                    'plan' => [
                        'id' => $purchase->plan->id,
                        'title' => $purchase->plan->title,
                        'slug' => $purchase->plan->slug,
                        'price_ht' => $purchase->plan->price_ht,
                        'duration_days' => $purchase->plan->duration_days,
                    ],
                    'add_ons' => $purchase->addOns->map(function ($addOn) {
                        return [
                            'id' => $addOn->id,
                            'title' => $addOn->title,
                            'price_ht' => $addOn->price_ht,
                            'quantity' => $addOn->pivot->quantity,
                            'unit_price_ht' => $addOn->pivot->unit_price_ht,
                            'total_price_ht' => $addOn->pivot->total_price_ht,
                        ];
                    }),
                    'promo_code' => $purchase->promoCode ? [
                        'id' => $purchase->promoCode->id,
                        'code' => $purchase->promoCode->code,
                        'discount_percentage' => $purchase->promoCode->discount_percentage,
                    ] : null,
                    'invoice' => $purchase->invoice ? [
                        'id' => $purchase->invoice->id,
                        'invoice_number' => $purchase->invoice->invoice_number,
                        'issued_at' => $purchase->invoice->issued_at,
                        'pdf_path' => $purchase->invoice->pdf_path,
                        'download_url' => url("/api/back/v1/admin/subscriptions/invoices/{$purchase->invoice->id}/download"),
                    ] : null,
                ];
            });

            $summary = [
                'total_vendors' => $purchases->total(),
                'total_revenue' => $purchases->sum('total_ttc'),
                'status_counts' => [
                    'completed' => Purchase::whereHas('user', function ($query) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'vendor');
                        });
                    })->where('status', 'completed')->count(),
                    'pending' => Purchase::whereHas('user', function ($query) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'vendor');
                        });
                    })->where('status', 'pending')->count(),
                    'manual' => Purchase::whereHas('user', function ($query) {
                        $query->whereHas('roles', function ($roleQuery) {
                            $roleQuery->where('name', 'vendor');
                        });
                    })->where('status', 'manual')->count(),
                ]
            ];

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendors with subscriptions retrieved successfully',
                'data' => [
                    'vendors' => $vendorsData,
                    'summary' => $summary,
                    'pagination' => [
                        'current_page' => $purchases->currentPage(),
                        'last_page' => $purchases->lastPage(),
                        'per_page' => $purchases->perPage(),
                        'total' => $purchases->total(),
                    ]
                ]
            ], 200);

        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendors with subscriptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function createManualPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'plan_id' => 'required|exists:plans,id',
            'add_ons' => 'nullable|array',
            'add_ons.*.id' => 'exists:add_ons,id',
            'add_ons.*.quantity' => 'integer|min:1',
            'promo_code' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $plan = Plan::findOrFail($request->plan_id);
        $promoCode = null;
        $discountAmount = 0;

        if ($request->promo_code) {
            $promoCode = PromoCode::where('code', $request->promo_code)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', now());
                })
                ->first();

            if (!$promoCode) {
                return response()->json(['status' => false, 'code' => 404, 'message' => 'Invalid or expired promo code'], 404);
            }
        }

        $subtotalHt = $plan->price_ht;
        $addOnsData = [];

        if ($request->add_ons) {
            foreach ($request->add_ons as $addOnInput) {
                $addOn = AddOn::findOrFail($addOnInput['id']);
                if ($addOnInput['quantity'] > $addOn->max_quantity) {
                    return response()->json(['status' => false, 'code' => 422, 'message' => "Max quantity for {$addOn->title} is {$addOn->max_quantity}"], 422);
                }
                $totalAddOnPrice = $addOn->price_ht * $addOnInput['quantity'];
                $subtotalHt += $totalAddOnPrice;
                $addOnsData[] = [
                    'add_on_id' => $addOn->id,
                    'quantity' => $addOnInput['quantity'],
                    'unit_price_ht' => $addOn->price_ht,
                    'total_price_ht' => $totalAddOnPrice,
                ];
            }
        }

        if ($promoCode) {
            $discountAmount = $subtotalHt * ($promoCode->discount_percentage / 100);
        }

        $subtotalHtAfterDiscount = $subtotalHt - $discountAmount;
        $vatAmount = $subtotalHtAfterDiscount * 0.20;
        $totalTtc = $subtotalHtAfterDiscount + $vatAmount;

        $purchase = null;

        DB::transaction(function () use ($request, $plan, $promoCode, $subtotalHt, $discountAmount, $vatAmount, $totalTtc, $addOnsData, &$purchase) {
            $purchase = Purchase::create([
                'user_id' => $request->user_id,
                'plan_id' => $plan->id,
                'promo_code_id' => $promoCode ? $promoCode->id : null,
                'plan_price_ht' => $plan->price_ht,
                'discount_amount' => $discountAmount,
                'subtotal_ht' => $subtotalHt,
                'vat_amount' => $vatAmount,
                'total_ttc' => $totalTtc,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'payment_method' => 'manual',
                'status' => 'completed',
            ]);

            foreach ($addOnsData as $addOnData) {
                $purchase->addOns()->attach($addOnData['add_on_id'], [
                    'quantity' => $addOnData['quantity'],
                    'unit_price_ht' => $addOnData['unit_price_ht'],
                    'total_price_ht' => $addOnData['total_price_ht'],
                ]);
            }

            \Log::info('Starting invoice generation for admin manual purchase: ' . $purchase->id);
            $this->generateAndSendInvoice($purchase);
            \Log::info('Invoice generation completed for admin manual purchase: ' . $purchase->id);
        });

        $config = Configuration::first();

        return response()->json([
            'status' => true,
            'code' => 201,
            'message' => 'Manual purchase created and activated successfully',
            'data' => [
                'purchase' => $purchase->load('plan', 'addOns', 'promoCode', 'user'),
                'billing_email' => $config ? $config->billing_email : 'contact@dabablane.com',
                'contact_phone' => $config ? $config->contact_phone : '+212615170064',
            ],
        ], 201);
    }

    public function getAllVendorsList(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'nullable|string|in:pending,active,suspended,blocked',
                'search' => 'nullable|string|max:255',
                'include' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = User::whereHas('roles', function ($q) {
                $q->where('name', 'vendor');
            });

            $status = $request->input('status');
            if ($status) {
                if ($status === 'active') {
                    $query->active();
                } elseif ($status === 'pending') {
                    $query->pending();
                } else {
                    $query->where('status', $status);
                }
            }

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            }

            if ($request->has('include')) {
                $includes = explode(',', $request->input('include'));
                $query->with($includes);
            }

            $vendors = $query->get();

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Vendors retrieved successfully',
                'data' => AuthResource::collection($vendors),
                'meta' => [
                    'total_count' => $vendors->count(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to retrieve vendors',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }

    public function uploadCommissionChart(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'commission_file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:10240', // 10MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        try {
            $category = Category::findOrFail($request->category_id);
            $file = $request->file('commission_file');

            $existingCharts = CommissionChart::where('category_id', $category->id)->get();
            foreach ($existingCharts as $existingChart) {
                if (Storage::disk('public')->exists($existingChart->file_path)) {
                    Storage::disk('public')->delete($existingChart->file_path);
                    \Log::info('Deleted existing file: ' . $existingChart->file_path);
                } else {
                    \Log::warning('Existing file not found: ' . $existingChart->file_path);
                }
                $existingChart->delete();
            }

            $originalFilename = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = 'commission-chart-' . $category->slug . '-' . time() . '.' . $extension;

            $storageDirectory = 'uploads/commission_charts';

            $storedPath = $file->storeAs($storageDirectory, $filename, 'public');

            \Log::info('File storage details:', [
                'original_filename' => $originalFilename,
                'generated_filename' => $filename,
                'stored_path' => $storedPath,
                'full_disk_path' => Storage::disk('public')->path($storedPath),
                'file_exists' => Storage::disk('public')->exists($storedPath) ? 'YES' : 'NO',
            ]);

            $commissionChart = CommissionChart::create([
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'category_id' => $category->id,
                'file_path' => $storedPath,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_active' => true,
            ]);

            \Log::info('Commission chart uploaded successfully for category: ' . $category->name);

            return response()->json([
                'status' => true,
                'code' => 201,
                'message' => 'Commission chart uploaded successfully',
                'data' => $commissionChart->load('category')
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Commission chart upload failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to upload commission chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateCommissionChart(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'sometimes|exists:categories,id',
            'commission_file' => 'sometimes|file|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg|max:10240',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        try {
            $commissionChart = CommissionChart::findOrFail($id);

            // DEBUG: Extensive logging
            \Log::info('=== START COMMISSION CHART UPDATE DEBUG ===');
            \Log::info('Commission Chart ID: ' . $commissionChart->id);
            \Log::info('Request has file: ' . ($request->hasFile('commission_file') ? 'YES' : 'NO'));
            \Log::info('Request files: ', $request->allFiles());
            \Log::info('All request data: ', $request->all());

            // Check file input specifically
            $file = $request->file('commission_file');
            \Log::info('File object: ' . ($file ? get_class($file) : 'NULL'));
            if ($file) {
                \Log::info('File details:', [
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                ]);
            }
            \Log::info('=== END COMMISSION CHART UPDATE DEBUG ===');

            $updateData = $request->only(['category_id', 'is_active']);

            if ($request->hasFile('commission_file')) {
                $file = $request->file('commission_file');
                $category = Category::find($request->category_id ?? $commissionChart->category_id);

                \Log::info('Processing file upload for commission chart update:', [
                    'commission_chart_id' => $commissionChart->id,
                    'old_file_path' => $commissionChart->file_path,
                    'old_filename' => $commissionChart->filename,
                ]);

                if (Storage::disk('public')->exists($commissionChart->file_path)) {
                    Storage::disk('public')->delete($commissionChart->file_path);
                    \Log::info('Old file deleted: ' . $commissionChart->file_path);
                } else {
                    \Log::warning('Old file not found for deletion: ' . $commissionChart->file_path);
                }

                $originalFilename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $filename = 'commission-chart-' . $category->slug . '-' . time() . '.' . $extension;

                $storageDirectory = 'uploads/commission_charts';
                $storedPath = $file->storeAs($storageDirectory, $filename, 'public');

                \Log::info('New file stored:', [
                    'new_filename' => $filename,
                    'stored_path' => $storedPath,
                    'file_exists' => Storage::disk('public')->exists($storedPath) ? 'YES' : 'NO',
                    'full_path' => Storage::disk('public')->path($storedPath),
                ]);

                $updateData = array_merge($updateData, [
                    'filename' => $filename,
                    'original_filename' => $originalFilename,
                    'file_path' => $storedPath,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            } else {
                \Log::warning('No file detected in request for commission chart update');
            }

            $commissionChart->update($updateData);

            $commissionChart->refresh();

            \Log::info('Commission chart update completed:', [
                'commission_chart_id' => $commissionChart->id,
                'new_file_path' => $commissionChart->file_path,
                'new_filename' => $commissionChart->filename,
                'file_exists_in_db' => Storage::disk('public')->exists($commissionChart->file_path) ? 'YES' : 'NO',
                'updated_fields' => array_keys($updateData),
            ]);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission chart updated successfully',
                'data' => $commissionChart->load('category')
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Commission chart update failed: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to update commission chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteCommissionChart(Request $request, CommissionChart $commissionChart)
    {
        try {
            \Log::info('Deleting commission chart:', [
                'commission_chart_id' => $commissionChart->id,
                'file_path' => $commissionChart->file_path,
            ]);

            if (Storage::disk('public')->exists($commissionChart->file_path)) {
                Storage::disk('public')->delete($commissionChart->file_path);
                \Log::info('File deleted from storage: ' . $commissionChart->file_path);
            } else {
                \Log::warning('File not found for deletion: ' . $commissionChart->file_path);
            }

            $commissionChart->delete();

            \Log::info('Commission chart deleted successfully: ' . $commissionChart->id);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Commission chart deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Commission chart deletion failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to delete commission chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listCommissionCharts(Request $request)
    {
        $query = CommissionChart::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $commissionCharts = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => true,
            'code' => 200,
            'data' => $commissionCharts
        ], 200);
    }

    public function downloadCommissionChart(Request $request, CommissionChart $commissionChart)
    {
        try {

            $filePath = $commissionChart->file_path;

            \Log::info('Download attempt:', [
                'commission_chart_id' => $commissionChart->id,
                'file_path' => $filePath,
                'file_exists' => Storage::disk('public')->exists($filePath) ? 'YES' : 'NO',
            ]);

            if (!Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Commission chart file not found at: ' . $filePath
                ], 404);
            }

            return Storage::disk('public')->download($filePath, $commissionChart->original_filename);

        } catch (\Exception $e) {
            \Log::error('Commission chart download failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to download commission chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}

