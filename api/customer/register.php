<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (adjust path as needed)
include('../../Super/config.php'); // Assuming config is two levels up in Super folder

// Response array
$response = ['status' => 'error', 'message' => 'Invalid request.'];

// --- 1. Check if it's a POST request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 2. Get data from the request body (assuming JSON) ---
    $input_data = json_decode(file_get_contents('php://input'), true);

    // --- 3. Sanitize and Validate Input ---
    $full_name = trim($input_data['full_name'] ?? '');
    $phone = trim($input_data['phone'] ?? '');
    $password = trim($input_data['password'] ?? '');
    $address = trim($input_data['address'] ?? '');
    $agent_id = filter_var($input_data['agent_id'] ?? 0, FILTER_VALIDATE_INT);

    if (empty($full_name) || empty($phone) || empty($password) || $agent_id === false || $agent_id <= 0) {
        $response['message'] = 'Missing required fields: full_name, phone, password, agent_id.';
    } else {
        // --- 4. Check if phone number already exists ---
        $stmt_check_phone = $conn->prepare("SELECT id FROM customers WHERE phone = ?");
        $stmt_check_phone->bind_param("s", $phone);
        $stmt_check_phone->execute();
        $stmt_check_phone->store_result();

        if ($stmt_check_phone->num_rows > 0) {
            $response['message'] = 'Phone number is already registered.';
        } else {
            // --- 5. Check if selected agent exists ---
            $stmt_check_agent = $conn->prepare("SELECT id FROM agents WHERE id = ? AND is_active = TRUE");
            $stmt_check_agent->bind_param("i", $agent_id);
            $stmt_check_agent->execute();
            $stmt_check_agent->store_result();

            if ($stmt_check_agent->num_rows === 0) {
                $response['message'] = 'Invalid or inactive agent selected.';
            } else {
                // --- 6. Hash Password ---
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // --- 7. Generate Unique Customer ID String ---
                $customer_id_string = 'CUST' . time() . rand(10, 99);

                // --- 8. Prepare and Execute INSERT ---
                $sql = "INSERT INTO customers (agent_id, customer_id_string, password, full_name, phone, address) 
                        VALUES (?, ?, ?, ?, ?, ?)"; 

                $stmt_insert = $conn->prepare($sql);
                $stmt_insert->bind_param("isssss", $agent_id, $customer_id_string, $hashed_password, $full_name, $phone, $address);

                if ($stmt_insert->execute()) {
                    // ** MODIFICATION HERE **
                    $new_customer_id = $stmt_insert->insert_id; // Get the newly created ID

                    $response['status'] = 'success';
                    $response['message'] = 'Registration successful. Please login.';
                    $response['customer_id_string'] = $customer_id_string;
                    $response['id'] = $new_customer_id; // Add the new numerical ID to the response
                    
                } else {
                    $response['message'] = 'Database error during registration: ' . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check_agent->close();
        }
        $stmt_check_phone->close();
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is accepted.';
}

// --- 9. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>