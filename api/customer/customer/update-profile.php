<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

$customer_id = get_current_customer_id();

if (!$customer_id) {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401);
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only PUT or POST are accepted.'], 405);
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (!is_array($input_data)) {
    $input_data = $_POST;
}

$fields_to_update = [];
$params = [];
$types = '';

if (isset($input_data['full_name']) && !empty(trim($input_data['full_name']))) {
    $fields_to_update[] = "full_name = ?";
    $params[] = trim($input_data['full_name']);
    $types .= 's';
}

if (isset($input_data['email'])) {
    $email = trim($input_data['email']);
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_api_json_response(['status' => 'error', 'message' => 'Invalid email format provided.'], 400);
    }
    $fields_to_update[] = "email = ?";
    $params[] = $email ?: null;
    $types .= 's';
}

if (isset($input_data['address'])) {
    $fields_to_update[] = "address = ?";
    $params[] = trim($input_data['address']);
    $types .= 's';
}

if (isset($input_data['new_password']) && !empty($input_data['new_password'])) {
    $fields_to_update[] = "password = ?";
    $params[] = password_hash($input_data['new_password'], PASSWORD_DEFAULT);
    $types .= 's';
}

if (empty($fields_to_update)) {
    send_api_json_response(['status' => 'error', 'message' => 'No valid fields provided for update.'], 400);
}

$sql = "UPDATE customers SET " . implode(', ', $fields_to_update) . " WHERE id = ?";
$params[] = $customer_id;
$types .= 'i';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    send_api_json_response(['status' => 'error', 'message' => 'Database error preparing statement: ' . $conn->error], 500);
}

$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $stmt->close();
    if (session_status() === PHP_SESSION_ACTIVE && isset($input_data['full_name'])) {
        $_SESSION['customer_name'] = trim($input_data['full_name']);
    }
    send_api_json_response([
        'status' => 'success',
        'message' => 'Profile updated successfully.'
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    send_api_json_response(['status' => 'error', 'message' => 'Database error during update: ' . $err], 500);
}
?>