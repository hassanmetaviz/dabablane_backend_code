<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait WebhookNotifiable
{
    protected function sendWebhookNotification($data)
    {
        try {
            $response = Http::withBasicAuth('DabaBlane', 'EGXjI@hO_EADtXD4wM')
                ->post('https://n8n.dabablane.com/webhook/65b39260-a934-437f-b5a3-e278544ff110', $data);
                Log::error('Webhook notification success', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'data' => $data
                ]);
            if (!$response->successful()) {
                Log::error('Webhook notification failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'data' => $data
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send webhook notification: ' . $e->getMessage());
        }
    }

    protected function prepareWebhookData($model, $type)
    {
        $blane = $model->blane;
        $customer = $model->customer;

        $data = [
            'id' => $type === 'reservation' ? $model->NUM_RES : $model->NUM_ORD,
            'created_at' => $model->created_at,
            'date' => $type === 'reservation' ? $model->date : null,
            'end_date' => $type === 'reservation' ? $model->end_date : null,
            'customer_name' => $customer->name,
            'customer_tel' => $customer->phone,
            'customer_email' => $customer->email,
            'commerce_name' => $blane->commerce_name,
            'commerce_phone' => $blane->commerce_phone,
            'quantity' => $model->quantity,
            'total_price' => $model->total_price,
            'comments' => $model->comments,
            'blane_name' => $blane->name,
            'payment_method' => $model->payment_method,
            'blane_city' => $blane->city,
            'delivery_address' => $type === 'order' ? $model->delivery_address : null,
            'type_time' => $blane->type_time,
            'type' => $type,
            'is_digital' => $type === 'order' ? $blane->is_digital : null
        ];

        return $data;
    }
} 