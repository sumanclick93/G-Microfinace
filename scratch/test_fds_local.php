<?php
// Mock session for Super Admin
session_start();
$_SESSION['admin_id'] = 1;

echo "=== TESTING SUPER ADMIN ALL-FDS.PHP LOGIC ===\n";
ob_start();
include(__DIR__ . '/../Super/all-fds.php');
$super_html = ob_get_clean();

// Check if FD Number and Customer Name appear in output
if (strpos($super_html, 'FD-20260914-5001') !== false && strpos($super_html, 'Anita Sharma') !== false) {
    echo "[SUCCESS] Super Admin FD Table contains FD-20260914-5001 and Anita Sharma!\n";
} else {
    echo "[FAILURE] Super Admin FD Table DID NOT render the record!\n";
    echo "Super HTML contains FD?: " . (strpos($super_html, 'FD-') !== false ? 'YES' : 'NO') . "\n";
    echo "Super HTML contains Anita?: " . (strpos($super_html, 'Anita') !== false ? 'YES' : 'NO') . "\n";
    if (preg_match('/<table.*?>.*?<\/table>/s', $super_html, $matches)) {
        echo "Super Table Content:\n" . substr($matches[0], 0, 1500) . "\n";
    }
}

// Mock session for Agent
unset($_SESSION['admin_id']);
$_SESSION['agent_id'] = 1;

echo "\n=== TESTING AGENT ALL-FDS.PHP LOGIC ===\n";
ob_start();
include(__DIR__ . '/../Agents/all-fds.php');
$agent_html = ob_get_clean();

if (strpos($agent_html, 'FD-20260914-5001') !== false && strpos($agent_html, 'Anita Sharma') !== false) {
    echo "[SUCCESS] Agent FD Table contains FD-20260914-5001 and Anita Sharma!\n";
} else {
    echo "[FAILURE] Agent FD Table DID NOT render the record!\n";
    echo "Agent HTML Length: " . strlen($agent_html) . "\n";
    echo "Agent HTML Excerpt: " . substr($agent_html, 0, 500) . "\n";
}
?>
