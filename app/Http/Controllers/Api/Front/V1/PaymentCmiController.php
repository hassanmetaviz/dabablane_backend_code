<?php

namespace App\Http\Controllers\Api\Front\V1;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmation;
use App\Mail\ReservationConfirmation;
use App\Models\Order;
use App\Services\CmiService;
use App\Services\VendorPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use App\Models\Transaction;

class PaymentCmiController extends Controller
{
    private $paymentService;
    private $vendorPaymentService;
    private $gatewayUrl;

    public function __construct(CmiService $paymentService, VendorPaymentService $vendorPaymentService)
    {
        $this->paymentService = $paymentService;
        $this->vendorPaymentService = $vendorPaymentService;
        $this->gatewayUrl = config('cmi.base_uri');
    }

    public function initiatePayment(Request $request)
    {
        try {
            $request->validate(['number' => 'required|string']);

            $number = $request->number;
            $isOrder = str_starts_with($number, 'ORDER');
            $isReservation = str_starts_with($number, 'RES');

            if ($isOrder) {
                $order = Order::where('NUM_ORD', $number)
                    ->where('status', '!=', 'canceled')
                    ->where('status', '!=', 'paid')
                    ->first();

                if (!$order) {
                    return response()->json([
                        'status' => false,
                        'code' => 404,
                        'message' => "Order with reference '$number' not found"
                    ], 404);
                }

                // Determine the amount based on the payment method
                $amount_or = ($order->payment_method === 'partiel')
                    ? $order->partiel_price
                    : $order->total_price;
                $user = $order->customer;
                $orderData = [
                    'amount' => $amount_or,
                    'oid' => $order->NUM_ORD,
                    'email' => $user->email ?? '',
                    'name' => $user->name ?? '',
                    'tel' => $user->phone ?? '',
                    'billToStreet1' => $user->address ?? '',
                    'billToCity' => $user->city ?? '',
                    'billToStateProv' => $user->state ?? '',
                    'billToPostalCode' => $user->zip ?? '',
                    'billToCountry' => $user->country ?? '',
                ];
            } elseif ($isReservation) {
                $reservation = Reservation::where('NUM_RES', $number)
                    ->where('status', '!=', 'canceled')
                    ->where('status', '!=', 'paid')
                    ->first();

                if (!$reservation) {
                    return response()->json([
                        'status' => false,
                        'code' => 404,
                        'message' => "Reservation with reference '$number' not found"
                    ], 404);
                }

                // Determine the amount based on the payment method
                $amount_res = ($reservation->payment_method === 'partiel')
                    ? $reservation->partiel_price
                    : $reservation->total_price;
                $user = $reservation->customer;
                $orderData = [
                    'amount' => $amount_res,
                    'oid' => $reservation->NUM_RES,
                    'email' => $user->email ?? '',
                    'name' => $user->name ?? '',
                    'tel' => $user->phone ?? '',
                    'billToStreet1' => $user->address ?? '',
                    'billToCity' => $user->city ?? '',
                    'billToStateProv' => $user->state ?? '',
                    'billToPostalCode' => $user->zip ?? '',
                    'billToCountry' => $user->country ?? '',
                ];
            } else {
                return response()->json([
                    'status' => false,
                    'code' => 400,
                    'message' => 'Invalid reference format. Must start with ORDER or RES'
                ], 400);
            }

            $params = $this->paymentService->preparePaymentParams($orderData);

            return response()->json([
                'status' => true,
                'payment_url' => $this->gatewayUrl,
                'method' => 'post',
                'inputs' => $params,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initiation error', [
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

    public function handleCallback(Request $request)
    {
        $params = $request->except('HASH');
        $receivedHash = $request->input('HASH');
        $calculatedHash = $this->paymentService->generateCallbackHash($params);

        if (!hash_equals($calculatedHash, $receivedHash)) {
            Log::error('Invalid hash in callback', $params);
            return response('FAILURE', 200); // reject, don't capture
        }

        $oid = $params['oid'] ?? '';
        $procReturnCode = $params['ProcReturnCode'] ?? '';
        $responseStatus = $params['Response'] ?? '';

        if ($procReturnCode !== '00' || $responseStatus !== 'Approved') {
            Log::warning('Payment not approved or failed', $params);
            return response('FAILURE', 200);
        }

        $isOrder = str_starts_with($oid, 'ORDER');
        $isReservation = str_starts_with($oid, 'RES');

        try {
            if ($isOrder) {
                $order = Order::where('NUM_ORD', $oid)->firstOrFail();
                $order->update(['status' => 'paid']);

                Transaction::create([
                    'order_id' => $order->id,
                    'transid' => $params['TransId'] ?? '',
                    'proc_return_code' => $procReturnCode,
                    'response' => $responseStatus,
                    'auth_code' => $params['AuthCode'] ?? '',
                    'transaction_date' => $params['EXTRA.TRXDATE'] ?? '',
                ]);

                // Create vendor payment record if online payment
                if ($order->payment_method !== 'cash') {
                    try {
                        $this->vendorPaymentService->createPaymentFromOrder($order);
                    } catch (\Exception $e) {
                        Log::error('Failed to create vendor payment from order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Mail::to($params['email'] ?? $order->customer->email)
                    ->cc(config('mail.contact_address'))
                    ->send(new OrderConfirmation($order));
            } elseif ($isReservation) {
                $reservation = Reservation::where('NUM_RES', $oid)->firstOrFail();
                $reservation->update(['status' => 'paid']);

                Transaction::create([
                    'reservation_id' => $reservation->id,
                    'transid' => $params['TransId'] ?? '',
                    'proc_return_code' => $procReturnCode,
                    'response' => $responseStatus,
                    'auth_code' => $params['AuthCode'] ?? '',
                    'transaction_date' => $params['EXTRA.TRXDATE'] ?? '',
                ]);

                // Create vendor payment record if online payment
                if ($reservation->payment_method !== 'cash') {
                    try {
                        $this->vendorPaymentService->createPaymentFromReservation($reservation);
                    } catch (\Exception $e) {
                        Log::error('Failed to create vendor payment from reservation', [
                            'reservation_id' => $reservation->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Mail::to($params['email'] ?? $reservation->customer->email)
                    ->cc(config('mail.contact_address'))
                    ->send(new ReservationConfirmation($reservation));
            } else {
                Log::error('Invalid oid format: not order or reservation', $params);
                return response('FAILURE', 200);
            }
        } catch (\Exception $e) {
            Log::error('Exception during CMI callback', ['error' => $e->getMessage(), 'params' => $params]);
            return response('FAILURE', 200);
        }

        // Return ACTION=POSTAUTH to confirm payment capture
        return response('ACTION=POSTAUTH', 200);
    }

    public function success(Request $request)
    {
        return redirect()->away(route('payment.result', ['oid' => $request->input('oid'), 'status' => 'success']));
    }

    public function failure(Request $request)
    {
        return redirect()->away(route('payment.result', ['oid' => $request->input('oid'), 'status' => 'failure']));
    }
}
