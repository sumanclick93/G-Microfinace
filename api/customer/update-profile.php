<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration
include('config.php');

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 2. Check for PUT request method ---
    // Note: Some hosting/clients might have issues with PUT, you could use POST instead.
    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') { // Allow POST as fallback

        // --- 3. Get data from the request body (assuming JSON) ---
        $input_data = json_decode(file_get_contents('php://input'), true);

        // --- 4. Prepare data for update ---
        $fields_to_update = [];
        $params = [];
        $types = '';

        // Sanitize and add fields if they are provided in the input
        if (isset($input_data['full_name']) && !empty(trim($input_data['full_name']))) {
            $fields_to_update[] = "full_name = ?";
            $params[] = trim($input_data['full_name']);
            $types .= 's';
        }
        if (isset($input_data['email'])) { // Allow setting email to empty/null if desired
             $email = trim($input_data['email']);
             // Optional: Add email validation
             if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                 $response['message'] = 'Invalid email format provided.';
                 echo json_encode($response);
                 exit();
             }
            $fields_to_update[] = "email = ?";
            $params[] = $email ?: null; // Store NULL if empty string
            $types .= 's';
        }
        if (isset($input_data['address'])) {
            $fields_to_update[] = "address = ?";
            $params[] = trim($input_data['address']);
            $types .= 's';
        }

        // Handle optional password update
        if (isset($input_data['new_password']) && !empty($input_data['new_password'])) {
            $fields_to_update[] = "password = ?";
            $params[] = password_hash($input_data['new_password'], PASSWORD_DEFAULT);
            $types .= 's';
        }

        // --- 5. Build and Execute UPDATE Query ---
        if (!empty($fields_to_update)) {
            $sql = "UPDATE customers SET " . implode(', ', $fields_to_update) . " WHERE id = ?";
            $params[] = $customer_id; // Add customer ID for the WHERE clause
            $types .= 'i';

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                 // Use spread operator (...) for binding parameters
                 $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'Profile updated successfully.';
                        // Optionally update session name if changed
                        if (isset($input_data['full_name'])) {
                           $_SESSION['customer_name'] = trim($input_data['full_name']);
                        }
                    } else {
                        // Query executed, but no rows were changed (maybe data was the same)
                         $response['status'] = 'success'; // Still success, just no change
                         $response['message'] = 'No changes detected in profile data.';
                    }
                } else {
                    $response['message'] = 'Database error during update: ' . $stmt->error;
                }
                $stmt->close();
            } else {
                 $response['message'] = 'Database error preparing statement: ' . $conn->error;
            }

        } else {
            $response['message'] = 'No valid fields provided for update.';
        }

    } else {
        $response['message'] = 'Invalid request method. Only PUT or POST are accepted.';
    }

} else {
    // Session ID not found, user is not logged in
    http_response_code(401); // Unauthorized status code
    $response['message'] = 'Authentication required. Please login.';
}

// --- 6. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>