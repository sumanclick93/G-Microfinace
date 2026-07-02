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

    // --- 2. Check for POST request ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // --- 3. Get data from the request body (JSON) ---
        $input_data = json_decode(file_get_contents('php://input'), true);
        $message = trim($input_data['message'] ?? '');

        // --- 4. Validate Input ---
        if (empty($message)) {
            $response['message'] = 'The message field cannot be empty.';
        } else {
            
            // --- 5. Get Customer's Agent ID and Phone Number ---
            $agent_id = null;
            $customer_phone = null;
            $stmt_info = $conn->prepare("SELECT agent_id, phone FROM customers WHERE id = ?");
            $stmt_info->bind_param("i", $customer_id);
            if($stmt_info->execute()){
                $result_info = $stmt_info->get_result();
                if($result_info->num_rows > 0){
                    $cust_data = $result_info->fetch_assoc();
                    $agent_id = $cust_data['agent_id'];
                    $customer_phone = $cust_data['phone'];
                }
            }
            $stmt_info->close();

            if (empty($agent_id) || empty($customer_phone)) {
                 $response['message'] = 'Could not find customer profile data.';
            } else {
                // --- 6. Prepare and Execute INSERT (MODIFIED) ---
                // Removed the 'status' column from the query
                $sql = "INSERT INTO customer_queries (customer_id, agent_id, customer_phone, message) VALUES (?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql);
                // Types: i (customer_id), i (agent_id), s (phone), s (message)
                $stmt_insert->bind_param("iiss", $customer_id, $agent_id, $customer_phone, $message);

                if ($stmt_insert->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Your query has been submitted successfully.';
                } else {
                    http_response_code(500);
                    $response['message'] = 'Database error: Could not save your query.';
                }
                $stmt_insert->close();
            }
        }
    } else {
        http_response_code(405); // Method Not Allowed
        $response['message'] = 'Invalid request method. Only POST is accepted.';
    }

} else {
    // Session ID not found, user is not logged in
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 7. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>