<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            send_api_json_response(['status' => 'error', 'message' => 'File upload error: Code ' . $file['error']], 400, $conn);
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 MB

            if (!in_array($file['type'], $allowed_types)) {
                send_api_json_response(['status' => 'error', 'message' => 'Invalid file type. Only JPG, PNG, GIF allowed.'], 400, $conn);
            } elseif ($file['size'] > $max_size) {
                send_api_json_response(['status' => 'error', 'message' => 'File size exceeds the 5MB limit.'], 400, $conn);
            } else {
                $upload_dir = '../../Agents/upload/customers/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($file["name"], PATHINFO_EXTENSION);
                $new_filename = 'cust_' . $customer_id . '_' . time() . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;

                $stmt_get = $conn->prepare("SELECT avatar FROM customers WHERE id = ?");
                $stmt_get->bind_param("i", $customer_id);
                $stmt_get->execute();
                $current_avatar = $stmt_get->get_result()->fetch_assoc()['avatar'] ?? null;
                $stmt_get->close();

                if (move_uploaded_file($file["tmp_name"], $target_file)) {
                    $stmt_update = $conn->prepare("UPDATE customers SET avatar = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_filename, $customer_id);

                    if ($stmt_update->execute()) {
                        if ($current_avatar && file_exists($upload_dir . $current_avatar)) {
                            @unlink($upload_dir . $current_avatar);
                        }
                        $stmt_update->close();
                        send_api_json_response(['status' => 'success', 'message' => 'Avatar updated successfully.', 'avatar_url' => $new_filename], 200, $conn);
                    } else {
                        $err = $stmt_update->error;
                        $stmt_update->close();
                        if (file_exists($target_file)) @unlink($target_file);
                        send_api_json_response(['status' => 'error', 'message' => 'Database error updating avatar: ' . $err], 500, $conn);
                    }
                } else {
                    send_api_json_response(['status' => 'error', 'message' => 'Failed to move uploaded file.'], 500, $conn);
                }
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.'], 405, $conn);
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'No avatar file provided in the request.'], 400, $conn);
    }
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>
