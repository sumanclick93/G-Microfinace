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

    // --- 2. Check for POST request and File Upload ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {

        $file = $_FILES['avatar'];

        // --- 3. Validate File Upload ---
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $response['message'] = 'File upload error: Code ' . $file['error'];
        } else {
            // Basic validation
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed_types)) {
                $response['message'] = 'Invalid file type. Only JPG, PNG, GIF allowed.';
            } elseif ($file['size'] > $max_size) {
                $response['message'] = 'File size exceeds the 5MB limit.';
            } else {
                // --- 4. Process Upload ---
                $upload_dir = '../../Agents/upload/customers/avatars/'; // Path relative to this script
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
                $new_filename = 'cust_' . $customer_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;

                // --- 5. Get current avatar filename ---
                $stmt_get = $conn->prepare("SELECT avatar FROM customers WHERE id = ?");
                $stmt_get->bind_param("i", $customer_id);
                $stmt_get->execute();
                $current_avatar = $stmt_get->get_result()->fetch_assoc()['avatar'];
                $stmt_get->close();

                // --- 6. Move the new file ---
                if (move_uploaded_file($file["tmp_name"], $target_file)) {

                    // --- 7. Update Database ---
                    $stmt_update = $conn->prepare("UPDATE customers SET avatar = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_filename, $customer_id);

                    if ($stmt_update->execute()) {
                        // --- 8. Delete Old Avatar ---
                        if ($current_avatar && file_exists($upload_dir . $current_avatar)) {
                            unlink($upload_dir . $current_avatar);
                        }

                        $response['status'] = 'success';
                        $response['message'] = 'Avatar updated successfully.';
                        // ** THIS IS THE CHANGE: Only send the filename **
                        $response['avatar_url'] = $new_filename; // Changed from the full path

                    } else {
                        $response['message'] = 'Database error updating avatar: ' . $stmt_update->error;
                        if (file_exists($target_file)) unlink($target_file);
                    }
                    $stmt_update->close();

                } else {
                    $response['message'] = 'Failed to move uploaded file.';
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response['message'] = 'Invalid request method. Only POST is accepted.';
    } else {
         $response['message'] = 'No avatar file provided in the request.';
    }

} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

// --- 9. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>