<?php
/**
 * Performance Test Script for BWG Instagram Feed Admin Pages
 * Tests that admin pages load within 2 seconds
 */

// Measure page load time using microtime
function measure_page_load($url) {
    $start = microtime(true);

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_COOKIE, 'wordpress_logged_in_test=admin'); // Simulated cookie
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

    curl_close($ch);

    return [
        'url' => $url,
        'http_code' => $httpCode,
        'load_time' => round($totalTime, 3),
        'passed' => $totalTime < 2.0
    ];
}

// Test pages
$baseUrl = 'http://localhost:8088';
$adminPages = [
    '/wp-admin/admin.php?page=bwg-igf' => 'BWG IGF Dashboard',
    '/wp-admin/admin.php?page=bwg-igf-feeds' => 'Feeds List',
    '/wp-admin/admin.php?page=bwg-igf-accounts' => 'Accounts',
    '/wp-admin/admin.php?page=bwg-igf-settings' => 'Settings',
];

echo "BWG Instagram Feed Admin Page Performance Test\n";
echo "===============================================\n\n";
echo "Testing admin page load times (target: < 2 seconds)\n\n";

$allPassed = true;

foreach ($adminPages as $path => $name) {
    $result = measure_page_load($baseUrl . $path);
    $status = $result['passed'] ? 'PASS' : 'FAIL';
    $statusSymbol = $result['passed'] ? '✓' : '✗';

    if (!$result['passed']) {
        $allPassed = false;
    }

    echo sprintf(
        "[%s] %s: %.3fs (HTTP %d) - %s\n",
        $statusSymbol,
        str_pad($name, 20),
        $result['load_time'],
        $result['http_code'],
        $status
    );
}

echo "\n===============================================\n";
echo "Result: " . ($allPassed ? "ALL TESTS PASSED" : "SOME TESTS FAILED") . "\n";
