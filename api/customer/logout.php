<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (adjust path as needed)
// Even though we don't query the DB, we need this to manage the session.
include('config.php');

// Response array
$response = ['status' => 'error', 'message' => 'Invalid request.'];

// Start session to access session data
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Check if it's a POST request (Recommended for logout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 2. Unset Customer Specific Session Variables ---
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_login_id']);

    // --- 3. Destroy the session completely ---
    session_destroy();

    // --- 4. Send Success Response ---
    $response['status'] = 'success';
    $response['message'] = 'Logout successful.';

} else {
    $response['message'] = 'Invalid request method. Only POST is accepted.';
}

// --- 5. Send JSON Response ---
echo json_encode($response);

// Optional: Close DB connection if it was opened by config.php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>