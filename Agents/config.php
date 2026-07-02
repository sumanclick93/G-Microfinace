<?php
// --- START: Add these lines for debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- END: Debugging lines ---
// Database Configuration
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'microfinance_fund'); // Your database username
}
if (!defined('DB_PASS')) {
    define('DB_PASS', 'ys!bnLg0j.T[');     // Your database password
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'microfinance_fund'); // Your database name
}

// Create a connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start the session for login management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>