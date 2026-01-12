#!/bin/bash

# Complete Subscription Payment Testing Script
# This script tests the entire subscription payment flow

echo "🚀 Testing Complete Subscription Payment System"
echo "=============================================="

# Base URL - Update this to match your API base URL
BASE_URL="http://localhost:8000/api"
TOKEN="your_auth_token_here"  # Replace with actual token

echo ""
echo "📋 Step 1: Get Available Plans"
echo "------------------------------"

# Get available plans
curl -X GET "${BASE_URL}/back/v1/vendor/subscriptions/plans" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 2: Get Available Add-ons"
echo "-------------------------------"

# Get available add-ons
curl -X GET "${BASE_URL}/back/v1/vendor/subscriptions/add-ons" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 3: Apply Promo Code (Optional)"
echo "-------------------------------------"

# Apply promo code (optional)
curl -X POST "${BASE_URL}/back/v1/vendor/subscriptions/promo-codes/apply" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "promo_code": "WELCOME10"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 4: Create Purchase (Online Payment)"
echo "------------------------------------------"

# Create purchase with online payment
PURCHASE_RESPONSE=$(curl -X POST "${BASE_URL}/back/v1/vendor/subscriptions/purchases" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "plan_id": 1,
    "add_ons": [
      {
        "id": 1,
        "quantity": 2
      }
    ],
    "promo_code": "WELCOME10",
    "payment_method": "online"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s)

echo "$PURCHASE_RESPONSE"

# Extract purchase ID from response (you'll need to do this manually)
echo ""
echo "📋 Step 5: Initiate CMI Payment"
echo "-------------------------------"
echo "Note: Replace PURCHASE_ID with actual purchase ID from Step 4"

# Initiate CMI payment
curl -X POST "${BASE_URL}/subscriptions/payment/initiate" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "purchase_id": 1
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 6: Test CMI Callback (Success)"
echo "-------------------------------------"

# Test CMI callback (success)
curl -X POST "${BASE_URL}/subscriptions/payment/callback" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: application/json" \
  -d 'oid=SUB-1&amount=299.00&ReturnOid=SUB-1&TransId=123456789&AuthCode=123456&ProcReturnCode=00&Response=Approved&mdStatus=1&mdErrorMsg=&clientIp=127.0.0.1&EXTRA.TRXDATE=2024-01-01&HASH=test_hash' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s

echo ""
echo "📋 Step 7: Test Payment Success Redirect"
echo "--------------------------------------"

# Test payment success redirect
curl -X GET "${BASE_URL}/subscriptions/payment/success?oid=SUB-1&amount=299.00&ReturnOid=SUB-1&TransId=123456789&AuthCode=123456&ProcReturnCode=00&Response=Approved&mdStatus=1&mdErrorMsg=&clientIp=127.0.0.1&EXTRA.TRXDATE=2024-01-01&HASH=test_hash" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 8: Test Payment Failure Redirect"
echo "---------------------------------------"

# Test payment failure redirect
curl -X GET "${BASE_URL}/subscriptions/payment/failure?oid=SUB-1&amount=299.00&ReturnOid=SUB-1&TransId=123456789&AuthCode=&ProcReturnCode=05&Response=Declined&mdStatus=0&mdErrorMsg=Insufficient+funds&clientIp=127.0.0.1&EXTRA.TRXDATE=2024-01-01&HASH=test_hash" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 9: Test Payment Timeout Redirect"
echo "---------------------------------------"

# Test payment timeout redirect
curl -X GET "${BASE_URL}/subscriptions/payment/timeout?oid=SUB-1&amount=299.00&ReturnOid=SUB-1&TransId=123456789&AuthCode=&ProcReturnCode=99&Response=Timeout&mdStatus=0&mdErrorMsg=Transaction+timeout&clientIp=127.0.0.1&EXTRA.TRXDATE=2024-01-01&HASH=test_hash" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 10: Test Payment Retry"
echo "-----------------------------"

# Test payment retry
curl -X POST "${BASE_URL}/subscriptions/payment/retry" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "purchase_id": 1
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 11: Check Subscription Status"
echo "------------------------------------"

# Check subscription status
curl -X GET "${BASE_URL}/back/v1/vendor/subscriptions/status" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 12: Get Purchase History"
echo "-------------------------------"

# Get purchase history
curl -X GET "${BASE_URL}/back/v1/vendor/subscriptions/purchases" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "📋 Step 13: Test Manual Payment"
echo "-----------------------------"

# Test manual payment
curl -X POST "${BASE_URL}/back/v1/vendor/subscriptions/purchases" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "plan_id": 1,
    "add_ons": [
      {
        "id": 1,
        "quantity": 1
      }
    ],
    "payment_method": "manual"
  }' \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "✅ Testing Complete!"
echo "==================="
echo ""
echo "📋 Summary of Tested Endpoints:"
echo "1. GET /back/v1/vendor/subscriptions/plans"
echo "2. GET /back/v1/vendor/subscriptions/add-ons"
echo "3. POST /back/v1/vendor/subscriptions/promo-codes/apply"
echo "4. POST /back/v1/vendor/subscriptions/purchases"
echo "5. POST /subscriptions/payment/initiate"
echo "6. POST /subscriptions/payment/callback"
echo "7. GET /subscriptions/payment/success"
echo "8. GET /subscriptions/payment/failure"
echo "9. GET /subscriptions/payment/timeout"
echo "10. POST /subscriptions/payment/retry"
echo "11. GET /back/v1/vendor/subscriptions/status"
echo "12. GET /back/v1/vendor/subscriptions/purchases"
echo ""
echo "🔧 Next Steps:"
echo "1. Update TOKEN variable with actual authentication token"
echo "2. Update BASE_URL if different from localhost:8000"
echo "3. Replace PURCHASE_ID with actual purchase ID from responses"
echo "4. Test with real CMI payment gateway"
echo "5. Monitor logs for any errors"
echo ""
echo "📊 Monitor Commands:"
echo "tail -f storage/logs/laravel.log"
echo "php artisan queue:work (if using queues)"
echo ""

