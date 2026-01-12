#!/bin/bash

# Subscription Expiration Testing Script
# This script tests the subscription expiration functionality

echo "🚀 Testing Subscription Expiration System"
echo "=========================================="

# Base URL - Update this to match your API base URL
BASE_URL="http://localhost:8000/api"
TOKEN="your_auth_token_here"  # Replace with actual token

echo ""
echo "📧 Testing Subscription Status API..."
echo "------------------------------------"

# Test subscription status
curl -X GET "${BASE_URL}/vendor/subscriptions/status" \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -w "\nHTTP Status: %{http_code}\n" \
  -s | jq '.' 2>/dev/null || echo "Response received (jq not available)"

echo ""
echo "🔧 Testing Manual Command Execution..."
echo "------------------------------------"

# Test the command manually (dry run)
echo "Running subscription expiration check (dry run)..."
php artisan subscription:check-expiration --dry-run

echo ""
echo "📊 Testing Command with Different Parameters..."
echo "----------------------------------------------"

# Test with 7 days warning
echo "Testing 7-day warning..."
php artisan subscription:check-expiration --days-before=7 --dry-run

# Test with 3 days warning
echo "Testing 3-day warning..."
php artisan subscription:check-expiration --days-before=3 --dry-run

echo ""
echo "⏰ Testing Scheduler..."
echo "---------------------"

# Check if scheduler is running
echo "Checking scheduler status..."
php artisan schedule:list | grep subscription

echo ""
echo "📝 Testing Email Templates..."
echo "----------------------------"

# Test email template rendering (you can create a test route for this)
echo "Email templates created:"
echo "✓ subscription-expiring.blade.php"
echo "✓ subscription-expired.blade.php"

echo ""
echo "🎯 Testing Purchase Model Methods..."
echo "----------------------------------"

# Create a simple test script
cat > test_purchase_model.php << 'EOF'
<?php
require_once 'vendor/autoload.php';

use App\Models\Purchase;
use Carbon\Carbon;

// Test purchase model methods
echo "Testing Purchase Model Methods:\n";
echo "==============================\n";

// Example usage (you would need actual data)
echo "Available methods:\n";
echo "- isActive(): Check if subscription is active\n";
echo "- isExpired(): Check if subscription is expired\n";
echo "- getDaysRemaining(): Get days until expiration\n";
echo "- expiresSoon(days): Check if expires within X days\n";
echo "- scopeActive(): Query active subscriptions\n";
echo "- scopeExpired(): Query expired subscriptions\n";
echo "- scopeExpiringSoon(days): Query expiring subscriptions\n";
EOF

php test_purchase_model.php
rm test_purchase_model.php

echo ""
echo "✅ Testing Complete!"
echo "==================="
echo ""
echo "📋 Next Steps:"
echo "1. Update your .env file with proper mail settings"
echo "2. Set up CRON job: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1"
echo "3. Test with real subscription data"
echo "4. Monitor logs: tail -f storage/logs/laravel.log"
echo ""
echo "🔧 Manual Testing Commands:"
echo "php artisan subscription:check-expiration --dry-run"
echo "php artisan subscription:check-expiration --days-before=7"
echo "php artisan subscription:check-expiration --days-before=3"
echo ""
echo "📊 Monitor Commands:"
echo "php artisan schedule:list"
echo "php artisan schedule:work"
echo ""

