<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration
$configPath = '../../Super/config.php';
if (!file_exists($configPath)) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']); exit(); }
include($configPath);

if (!isset($conn) || !$conn instanceof mysqli) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']); exit(); }

// Response array
$response = ['status' => 'error', 'message' => 'Invalid request.'];

// --- 1. Check for POST request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- 2. Get Customer ID from POST data (INSECURE) ---
    // if (!isset($_POST['customer_id']) || !is_numeric($_POST['customer_id'])) {
    //     http_response_code(400); // Bad Request
    //     $response['message'] = 'A valid customer_id is required in the form-data.';
    //     echo json_encode($response);
    //     exit();
    // }
    $customer_id = (int)$_POST['id'];
    
    // --- 3. Get current filenames (and check if customer exists) ---
    $stmt_get = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM customers WHERE id = ?");
    $stmt_get->bind_param("i", $customer_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404); // Not Found
        $response['message'] = 'Customer with this ID not found.';
        echo json_encode($response);
        exit();
    }
    $current_files = $result->fetch_assoc();
    $stmt_get->close();

    $new_filenames = [
        'avatar' => $current_files['avatar'],
        'aadhar_photo' => $current_files['aadhar_photo'],
        'pan_photo' => $current_files['pan_photo']
    ];
    $uploaded_files_info = []; // To return to app

    // --- 4. Reusable Upload Helper Function ---
    function process_upload($file_key, $upload_dir, $current_filename, $customer_id) {
        if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] == 0) {
            $file = $_FILES[$file_key];
            
            // Validation
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $max_size = 10 * 1024 * 1024; // 5 MB
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception("Invalid file type for $file_key. Only JPG, PNG, PDF allowed.");
            }
            if ($file['size'] > $max_size) {
                throw new Exception("File size for $file_key exceeds the 10MB limit.");
            }

            // Create unique filename
            $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
            $new_filename = 'cust_' . $customer_id . '_' . $file_key . '_' . time() . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;

            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            if (move_uploaded_file($file["tmp_name"], $target_file)) {
                // Success: Delete old file if it exists
                if ($current_filename && file_exists($upload_dir . $current_filename)) {
                    unlink($upload_dir . $current_filename);
                }
                return $new_filename; // Return the new name for DB update
            } else {
                throw new Exception("Failed to move uploaded file for $file_key.");
            }
        }
        return $current_filename; // No new file, return the old name
    }

    // --- 5. Process each possible file ---
    try {
        $new_filenames['avatar'] = process_upload(
            'avatar', 
            '../../Agents/upload/customers/avatars/', 
            $current_files['avatar'], 
            $customer_id
        );
        $new_filenames['aadhar_photo'] = process_upload(
            'aadhar_photo', 
            '../../Agents/upload/customers/aadhar_cards/', 
            $current_files['aadhar_photo'], 
            $customer_id
        );
        $new_filenames['pan_photo'] = process_upload(
            'pan_photo', 
            '../../Agents/upload/customers/pan_cards/', 
            $current_files['pan_photo'], 
            $customer_id
        );

        // --- 6. Update Database (Saves FILENAME only) ---
        $sql_update = "UPDATE customers SET avatar = ?, aadhar_photo = ?, pan_photo = ? WHERE id = ?";
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("sssi", 
            $new_filenames['avatar'], 
            $new_filenames['aadhar_photo'], 
            $new_filenames['pan_photo'], 
            $customer_id
        );

        if ($stmt_update->execute()) {
            $response['status'] = 'success';
            $response['message'] = 'Documents updated successfully.';
            // Response includes FILENAME only
            if ($new_filenames['avatar'] !== $current_files['avatar']) $uploaded_files_info['avatar_url'] = $new_filenames['avatar'];
            if ($new_filenames['aadhar_photo'] !== $current_files['aadhar_photo']) $uploaded_files_info['aadhar_url'] = $new_filenames['aadhar_photo'];
            if ($new_filenames['pan_photo'] !== $current_files['pan_photo']) $uploaded_files_info['pan_url'] = $new_filenames['pan_photo'];
            $response['data'] = $uploaded_files_info;
        } else {
            throw new Exception("Database update failed: " . $stmt_update->error);
        }
        $stmt_update->close();

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        // Rollback uploaded files
        if ($new_filenames['avatar'] !== $current_files['avatar'] && file_exists('../../Agents/upload/customers/avatars/' . $new_filenames['avatar'])) {
            unlink('../../Agents/upload/customers/avatars/' . $new_filenames['avatar']);
        }
        if ($new_filenames['aadhar_photo'] !== $current_files['aadhar_photo'] && file_exists('../../Agents/upload/customers/aadhar_cards/' . $new_filenames['aadhar_photo'])) {
            unlink('../../Agents/upload/customers/aadhar_cards/' . $new_filenames['aadhar_photo']);
        }
        if ($new_filenames['pan_photo'] !== $current_files['pan_photo'] && file_exists('../../Agents/upload/customers/pan_cards/' . $new_filenames['pan_photo'])) {
            unlink('../../Agents/upload/customers/pan_cards/' . $new_filenames['pan_photo']);
        }
    }
    
} else {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Invalid request method. Only POST is accepted.';
}

// --- 7. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>