<?php

namespace App\Http\Controllers\Api\Back\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\AddOn;
use App\Models\PromoCode;
use App\Models\Purchase;
use App\Models\CommissionChart;
use App\Models\Configuration;
use App\Models\Invoice;
use App\Mail\InvoiceMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\PDF;
use App\Services\CmiService;

class VendorSubscriptionController extends Controller
{
    private $paymentService;
    private $gatewayUrl;

    public function __construct(CmiService $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->gatewayUrl = config('cmi.base_uri');
    }

    public function getPlans(Request $request)
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(function ($plan) {
                $plan->savings = $plan->original_price_ht ? ($plan->original_price_ht - $plan->price_ht) : 0;
                return $plan;
            });

        $config = Configuration::first();
        $commissionPdf = $config ? $config->commission_pdf_path : null;

        return response()->json([
            'status' => true,
            'code' => 200,
            'data' => [
                'plans' => $plans,
                'commission_pdf' => $commissionPdf,
            ],
        ], 200);
    }

    public function getAddOns(Request $request)
    {
        $addOns = AddOn::where('is_active', true)->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $addOns], 200);
    }

    public function applyPromoCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 422, 'errors' => $validator->errors()], 422);
        }

        $promoCode = PromoCode::where('code', $request->code)
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

        return response()->json(['status' => true, 'code' => 200, 'data' => $promoCode], 200);
    }

    public function createPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'add_ons' => 'nullable|array',
            'add_ons.*.id' => 'exists:add_ons,id',
            'add_ons.*.quantity' => 'integer|min:1',
            'promo_code' => 'nullable|string|max:50',
            'payment_method' => 'required|in:online,manual',
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
                'user_id' => auth()->id(),
                'plan_id' => $plan->id,
                'promo_code_id' => $promoCode ? $promoCode->id : null,
                'plan_price_ht' => $plan->price_ht,
                'discount_amount' => $discountAmount,
                'subtotal_ht' => $subtotalHt,
                'vat_amount' => $vatAmount,
                'total_ttc' => $totalTtc,
                'start_date' => now(),
                'end_date' => now()->addDays($plan->duration_days),
                'payment_method' => $request->payment_method,
                'status' => $request->payment_method === 'online' ? 'pending' : 'manual',
            ]);

            foreach ($addOnsData as $addOnData) {
                $purchase->addOns()->attach($addOnData['add_on_id'], [
                    'quantity' => $addOnData['quantity'],
                    'unit_price_ht' => $addOnData['unit_price_ht'],
                    'total_price_ht' => $addOnData['total_price_ht'],
                ]);
            }

            if ($request->payment_method === 'manual') {
                \Log::info('Starting invoice generation for manual purchase: ' . $purchase->id);
                $this->generateAndSendInvoice($purchase);
                \Log::info('Invoice generation completed for manual purchase: ' . $purchase->id);
            }
        });

        if ($request->payment_method === 'online') {
            return $this->initiateCmiPayment($purchase);
        }

        $config = Configuration::first();
        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'Purchase created, awaiting manual activation',
            'data' => [
                'purchase' => $purchase->load('plan', 'addOns', 'promoCode'),
                'billing_email' => $config ? $config->billing_email : 'contact@dabablane.com',
                'contact_phone' => $config ? $config->contact_phone : '+212615170064',
            ],
        ], 200);
    }

    public function handleCmiCallback(Request $request)
    {
        $params = $request->except('HASH');
        $receivedHash = $request->input('HASH');
        $calculatedHash = $this->paymentService->generateCallbackHash($params);

        if (!hash_equals($calculatedHash, $receivedHash)) {
            \Log::error('Invalid hash in subscription callback', $params);
            return response('FAILURE', 200);
        }

        $oid = $params['oid'] ?? '';
        $procReturnCode = $params['ProcReturnCode'] ?? '';
        $responseStatus = $params['Response'] ?? '';

        if ($procReturnCode !== '00' || $responseStatus !== 'Approved') {
            \Log::warning('Subscription payment not approved or failed', $params);
            return response('FAILURE', 200);
        }

        if (!str_starts_with($oid, 'SUB-')) {
            \Log::error('Invalid subscription oid format', $params);
            return response('FAILURE', 200);
        }

        $purchaseId = str_replace('SUB-', '', $oid);

        try {
            $purchase = Purchase::findOrFail($purchaseId);

            $purchase->update([
                'status' => 'completed',
                'payment_method' => 'online',
                'start_date' => now(),
                'end_date' => now()->addDays($purchase->plan->duration_days),
            ]);

            \DB::table('transactions')->insert([
                'purchase_id' => $purchase->id,
                'transid' => $params['TransId'] ?? '',
                'proc_return_code' => $procReturnCode,
                'response' => $responseStatus,
                'auth_code' => $params['AuthCode'] ?? '',
                'transaction_date' => $params['EXTRA.TRXDATE'] ?? '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!$purchase->invoice) {
                \Log::info('Generating invoice for successful online payment: ' . $purchase->id);
                $this->generateAndSendInvoice($purchase);
            }

            $config = Configuration::first();
            if ($purchase->invoice) {
                Mail::to($purchase->user->email)->send(new InvoiceMail($purchase, $purchase->invoice, $config));
            }

            \Log::info('Subscription payment completed successfully', [
                'purchase_id' => $purchase->id,
                'user_id' => $purchase->user_id,
                'plan' => $purchase->plan->title
            ]);

        } catch (\Exception $e) {
            \Log::error('Exception during subscription CMI callback', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            return response('FAILURE', 200);
        }

        return response('ACTION=POSTAUTH', 200);
    }

    public function getSubscriptionStatus(Request $request)
    {
        try {
            $user = auth()->user();

            $activeSubscription = Purchase::where('user_id', $user->id)
                ->where('status', Purchase::STATUS_COMPLETED)
                ->where('start_date', '<=', now())
                ->where('end_date', '>', now())
                ->with(['plan', 'addOns'])
                ->first();

            $expiredSubscription = Purchase::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->where('status', Purchase::STATUS_EXPIRED)
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('status', Purchase::STATUS_COMPLETED)
                                ->where('end_date', '<=', now());
                        });
                })
                ->with(['plan'])
                ->orderBy('end_date', 'desc')
                ->first();

            $pendingSubscription = Purchase::where('user_id', $user->id)
                ->where('status', Purchase::STATUS_PENDING)
                ->with(['plan'])
                ->first();

            $hasActiveSubscription = $activeSubscription ? true : false;
            $daysRemaining = $activeSubscription ? $activeSubscription->getDaysRemaining() : 0;
            $expiresSoon = $activeSubscription ? $activeSubscription->expiresSoon(7) : false;

            return response()->json([
                'status' => true,
                'code' => 200,
                'data' => [
                    'has_active_subscription' => $hasActiveSubscription,
                    'subscription_status' => $hasActiveSubscription ? 'active' : 'inactive',
                    'days_remaining' => $daysRemaining,
                    'expires_soon' => $expiresSoon,
                    'active_subscription' => $activeSubscription ? [
                        'id' => $activeSubscription->id,
                        'plan_title' => $activeSubscription->plan->title,
                        'start_date' => $activeSubscription->start_date,
                        'end_date' => $activeSubscription->end_date,
                        'days_remaining' => $daysRemaining,
                        'total_amount' => $activeSubscription->total_ttc,
                        'add_ons' => $activeSubscription->addOns->map(function ($addOn) {
                            return [
                                'title' => $addOn->title,
                                'price' => $addOn->price_ht,
                                'quantity' => $addOn->pivot->quantity ?? 1,
                            ];
                        }),
                    ] : null,
                    'expired_subscription' => $expiredSubscription ? [
                        'id' => $expiredSubscription->id,
                        'plan_title' => $expiredSubscription->plan->title,
                        'expired_date' => $expiredSubscription->end_date,
                        'total_amount' => $expiredSubscription->total_ttc,
                    ] : null,
                    'pending_subscription' => $pendingSubscription ? [
                        'id' => $pendingSubscription->id,
                        'plan_title' => $pendingSubscription->plan->title,
                        'total_amount' => $pendingSubscription->total_ttc,
                        'payment_method' => $pendingSubscription->payment_method,
                    ] : null,
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error getting subscription status: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to get subscription status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPurchaseHistory(Request $request)
    {
        $purchases = Purchase::where('user_id', auth()->id())
            ->with(['plan', 'addOns', 'promoCode', 'invoice'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => true, 'code' => 200, 'data' => $purchases], 200);
    }

    public function downloadInvoice(Request $request, Invoice $invoice)
    {
        if ($invoice->purchase->user_id !== auth()->id()) {
            return response()->json(['status' => false, 'code' => 403, 'message' => 'Unauthorized'], 403);
        }

        $storagePath = 'public/' . $invoice->pdf_path;
        $fullPath = storage_path('app/' . $storagePath);

        if (!file_exists($fullPath)) {
            return response()->json(['status' => false, 'code' => 404, 'message' => 'Invoice PDF file not found at: ' . $fullPath], 404);
        }

        return response()->download($fullPath, 'invoice-' . $invoice->invoice_number . '.pdf');
    }

    private function initiateCmiPayment(Purchase $purchase)
    {
        $user = $purchase->user;
        $orderData = [
            'amount' => $purchase->total_ttc,
            'oid' => 'SUB-' . $purchase->id,
            'email' => $user->email ?? '',
            'name' => $user->name ?? '',
            'tel' => $user->phone ?? '',
            'billToCity' => $user->city ?? '',
            'billToCompany' => $user->company_name ?? '',
        ];

        $params = $this->paymentService->preparePaymentParams($orderData);

        return response()->json([
            'status' => true,
            'code' => 200,
            'message' => 'Payment initiated successfully',
            'data' => $purchase->load('plan', 'addOns', 'promoCode', 'invoice'),
            'payment_info' => [
                'payment_url' => $this->gatewayUrl,
                'method' => 'post',
                'inputs' => $params
            ]
        ]);
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

            Mail::to($purchase->user->email)->send(new InvoiceMail($purchase, $invoice, $config));

            return $invoice;

        } catch (\Exception $e) {
            \Log::error('Invoice generation failed: ' . $e->getMessage());
            return $invoice;
        }
    }

    // Commission Charts for Vendors

    public function getCommissionCharts(Request $request)
    {
        $query = CommissionChart::with('category')->active();
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $commissionCharts = $query->orderBy('created_at', 'desc')->get();

        $commissionCharts->each(function ($chart) {
            $chart->download_url = url("/api/back/v1/vendor/subscriptions/commissionChartVendor/{$chart->id}/download");
        });

        return response()->json([
            'status' => true,
            'code' => 200,
            'data' => $commissionCharts
        ], 200);
    }

    public function downloadCommissionChart(Request $request, CommissionChart $commissionChart)
    {
        if (!$commissionChart->is_active) {
            return response()->json([
                'status' => false,
                'code' => 403,
                'message' => 'Commission chart is not available'
            ], 403);
        }

        try {
            $filePath = $commissionChart->file_path;

            if (!Storage::disk('public')->exists($filePath)) {
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => 'Commission chart file not found at: ' . $filePath
                ], 404);
            }

            return Storage::disk('public')->download($filePath, $commissionChart->original_filename);

        } catch (\Exception $e) {
            \Log::error('Vendor commission chart download failed: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Failed to download commission chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

