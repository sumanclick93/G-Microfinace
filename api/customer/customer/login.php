<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.'], 405);
}

// Support both JSON input and $_POST / $_REQUEST
$input_data = json_decode(file_get_contents('php://input'), true);
if (!is_array($input_data)) {
    $input_data = $_POST;
}

$login_identifier = trim($input_data['login_identifier'] ?? $input_data['phone'] ?? $input_data['customer_id_string'] ?? $_POST['login_identifier'] ?? $_POST['phone'] ?? '');
$password = trim($input_data['password'] ?? $_POST['password'] ?? '');

if (empty($login_identifier) || empty($password)) {
    send_api_json_response(['status' => 'error', 'message' => 'Missing required fields: login_identifier and password.'], 400);
}

$sql = "SELECT id, customer_id_string, password, full_name FROM customers WHERE customer_id_string = ? OR phone = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $login_identifier, $login_identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows === 1) {
    $customer = $result->fetch_assoc();

    if (password_verify($password, $customer['password'])) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['customer_id'] = $customer['id'];
        $_SESSION['customer_name'] = $customer['full_name'];
        $_SESSION['customer_login_id'] = $customer['customer_id_string'];

        $stmt->close();
        send_api_json_response([
            'status' => 'success',
            'message' => 'Login successful.',
            'customer_id' => $customer['id'],
            'customer_data' => [
                'id' => $customer['id'],
                'name' => $customer['full_name'],
                'customer_id_string' => $customer['customer_id_string']
            ]
        ]);
    } else {
        $stmt->close();
        send_api_json_response(['status' => 'error', 'message' => 'Invalid login identifier or password.'], 401);
    }
} else {
    if ($stmt) $stmt->close();
    send_api_json_response(['status' => 'error', 'message' => 'Invalid login identifier or password.'], 401);
}
?>