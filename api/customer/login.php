<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (adjust path as needed)
include('config.php'); // Assuming config is two levels up in Super folder

// Response array
$response = ['status' => 'error', 'message' => 'Invalid request.'];

// Start session to store login state
// Note: For mobile apps, JWT (JSON Web Tokens) are generally preferred over PHP sessions.
//       This example uses sessions for simplicity based on our previous web context.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Check if it's a POST request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 2. Get data from the request body (assuming JSON) ---
    $input_data = json_decode(file_get_contents('php://input'), true);

    // --- 3. Sanitize and Validate Input ---
    // Allow login with either customer_id_string OR phone number
    $login_identifier = trim($input_data['login_identifier'] ?? ''); // Could be customer_id_string or phone
    $password = trim($input_data['password'] ?? '');

    if (empty($login_identifier) || empty($password)) {
        $response['message'] = 'Missing required fields: login_identifier and password.';
    } else {
        // --- 4. Prepare Query (Check both customer_id_string and phone) ---
        $sql = "SELECT id, customer_id_string, password, full_name FROM customers WHERE customer_id_string = ? OR phone = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $login_identifier, $login_identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        // --- 5. Verify User and Password ---
        if ($result->num_rows === 1) {
            $customer = $result->fetch_assoc();

            if (password_verify($password, $customer['password'])) {
                // --- 6. Password is correct - Login Success ---
                // Store user info in session (or generate JWT token)
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['full_name'];
                $_SESSION['customer_login_id'] = $customer['customer_id_string']; // Store the ID used for login

                $response['status'] = 'success';
                $response['message'] = 'Login successful.';
                // Include basic customer data in the response
                $response['customer_data'] = [
                    'id' => $customer['id'],
                    'name' => $customer['full_name'],
                    'customer_id_string' => $customer['customer_id_string']
                ];
                // If using JWT, add token here: 'token' => 'YOUR_JWT_TOKEN'

            } else {
                // Incorrect password
                $response['message'] = 'Invalid login identifier or password.';
            }
        } else {
            // No user found with that ID or phone
            $response['message'] = 'Invalid login identifier or password.';
        }
        $stmt->close();
    }
} else {
    $response['message'] = 'Invalid request method. Only POST is accepted.';
}

// --- 7. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>