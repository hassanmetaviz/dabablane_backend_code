#!/bin/bash

# Subscription Payment API Testing Script
# Make sure to replace YOUR_DOMAIN and YOUR_TOKEN with actual values

BASE_URL="http://your-domain.com"
TOKEN="YOUR_TOKEN_HERE"

echo "🧪 Testing Subscription Payment APIs"
echo "====================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to make API calls
make_request() {
    local method=$1
    local url=$2
    local data=$3
    local auth_header=$4
    
    echo -e "\n${YELLOW}Making $method request to: $url${NC}"
    
    if [ -n "$data" ]; then
        response=$(curl -s -X $method "$url" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            $auth_header \
            -d "$data")
    else
        response=$(curl -s -X $method "$url" \
            -H "Accept: application/json" \
            $auth_header)
    fi
    
    echo "Response:"
    echo "$response" | jq '.' 2>/dev/null || echo "$response"
    
    # Check if response contains error
    if echo "$response" | grep -q '"status":false'; then
        echo -e "${RED}❌ Request failed${NC}"
    else
        echo -e "${GREEN}✅ Request successful${NC}"
    fi
}

# Test 1: Get Plans
echo -e "\n${YELLOW}1. Getting Subscription Plans${NC}"
make_request "GET" "$BASE_URL/api/back/v1/vendor/subscriptions/plans" "" "-H \"Authorization: Bearer $TOKEN\""

# Test 2: Get Add-ons
echo -e "\n${YELLOW}2. Getting Add-ons${NC}"
make_request "GET" "$BASE_URL/api/back/v1/vendor/subscriptions/add-ons" "" "-H \"Authorization: Bearer $TOKEN\""

# Test 3: Apply Promo Code
echo -e "\n${YELLOW}3. Applying Promo Code${NC}"
make_request "POST" "$BASE_URL/api/back/v1/vendor/subscriptions/promo-codes/apply" '{"code": "DISCOUNT10"}' "-H \"Authorization: Bearer $TOKEN\""

# Test 4: Create Manual Purchase
echo -e "\n${YELLOW}4. Creating Manual Purchase${NC}"
make_request "POST" "$BASE_URL/api/back/v1/vendor/subscriptions/purchases" '{
    "plan_id": 1,
    "add_ons": [
        {
            "id": 1,
            "quantity": 1
        }
    ],
    "payment_method": "manual"
}' "-H \"Authorization: Bearer $TOKEN\""

# Test 5: Create Online Purchase
echo -e "\n${YELLOW}5. Creating Online Purchase${NC}"
make_request "POST" "$BASE_URL/api/back/v1/vendor/subscriptions/purchases" '{
    "plan_id": 1,
    "add_ons": [
        {
            "id": 1,
            "quantity": 1
        }
    ],
    "payment_method": "online"
}' "-H \"Authorization: Bearer $TOKEN\""

# Test 6: Initiate Payment (replace with actual purchase_id)
echo -e "\n${YELLOW}6. Initiating Payment for Purchase ID 1${NC}"
make_request "POST" "$BASE_URL/api/subscriptions/payment/initiate" '{"purchase_id": 1}' "-H \"Authorization: Bearer $TOKEN\""

# Test 7: Get Purchase History
echo -e "\n${YELLOW}7. Getting Purchase History${NC}"
make_request "GET" "$BASE_URL/api/back/v1/vendor/subscriptions/purchases" "" "-H \"Authorization: Bearer $TOKEN\""

# Test 8: Retry Payment (for failed purchase)
echo -e "\n${YELLOW}8. Retrying Payment for Purchase ID 1${NC}"
make_request "POST" "$BASE_URL/api/subscriptions/payment/retry" '{"purchase_id": 1}' "-H \"Authorization: Bearer $TOKEN\""

# Test 9: Simulate Payment Failure
echo -e "\n${YELLOW}9. Simulating Payment Failure${NC}"
make_request "GET" "$BASE_URL/api/subscriptions/payment/failure?oid=SUB-1" ""

# Test 10: Simulate Payment Timeout
echo -e "\n${YELLOW}10. Simulating Payment Timeout${NC}"
make_request "GET" "$BASE_URL/api/subscriptions/payment/timeout?oid=SUB-1" ""

# Test 11: Simulate CMI Callback (Success)
echo -e "\n${YELLOW}11. Simulating CMI Success Callback${NC}"
make_request "POST" "$BASE_URL/api/subscriptions/payment/callback" '{
    "oid": "SUB-1",
    "ProcReturnCode": "00",
    "Response": "Approved",
    "TransId": "123456789",
    "AuthCode": "AUTH123",
    "EXTRA.TRXDATE": "2024-01-01 12:00:00",
    "HASH": "calculated_hash_here"
}' ""

# Test 12: Simulate CMI Callback (Failure)
echo -e "\n${YELLOW}12. Simulating CMI Failure Callback${NC}"
make_request "POST" "$BASE_URL/api/subscriptions/payment/callback" '{
    "oid": "SUB-1",
    "ProcReturnCode": "01",
    "Response": "Declined",
    "TransId": "123456790",
    "AuthCode": "",
    "EXTRA.TRXDATE": "2024-01-01 12:00:00",
    "HASH": "calculated_hash_here"
}' ""

# Test 13: Get Commission Charts
echo -e "\n${YELLOW}13. Getting Commission Charts${NC}"
make_request "GET" "$BASE_URL/api/back/v1/vendor/subscriptions/commissionChartVendor" "" "-H \"Authorization: Bearer $TOKEN\""

# Test 14: Download Invoice (replace with actual invoice_id)
echo -e "\n${YELLOW}14. Downloading Invoice${NC}"
echo "Note: This will download a file"
curl -X GET "$BASE_URL/api/back/v1/vendor/subscriptions/invoices/1" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Accept: application/json" \
    --output invoice.pdf

echo -e "\n${GREEN}🎉 All tests completed!${NC}"
echo -e "\n${YELLOW}Next Steps:${NC}"
echo "1. Check your Laravel logs for detailed information"
echo "2. Verify database records in the purchases table"
echo "3. Test the actual CMI payment gateway integration"
echo "4. Monitor the callback URLs for real payment processing"



