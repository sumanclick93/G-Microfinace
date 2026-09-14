<?php
header('Content-Type: application/json');

$configPath = 'config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Config file not found.']);
    exit();
}
include($configPath);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['status' => 'error', 'message' => 'Authentication required.'];

if (isset($_SESSION['customer_id'])) {
    $customer_id = (int)$_SESSION['customer_id'];
    $loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : 0;

    if ($loan_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Valid loan_id is required.']);
        exit();
    }

    // Check loan ownership and ensure it is a gold loan
    $stmt_check = $conn->prepare("SELECT gold_photo_path, loan_type FROM loans WHERE id = ? AND customer_id = ?");
    $stmt_check->bind_param("ii", $loan_id, $customer_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();

    if ($res_check->num_rows === 1) {
        $loan_data = $res_check->fetch_assoc();
        $existing_str = $loan_data['gold_photo_path'] ?? '';
        $existing_photos = !empty($existing_str) ? array_filter(explode(',', $existing_str)) : [];

        if (isset($_FILES['gold_photo'])) {
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
            $target_dir = __DIR__ . '/../../Agents/upload/gold_collateral/';
            if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
            @chmod($target_dir, 0755);

            $target_dir_alt = __DIR__ . '/../../Agents/uploads/gold_collateral/';
            if (!is_dir($target_dir_alt)) @mkdir($target_dir_alt, 0755, true);
            @chmod($target_dir_alt, 0755);

            $files_obj = $_FILES['gold_photo'];
            $file_items = [];
            if (is_array($files_obj['name'])) {
                foreach ($files_obj['name'] as $idx => $n) {
                    if (!empty($n)) {
                        $file_items[] = [
                            'name' => $files_obj['name'][$idx],
                            'tmp_name' => $files_obj['tmp_name'][$idx],
                            'error' => $files_obj['error'][$idx],
                        ];
                    }
                }
            } else {
                if (!empty($files_obj['name'])) {
                    $file_items[] = [
                        'name' => $files_obj['name'],
                        'tmp_name' => $files_obj['tmp_name'],
                        'error' => $files_obj['error'],
                    ];
                }
            }

            $new_uploaded = [];
            foreach ($file_items as $item) {
                if ($item['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, $allowed_exts)) {
                        $new_filename = 'gold_cust_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                        $destination = $target_dir . $new_filename;
                        $destination_alt = $target_dir_alt . $new_filename;

                        if (move_uploaded_file($item['tmp_name'], $destination)) {
                            @copy($destination, $destination_alt);
                            $new_uploaded[] = 'upload/gold_collateral/' . $new_filename;
                        } elseif (move_uploaded_file($item['tmp_name'], $destination_alt)) {
                            @copy($destination_alt, $destination);
                            $new_uploaded[] = 'upload/gold_collateral/' . $new_filename;
                        }
                    }
                }
            }

            if (!empty($new_uploaded)) {
                $all_photos = array_merge($existing_photos, $new_uploaded);
                $final_path_str = implode(',', array_unique(array_filter($all_photos)));

                $stmt_up = $conn->prepare("UPDATE loans SET gold_photo_path = ? WHERE id = ? AND customer_id = ?");
                $stmt_up->bind_param("sii", $final_path_str, $loan_id, $customer_id);
                $stmt_up->execute();
                $stmt_up->close();

                $response['status'] = 'success';
                $response['message'] = count($new_uploaded) . ' collateral photo(s) uploaded successfully.';
                $response['data'] = ['loan_id' => $loan_id, 'gold_photo_path' => $final_path_str];
            } else {
                http_response_code(400);
                $response['message'] = 'No valid photo files were uploaded.';
            }
        } else {
            http_response_code(400);
            $response['message'] = 'No gold_photo file provided in request.';
        }
    } else {
        http_response_code(404);
        $response['message'] = 'Loan not found or access denied.';
    }
    $stmt_check->close();
} else {
    http_response_code(401);
    $response['message'] = 'Authentication required.';
}

echo json_encode($response);
$conn->close();
?>
