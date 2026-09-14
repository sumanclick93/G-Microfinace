<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

$customer_id = get_current_customer_id();

if (!$customer_id) {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only GET is accepted.'], 405);
}

$sql = "SELECT first_name, last_name, email, phone, payment_qr, payment_upi FROM admins ORDER BY id ASC LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $admin_data = $result->fetch_assoc();
    send_api_json_response([
        'status' => 'success',
        'message' => 'Admin details fetched successfully.',
        'data' => [
            'name' => trim(($admin_data['first_name'] ?? '') . ' ' . ($admin_data['last_name'] ?? '')),
            'email' => $admin_data['email'] ?? '',
            'phone' => $admin_data['phone'] ?? '',
            'payment_qr' => $admin_data['payment_qr'] ?? '',
            'payment_upi' => $admin_data['payment_upi'] ?? ''
        ]
    ]);
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Admin details could not be found in the database.'], 404);
}
?>