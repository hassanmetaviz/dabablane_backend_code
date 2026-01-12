#!/bin/bash

# Blane API Endpoints Test Script
# Usage: ./test_blane_endpoints.sh

# Configuration - UPDATE THESE VALUES
BASE_URL="http://localhost:8000/api"
TOKEN="YOUR_TOKEN_HERE"
BLANE_ID=1
VENDOR_ID=1
COMMERCE_NAME="YourVendorName"

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Blane API Endpoints Test ===${NC}\n"
echo "Base URL: ${BASE_URL}"
echo "Token: ${TOKEN:0:20}..."
echo "Blane ID: ${BLANE_ID}"
echo ""

# Function to test endpoint
test_endpoint() {
    local name=$1
    local method=$2
    local url=$3
    local data=$4
    local auth=$5
    
    echo -e "${YELLOW}Testing: ${name}${NC}"
    echo "Method: ${method}"
    echo "URL: ${url}"
    
    if [ "$method" = "GET" ]; then
        if [ "$auth" = "true" ]; then
            response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "${url}" \
                -H "Accept: application/json" \
                -H "Authorization: Bearer ${TOKEN}")
        else
            response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X GET "${url}" \
                -H "Accept: application/json")
        fi
    elif [ "$method" = "POST" ]; then
        if [ "$auth" = "true" ]; then
            response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "${url}" \
                -H "Accept: application/json" \
                -H "Authorization: Bearer ${TOKEN}" \
                -H "Content-Type: application/json" \
                -d "${data}")
        else
            response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X POST "${url}" \
                -H "Accept: application/json" \
                -H "Content-Type: application/json" \
                -d "${data}")
        fi
    elif [ "$method" = "PUT" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PUT "${url}" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${TOKEN}" \
            -H "Content-Type: application/json" \
            -d "${data}")
    elif [ "$method" = "PATCH" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X PATCH "${url}" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${TOKEN}" \
            -H "Content-Type: application/json" \
            -d "${data}")
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -w "\nHTTP_CODE:%{http_code}" -X DELETE "${url}" \
            -H "Accept: application/json" \
            -H "Authorization: Bearer ${TOKEN}")
    fi
    
    http_code=$(echo "$response" | grep -o "HTTP_CODE:[0-9]*" | cut -d: -f2)
    body=$(echo "$response" | sed 's/HTTP_CODE:[0-9]*$//')
    
    if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
        echo -e "${GREEN}✓ Success (HTTP ${http_code})${NC}"
        echo "Response: $(echo $body | head -c 200)..."
    else
        echo -e "${RED}✗ Failed (HTTP ${http_code})${NC}"
        echo "Response: $body"
    fi
    echo ""
}

# ============================================
# CRUD Operations (BlanController)
# ============================================
echo -e "${GREEN}=== 1. CRUD Operations ===${NC}\n"

test_endpoint "List Blanes" "GET" "${BASE_URL}/back/v1/blanes?paginationSize=5" "" "true"
test_endpoint "Get Single Blane" "GET" "${BASE_URL}/back/v1/blanes/${BLANE_ID}?include=blaneImages" "" "true"
test_endpoint "Create Blane" "POST" "${BASE_URL}/back/v1/blanes" '{"name":"Test Blane","description":"Test","price_current":100,"status":"active","type":"order","city":"Casablanca"}' "true"
test_endpoint "Update Blane Status" "PATCH" "${BASE_URL}/back/v1/blanes/${BLANE_ID}/update-status" '{"status":"active"}' "true"

# ============================================
# Query Operations (BlaneQueryController)
# ============================================
echo -e "${GREEN}=== 2. Query Operations ===${NC}\n"

test_endpoint "Search Blanes" "GET" "${BASE_URL}/back/v1/blanes/search?query=test&paginationSize=5" "" "true"
test_endpoint "Get Featured Blanes (Public)" "GET" "${BASE_URL}/back/v1/getFeaturedBlanes?paginationSize=5" "" "false"
test_endpoint "Get Blanes by Start Date (Public)" "GET" "${BASE_URL}/back/v1/getBlanesByStartDate?paginationSize=5" "" "false"
test_endpoint "Get Blanes by Category (Public)" "GET" "${BASE_URL}/back/v1/getBlanesByCategory?paginationSize=5" "" "false"
test_endpoint "Get Blanes by Vendor (Auth)" "GET" "${BASE_URL}/back/v1/getBlanesByVendor?commerce_name=${COMMERCE_NAME}&paginationSize=5" "" "true"
test_endpoint "Get Blanes by Vendor (Public)" "GET" "${BASE_URL}/back/v1/vendors/getBlanesByVendor?id=${VENDOR_ID}&paginationSize=5" "" "false"
test_endpoint "Get All Filtered Blanes" "GET" "${BASE_URL}/back/v1/getAllFilterBlane?status=active&paginationSize=5" "" "true"
test_endpoint "Get Vendor by Blane" "GET" "${BASE_URL}/back/v1/getVendorByBlane?blane_id=${BLANE_ID}" "" "true"

# ============================================
# Share Operations (BlaneShareController)
# ============================================
echo -e "${GREEN}=== 3. Share Operations ===${NC}\n"

test_endpoint "Generate Share Link" "POST" "${BASE_URL}/back/v1/blanes/${BLANE_ID}/share" "" "true"
test_endpoint "Update Visibility to Link" "PATCH" "${BASE_URL}/back/v1/blanes/${BLANE_ID}/visibility" '{"visibility":"link"}' "true"
test_endpoint "Update Visibility to Public" "PATCH" "${BASE_URL}/back/v1/blanes/${BLANE_ID}/visibility" '{"visibility":"public"}' "true"
test_endpoint "Revoke Share Link" "DELETE" "${BASE_URL}/back/v1/blanes/${BLANE_ID}/share" "" "true"

# ============================================
# Import Operations (BlaneImportController)
# ============================================
echo -e "${GREEN}=== 4. Import Operations ===${NC}\n"

test_endpoint "Import Blanes" "POST" "${BASE_URL}/back/v1/blanes/import" '{"blanes":[{"name":"Imported Blane","price_current":100,"status":"active","type":"order"}]}' "true"

echo -e "${GREEN}=== Testing Complete ===${NC}"







