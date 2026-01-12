<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class SubscriptionPaymentTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone' => '1234567890',
            'company_name' => 'Test Company'
        ]);

        // Create a test plan
        $this->plan = Plan::create([
            'title' => 'Test Plan',
            'slug' => 'test-plan',
            'price_ht' => 100.00,
            'duration_days' => 30,
            'is_active' => true,
            'display_order' => 1
        ]);

        // Create default configuration
        Configuration::create([
            'billing_email' => 'billing@test.com',
            'contact_email' => 'contact@test.com',
            'contact_phone' => '+1234567890',
            'invoice_prefix' => 'TEST-INV-'
        ]);
    }

    /** @test */
    public function vendor_can_create_subscription_purchase()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/back/v1/vendor/subscriptions/purchases', [
                'plan_id' => $this->plan->id,
                'payment_method' => 'online'
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'code',
            'message',
            'data' => [
                'purchase' => [
                    'id',
                    'plan_id',
                    'user_id',
                    'status',
                    'payment_method'
                ],
                'payment_info' => [
                    'payment_url',
                    'method',
                    'inputs'
                ]
            ]
        ]);

        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'pending',
            'payment_method' => 'online'
        ]);
    }

    /** @test */
    public function vendor_can_get_subscription_plans()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/back/v1/vendor/subscriptions/plans');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'code',
            'data' => [
                'plans' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'price_ht',
                        'duration_days',
                        'is_active'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function vendor_can_get_purchase_history()
    {
        // Create a test purchase
        Purchase::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_price_ht' => $this->plan->price_ht,
            'subtotal_ht' => $this->plan->price_ht,
            'vat_amount' => $this->plan->price_ht * 0.20,
            'total_ttc' => $this->plan->price_ht * 1.20,
            'status' => 'completed',
            'payment_method' => 'manual',
            'start_date' => now(),
            'end_date' => now()->addDays($this->plan->duration_days)
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/back/v1/vendor/subscriptions/purchases');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'code',
            'data' => [
                '*' => [
                    'id',
                    'plan_id',
                    'user_id',
                    'status',
                    'payment_method'
                ]
            ]
        ]);
    }

    /** @test */
    public function subscription_payment_initiation_requires_authentication()
    {
        $purchase = Purchase::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_price_ht' => $this->plan->price_ht,
            'subtotal_ht' => $this->plan->price_ht,
            'vat_amount' => $this->plan->price_ht * 0.20,
            'total_ttc' => $this->plan->price_ht * 1.20,
            'status' => 'pending',
            'payment_method' => 'online'
        ]);

        $response = $this->postJson('/api/subscriptions/payment/initiate', [
            'purchase_id' => $purchase->id
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function subscription_payment_initiation_works_for_authenticated_user()
    {
        $purchase = Purchase::create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'plan_price_ht' => $this->plan->price_ht,
            'subtotal_ht' => $this->plan->price_ht,
            'vat_amount' => $this->plan->price_ht * 0.20,
            'total_ttc' => $this->plan->price_ht * 1.20,
            'status' => 'pending',
            'payment_method' => 'online'
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/subscriptions/payment/initiate', [
                'purchase_id' => $purchase->id
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'code',
            'message',
            'data' => [
                'purchase',
                'payment_info' => [
                    'payment_url',
                    'method',
                    'inputs'
                ]
            ]
        ]);
    }
}



