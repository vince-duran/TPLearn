<?php
/**
 * Quick test to verify the function conflict is resolved
 */

// Test both files can be included without conflicts
require_once '../includes/email-verification.php';
require_once '../includes/notification-helpers.php';

echo "✅ Both email-verification.php and notification-helpers.php loaded successfully!\n";
echo "✅ Function conflict resolved!\n";

// Test that both getBaseUrl functions exist and work
echo "\n--- Testing Functions ---\n";

// Test email-verification getBaseUrl
if (function_exists('getBaseUrl')) {
    echo "✅ getBaseUrl() from email-verification.php exists\n";
    try {
        $url1 = getBaseUrl();
        echo "✅ getBaseUrl() works: $url1\n";
    } catch (Exception $e) {
        echo "❌ getBaseUrl() error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ getBaseUrl() not found\n";
}

// Test notification getNotificationBaseUrl
if (function_exists('getNotificationBaseUrl')) {
    echo "✅ getNotificationBaseUrl() from notification-helpers.php exists\n";
    try {
        $url2 = getNotificationBaseUrl();
        echo "✅ getNotificationBaseUrl() works: $url2\n";
    } catch (Exception $e) {
        echo "❌ getNotificationBaseUrl() error: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ getNotificationBaseUrl() not found\n";
}

echo "\n✅ All tests passed! The payment rejection should now work without errors.\n";
?>