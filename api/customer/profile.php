<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (adjust path as needed)
include('config.php'); // Assuming config is two levels up in Super folder

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 2. Prepare Query ---
    // Fetch customer details and join with agents table to get agent info
    $sql = "SELECT
                c.id, c.full_name, c.customer_id_string, c.phone, c.email, c.address, c.avatar,
                a.first_name as agent_first_name, a.last_name as agent_last_name, a.phone as agent_phone
            FROM customers c
            JOIN agents a ON c.agent_id = a.id
            WHERE c.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);

    // --- 3. Execute and Fetch Data ---
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $customer_data = $result->fetch_assoc();

            // --- 4. Format the Response ---
            $response['status'] = 'success';
            $response['message'] = 'Profile data fetched successfully.';

            // Construct the avatar URL (adjust base path if needed)
            $avatar_url = null;
            if (!empty($customer_data['avatar'])) {
                // Use an absolute path from the web root for consistency
                $avatar_url = $customer_data['avatar'];
            }

            $response['data'] = [
                'id' => $customer_data['id'],
                'full_name' => $customer_data['full_name'],
                'customer_id_string' => $customer_data['customer_id_string'],
                'phone' => $customer_data['phone'],
                'email' => $customer_data['email'],
                'address' => $customer_data['address'],
                'avatar_url' => $avatar_url, // Send the full URL or relative path
                'agent' => [
                    'name' => trim($customer_data['agent_first_name'] . ' ' . $customer_data['agent_last_name']),
                    'phone' => $customer_data['agent_phone']
                ]
            ];
            unset($response['message']); // Remove default message on success

        } else {
            // Customer record not found for the logged-in ID (unlikely but possible)
            $response['message'] = 'Customer profile not found.';
            // Log out the user as their session ID is invalid
            session_destroy();
        }
    } else {
        $response['message'] = 'Database error fetching profile: ' . $stmt->error;
    }
    $stmt->close();

} else {
    // Session ID not found, user is not logged in
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 5. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>