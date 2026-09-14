<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

$customer_id = get_current_customer_id();

if (!$customer_id) {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.'], 405);
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (!is_array($input_data)) {
    $input_data = $_POST;
}

$message = trim($input_data['message'] ?? $_POST['message'] ?? '');

if (empty($message)) {
    send_api_json_response(['status' => 'error', 'message' => 'The message field cannot be empty.'], 400);
}

$agent_id = null;
$customer_phone = null;
$stmt_info = $conn->prepare("SELECT agent_id, phone FROM customers WHERE id = ?");
$stmt_info->bind_param("i", $customer_id);
if ($stmt_info->execute()) {
    $result_info = $stmt_info->get_result();
    if ($result_info && $result_info->num_rows > 0) {
        $cust_data = $result_info->fetch_assoc();
        $agent_id = $cust_data['agent_id'];
        $customer_phone = $cust_data['phone'];
    }
}
$stmt_info->close();

if (empty($customer_phone)) {
    send_api_json_response(['status' => 'error', 'message' => 'Could not find customer profile data.'], 400);
}

$sql = "INSERT INTO customer_queries (customer_id, agent_id, customer_phone, message) VALUES (?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql);
$stmt_insert->bind_param("iiss", $customer_id, $agent_id, $customer_phone, $message);

if ($stmt_insert->execute()) {
    $stmt_insert->close();
    send_api_json_response([
        'status' => 'success',
        'message' => 'Your query has been submitted successfully.'
    ]);
} else {
    $err = $stmt_insert->error;
    $stmt_insert->close();
    send_api_json_response(['status' => 'error', 'message' => 'Database error: Could not save your query.'], 500);
}
?>