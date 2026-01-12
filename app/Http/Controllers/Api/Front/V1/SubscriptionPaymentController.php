<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\Configuration;
use App\Services\CmiService;
use App\Mail\InvoiceMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentController extends Controller
{
    private $paymentService;
    private $gatewayUrl;

    public function __construct(CmiService $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->gatewayUrl = config('cmi.base_uri');
    }

    /**
     * Initiate subscription payment
     */
    public function initiatePayment(Request $request)
    {
        try {
            $request->validate([
                'purchase_id' => 'required|integer|exists:purchases,id'
            ]);

            $purchase = Purchase::with(['user', 'plan', 'addOns', 'promoCode'])->findOrFail($request->purchase_id);

            if ($purchase->user_id !== auth()->id()) {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'message' => 'Unauthorized access to this purchase'
                ], 403);
            }

            if ($purchase->status !== 'pending') {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'message' => 'Purchase is not in pending status'
                ], 400);
            }

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
                'data' => [
                    'purchase' => $purchase,
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Subscription payment initiation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'An error occurred while initiating payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle CMI callback for subscription payments
     */
    public function handleCallback(Request $request)
    {
        $params = $request->except('HASH');
        $receivedHash = $request->input('HASH');
        $calculatedHash = $this->paymentService->generateCallbackHash($params);

        if (!hash_equals($calculatedHash, $receivedHash)) {
            Log::error('Invalid hash in subscription callback', $params);
            return response('FAILURE', 200);
        }

        $oid = $params['oid'] ?? '';
        $procReturnCode = $params['ProcReturnCode'] ?? '';
        $responseStatus = $params['Response'] ?? '';

        if ($procReturnCode !== '00' || $responseStatus !== 'Approved') {
            Log::warning('Subscription payment not approved or failed', $params);
            return response('FAILURE', 200);
        }

        if (!str_starts_with($oid, 'SUB-')) {
            Log::error('Invalid subscription oid format', $params);
            return response('FAILURE', 200);
        }

        $purchaseId = str_replace('SUB-', '', $oid);

        try {
            $purchase = Purchase::with(['plan', 'user', 'invoice'])->findOrFail($purchaseId);

            DB::beginTransaction();

            $purchase->update([
                'status' => 'completed',
                'payment_method' => 'online',
                'start_date' => now(),
                'end_date' => now()->addDays($purchase->plan->duration_days),
            ]);

            DB::table('transactions')->insert([
                'purchase_id' => $purchase->id,
                'transid' => $params['TransId'] ?? '',
                'proc_return_code' => $procReturnCode,
                'response' => $responseStatus,
                'auth_code' => $params['AuthCode'] ?? '',
                'transaction_date' => $params['EXTRA.TRXDATE'] ?? '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $config = Configuration::first();
            if ($purchase->invoice) {
                Mail::to($purchase->user->email)->send(new InvoiceMail($purchase, $purchase->invoice, $config));
            }

            DB::commit();

            Log::info('Subscription payment completed successfully', [
                'purchase_id' => $purchase->id,
                'user_id' => $purchase->user_id,
                'plan' => $purchase->plan->title
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Exception during subscription CMI callback', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            return response('FAILURE', 200);
        }

        return response('ACTION=POSTAUTH', 200);
    }

    /**
     * Handle payment timeout or cancellation
     */
    public function timeout(Request $request)
    {
        $oid = $request->input('oid');
        $purchaseId = str_replace('SUB-', '', $oid);

        Log::warning('Subscription payment timeout', [
            'oid' => $oid,
            'purchase_id' => $purchaseId,
            'request' => $request->all()
        ]);

        try {
            $purchase = Purchase::find($purchaseId);
            if ($purchase && $purchase->status === 'pending') {
                $purchase->update([
                    'status' => 'cancelled',
                    'payment_method' => 'online'
                ]);

                Log::info('Purchase status updated to cancelled due to timeout', [
                    'purchase_id' => $purchaseId,
                    'user_id' => $purchase->user_id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating purchase status on timeout', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchaseId
            ]);
        }

        return response()->json([
            'status' => false,
            'code' => 408,
            'message' => 'Payment timeout or cancelled',
            'data' => [
                'oid' => $oid,
                'purchase_id' => $purchaseId,
                'status' => 'cancelled'
            ]
        ], 408);
    }

    /**
     * Retry payment for failed/cancelled purchases
     */
    public function retryPayment(Request $request)
    {
        try {
            $request->validate([
                'purchase_id' => 'required|integer|exists:purchases,id'
            ]);

            $purchase = Purchase::with(['user', 'plan', 'addOns', 'promoCode'])->findOrFail($request->purchase_id);

            if ($purchase->user_id !== auth()->id()) {
                return response()->json([
                    'status' => false,
                    'code' => 403,
                    'message' => 'Unauthorized access to this purchase'
                ], 403);
            }

            if (!in_array($purchase->status, ['failed', 'cancelled', 'pending'])) {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'message' => 'Purchase cannot be retried. Current status: ' . $purchase->status
                ], 400);
            }

            $purchase->update(['status' => 'pending']);

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
                'message' => 'Payment retry initiated successfully',
                'data' => [
                    'purchase' => $purchase,
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment retry error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'An error occurred while retrying payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle successful payment redirect
     */
    public function success(Request $request)
    {
        $oid = $request->input('oid');
        $purchaseId = str_replace('SUB-', '', $oid);

        try {
            $purchase = Purchase::with(['plan', 'user'])->findOrFail($purchaseId);

            return response()->json([
                'status' => true,
                'code' => 200,
                'message' => 'Payment completed successfully',
                'data' => [
                    'purchase_id' => $purchase->id,
                    'plan_title' => $purchase->plan->title,
                    'status' => $purchase->status,
                    'start_date' => $purchase->start_date,
                    'end_date' => $purchase->end_date
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error in success callback', [
                'error' => $e->getMessage(),
                'oid' => $oid
            ]);

            return response()->json([
                'status' => false,
                'code' => 500,
                'message' => 'Error processing payment success'
            ], 500);
        }
    }

    /**
     * Handle failed payment redirect
     */
    public function failure(Request $request)
    {
        $oid = $request->input('oid');
        $purchaseId = str_replace('SUB-', '', $oid);

        Log::warning('Subscription payment failed', [
            'oid' => $oid,
            'purchase_id' => $purchaseId,
            'request' => $request->all()
        ]);

        try {
            $purchase = Purchase::find($purchaseId);
            if ($purchase && $purchase->status === 'pending') {
                $purchase->update([
                    'status' => 'failed',
                    'payment_method' => 'online'
                ]);

                Log::info('Purchase status updated to failed', [
                    'purchase_id' => $purchaseId,
                    'user_id' => $purchase->user_id
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error updating purchase status on failure', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchaseId
            ]);
        }

        return response()->json([
            'status' => false,
            'code' => 400,
            'message' => 'Payment failed or cancelled',
            'data' => [
                'oid' => $oid,
                'purchase_id' => $purchaseId,
                'status' => 'failed'
            ]
        ], 400);
    }
}
