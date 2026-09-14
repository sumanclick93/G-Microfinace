<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.'], 405);
}

$input_data = json_decode(file_get_contents('php://input'), true);
if (!is_array($input_data)) {
    $input_data = $_POST;
}

$full_name = trim($input_data['full_name'] ?? '');
$phone = trim($input_data['phone'] ?? '');
$password = trim($input_data['password'] ?? '');
$address = trim($input_data['address'] ?? '');
$agent_id = filter_var($input_data['agent_id'] ?? 0, FILTER_VALIDATE_INT);

if (empty($full_name) || empty($phone) || empty($password) || $agent_id === false || $agent_id <= 0) {
    send_api_json_response(['status' => 'error', 'message' => 'Missing required fields: full_name, phone, password, agent_id.'], 400);
}

// Check phone
$stmt_check_phone = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
$stmt_check_phone->bind_param("s", $phone);
$stmt_check_phone->execute();
$stmt_check_phone->store_result();

if ($stmt_check_phone->num_rows > 0) {
    $stmt_check_phone->close();
    send_api_json_response(['status' => 'error', 'message' => 'Phone number is already registered.'], 400);
}
$stmt_check_phone->close();

// Check agent
$stmt_check_agent = $conn->prepare("SELECT id FROM agents WHERE id = ?");
$stmt_check_agent->bind_param("i", $agent_id);
$stmt_check_agent->execute();
$stmt_check_agent->store_result();

if ($stmt_check_agent->num_rows === 0) {
    $stmt_check_agent->close();
    send_api_json_response(['status' => 'error', 'message' => 'Invalid agent selected.'], 400);
}
$stmt_check_agent->close();

$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$customer_id_string = 'CUST' . time() . rand(10, 99);

$sql = "INSERT INTO customers (agent_id, customer_id_string, password, full_name, phone, address) VALUES (?, ?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql);
$stmt_insert->bind_param("isssss", $agent_id, $customer_id_string, $hashed_password, $full_name, $phone, $address);

if ($stmt_insert->execute()) {
    $new_customer_id = $stmt_insert->insert_id;
    $stmt_insert->close();
    send_api_json_response([
        'status' => 'success',
        'message' => 'Registration successful. Please login.',
        'customer_id_string' => $customer_id_string,
        'id' => $new_customer_id,
        'customer_id' => $new_customer_id
    ]);
} else {
    $err = $stmt_insert->error;
    $stmt_insert->close();
    send_api_json_response(['status' => 'error', 'message' => 'Database error during registration: ' . $err], 500);
}
?>