<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration
$configPath = '../../Super/config.php';
if (!file_exists($configPath)) {
    http_response_code(500); 
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
    exit();
}
include($configPath);

if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    $support_data = [
        'agent' => null,
        'admin' => null
    ];

    // --- 2. Get Assigned Agent's Contact Info ---
    $sql_agent = "SELECT a.first_name, a.last_name, a.phone 
                  FROM agents a
                  JOIN customers c ON a.id = c.agent_id
                  WHERE c.id = ?";
    $stmt_agent = $conn->prepare($sql_agent);
    $stmt_agent->bind_param("i", $customer_id);
    if ($stmt_agent->execute()) {
        $result_agent = $stmt_agent->get_result();
        if ($result_agent->num_rows > 0) {
            $agent = $result_agent->fetch_assoc();
            $support_data['agent'] = [
                'name' => trim($agent['first_name'] . ' ' . $agent['last_name']),
                'phone' => $agent['phone']
            ];
        }
    }
    $stmt_agent->close();

    // --- 3. Get Super Admin's Contact Info ---
    // Assumes there is only one admin, or the first one is the main contact.
    $sql_admin = "SELECT first_name, last_name, phone FROM admins LIMIT 1";
    $result_admin = $conn->query($sql_admin);
    if ($result_admin && $result_admin->num_rows > 0) {
        $admin = $result_admin->fetch_assoc();
        $support_data['admin'] = [
            'name' => trim($admin['first_name'] . ' ' . $admin['last_name']),
            'phone' => $admin['phone']
        ];
    }
    
    // --- 4. Format Success Response ---
    $response['status'] = 'success';
    $response['data'] = $support_data;
    unset($response['message']);

} else {
    // Session ID not found, user is not logged in
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 5. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>