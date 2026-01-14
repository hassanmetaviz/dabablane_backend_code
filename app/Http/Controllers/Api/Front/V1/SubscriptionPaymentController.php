<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Front\V1\InitiateSubscriptionPaymentRequest;
use App\Http\Requests\Api\Front\V1\RetrySubscriptionPaymentRequest;
use App\Models\Purchase;
use App\Models\Configuration;
use App\Services\CmiService;
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentController extends BaseController
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
    public function initiatePayment(InitiateSubscriptionPaymentRequest $request)
    {
        try {
            $data = $request->validated();

            $purchase = Purchase::with(['user', 'plan', 'addOns', 'promoCode'])->findOrFail($data['purchase_id']);

            if ($purchase->user_id !== auth()->id()) {
                return $this->error('Unauthorized access to this purchase', [], 403);
            }

            if ($purchase->status !== 'pending') {
                return $this->error('Purchase is not in pending status', [], 400);
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

            return $this->success(
                [
                    'purchase' => $purchase,
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params,
                    ],
                ],
                'Payment initiated successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Subscription payment initiation error', [
                'request' => $request->all()
            ]);

            return $this->error('An error occurred while initiating payment.', [], 500);
        }
    }

    /**
     * Handle CMI callback for subscription payments
     */
    public function handleCallback(\Illuminate\Http\Request $request)
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
                'params' => $params
            ]);
            return response('FAILURE', 200);
        }

        return response('ACTION=POSTAUTH', 200);
    }

    /**
     * Handle payment timeout or cancellation
     */
    public function paymentTimeout(\Illuminate\Http\Request $request)
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
                'purchase_id' => $purchaseId
            ]);
        }

        // Keep backward-compatible response payload (includes data fields).
        return $this->error(
            'Payment timeout or cancelled',
            [],
            408,
            [
                'oid' => $oid,
                'purchase_id' => $purchaseId,
                'status' => 'cancelled',
            ]
        );
    }

    /**
     * Retry payment for failed/cancelled purchases
     */
    public function retryPayment(RetrySubscriptionPaymentRequest $request)
    {
        try {
            $data = $request->validated();

            $purchase = Purchase::with(['user', 'plan', 'addOns', 'promoCode'])->findOrFail($data['purchase_id']);

            if ($purchase->user_id !== auth()->id()) {
                return $this->error('Unauthorized access to this purchase', [], 403);
            }

            if (!in_array($purchase->status, ['failed', 'cancelled', 'pending'])) {
                return $this->error('Purchase cannot be retried.', [
                    'status' => ['Purchase cannot be retried. Current status: ' . $purchase->status],
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

            return $this->success(
                [
                    'purchase' => $purchase,
                    'payment_info' => [
                        'payment_url' => $this->gatewayUrl,
                        'method' => 'post',
                        'inputs' => $params,
                    ],
                ],
                'Payment retry initiated successfully',
                200
            );

        } catch (\Exception $e) {
            Log::error('Payment retry error', [
                'request' => $request->all()
            ]);

            return $this->error('An error occurred while retrying payment.', [], 500);
        }
    }

    /**
     * Handle successful payment redirect
     */
    public function paymentSuccess(\Illuminate\Http\Request $request)
    {
        $oid = $request->input('oid');
        $purchaseId = str_replace('SUB-', '', $oid);

        try {
            $purchase = Purchase::with(['plan', 'user'])->findOrFail($purchaseId);

            return $this->success(
                [
                    'purchase_id' => $purchase->id,
                    'plan_title' => $purchase->plan->title,
                    'status' => $purchase->status,
                    'start_date' => $purchase->start_date,
                    'end_date' => $purchase->end_date,
                ],
                'Payment completed successfully',
                200
            );
        } catch (\Exception $e) {
            Log::error('Error in success callback', [
                'oid' => $oid
            ]);

            return $this->error('Error processing payment success', [], 500);
        }
    }

    /**
     * Handle failed payment redirect
     */
    public function paymentFailure(\Illuminate\Http\Request $request)
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
                'purchase_id' => $purchaseId
            ]);
        }

        // Keep backward-compatible response payload (includes data fields).
        return $this->error(
            'Payment failed or cancelled',
            [],
            400,
            [
                'oid' => $oid,
                'purchase_id' => $purchaseId,
                'status' => 'failed',
            ]
        );
    }
}
