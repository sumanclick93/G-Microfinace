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

// Default Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    
    // --- 2. Check for GET request ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // --- 3. Fetch Admin Details ---
        // Assuming you want the primary Super Admin, we order by ID and limit to 1
        $sql = "SELECT first_name, last_name, email, phone, payment_qr, payment_upi FROM admins ORDER BY id ASC LIMIT 1";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $admin_data = $result->fetch_assoc();
            
            // --- 4. Format the Data for the App ---
            $response['status'] = 'success';
            $response['message'] = 'Admin details fetched successfully.';
            $response['data'] = [
                'name' => trim($admin_data['first_name'] . ' ' . $admin_data['last_name']),
                'email' => $admin_data['email'],
                'phone' => $admin_data['phone'],
                // Note: The app will likely need to attach your website's base URL to this filename to display the image properly.
                'payment_qr' => $admin_data['payment_qr'], 
                'payment_upi' => $admin_data['payment_upi'] 
            ];
            
        } else {
            http_response_code(404); // Not Found
            $response['message'] = 'Admin details could not be found in the database.';
        }
        
    } else {
        http_response_code(405); // Method Not Allowed
        $response['message'] = 'Invalid request method. Only GET is accepted.';
    }

} else {
    // Session ID not found, user is not logged in
    http_response_code(401); // Unauthorized
    $response['message'] = 'Authentication required. Please login.';
}

// --- 5. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>