#!/bin/bash

# Core API Engine Manual Test Script
# Tests the unified CoreDataController with all features

echo "🚀 Core API Engine Test Suite"
echo "================================"
echo ""

# Configuration
BASE_URL="http://127.0.0.1:8001"
API_KEY="your_api_key_here"  # Replace with actual API key from database

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counter
PASSED=0
FAILED=0

# Helper function to test endpoint
test_endpoint() {
    local name="$1"
    local method="$2"
    local endpoint="$3"
    local data="$4"
    local expected_status="$5"
    
    echo -n "Testing: $name... "
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer $API_KEY" "$BASE_URL$endpoint")
    elif [ "$method" = "POST" ]; then
        response=$(curl -s -w "\n%{http_code}" -X POST -H "Authorization: Bearer $API_KEY" -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint")
    elif [ "$method" = "PUT" ]; then
        response=$(curl -s -w "\n%{http_code}" -X PUT -H "Authorization: Bearer $API_KEY" -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint")
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -w "\n%{http_code}" -X DELETE -H "Authorization: Bearer $API_KEY" "$BASE_URL$endpoint")
    fi
    
    status_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')
    
    if [ "$status_code" = "$expected_status" ]; then
        echo -e "${GREEN}✓ PASSED${NC} (Status: $status_code)"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAILED${NC} (Expected: $expected_status, Got: $status_code)"
        echo "Response: $body"
        ((FAILED++))
    fi
}

echo "📋 Prerequisites Check"
echo "----------------------"
echo "1. Make sure your Laravel server is running on $BASE_URL"
echo "2. Update API_KEY variable in this script with a valid secret key (sk_...)"
echo "3. Create a dynamic model called 'products' with fields: name (string), price (float)"
echo ""
read -p "Press Enter when ready to continue..."
echo ""

echo "🔐 Test 1: Authentication & Authorization"
echo "-------------------------------------------"

# Test 1.1: Missing API Key
echo -n "1.1 Missing API Key... "
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api/v1/data/products")
status_code=$(echo "$response" | tail -n1)
if [ "$status_code" = "401" ]; then
    echo -e "${GREEN}✓ PASSED${NC}"
    ((PASSED++))
else
    echo -e "${RED}✗ FAILED${NC} (Expected: 401, Got: $status_code)"
    ((FAILED++))
fi

# Test 1.2: Invalid API Key
echo -n "1.2 Invalid API Key... "
response=$(curl -s -w "\n%{http_code}" -H "Authorization: Bearer invalid_key_12345" "$BASE_URL/api/v1/data/products")
status_code=$(echo "$response" | tail -n1)
if [ "$status_code" = "401" ]; then
    echo -e "${GREEN}✓ PASSED${NC}"
    ((PASSED++))
else
    echo -e "${RED}✗ FAILED${NC} (Expected: 401, Got: $status_code)"
    ((FAILED++))
fi

echo ""
echo "📊 Test 2: CRUD Operations (v1 API)"
echo "------------------------------------"

# Test 2.1: List records
test_endpoint "2.1 List Records (GET)" "GET" "/api/v1/data/products" "" "200"

# Test 2.2: Create record
test_endpoint "2.2 Create Record (POST)" "POST" "/api/v1/data/products" '{"name":"Test Product","price":29.99}' "201"

# Test 2.3: Get schema
test_endpoint "2.3 Get Schema" "GET" "/api/v1/data/products/schema" "" "200"

echo ""
echo "🔄 Test 3: Backward Compatibility"
echo "----------------------------------"

# Test 3.1: Legacy endpoint still works
test_endpoint "3.1 Legacy Endpoint (GET)" "GET" "/api/data/products" "" "200"

echo ""
echo "🚦 Test 4: Rate Limiting"
echo "------------------------"

echo -n "4.1 Rate Limit Headers... "
response=$(curl -s -I -H "Authorization: Bearer $API_KEY" "$BASE_URL/api/v1/data/products")
if echo "$response" | grep -q "X-RateLimit-Limit"; then
    echo -e "${GREEN}✓ PASSED${NC} (Headers present)"
    ((PASSED++))
else
    echo -e "${RED}✗ FAILED${NC} (Headers missing)"
    ((FAILED++))
fi

echo ""
echo "📈 Test Summary"
echo "==============="
echo -e "Total Tests: $((PASSED + FAILED))"
echo -e "${GREEN}Passed: $PASSED${NC}"
echo -e "${RED}Failed: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed!${NC}"
    exit 0
else
    echo -e "${YELLOW}⚠️  Some tests failed. Check the output above.${NC}"
    exit 1
fi
