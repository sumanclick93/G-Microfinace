<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

$customer_id = get_current_customer_id();

if (!$customer_id) {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

$loan_id = isset($_POST['loan_id']) ? (int)$_POST['loan_id'] : (isset($_GET['loan_id']) ? (int)$_GET['loan_id'] : 0);

if ($loan_id <= 0) {
    send_api_json_response(['status' => 'error', 'message' => 'Valid loan_id is required.'], 400);
}

// Check loan ownership and ensure it is a gold loan
$stmt_check = $conn->prepare("SELECT gold_photo_path, loan_type FROM loans WHERE id = ? AND customer_id = ?");
$stmt_check->bind_param("ii", $loan_id, $customer_id);
$stmt_check->execute();
$res_check = $stmt_check->get_result();

if ($res_check && $res_check->num_rows === 1) {
    $loan_data = $res_check->fetch_assoc();
    $stmt_check->close();

    $existing_str = $loan_data['gold_photo_path'] ?? '';
    $existing_photos = !empty($existing_str) ? array_filter(explode(',', $existing_str)) : [];

    if (isset($_FILES['gold_photo']) || isset($_FILES['gold_photo_path']) || isset($_FILES['gold_photo_file'])) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
        $target_dir = __DIR__ . '/../../../Agents/upload/gold_collateral/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
        @chmod($target_dir, 0755);

        $target_dir_alt = __DIR__ . '/../../../Agents/uploads/gold_collateral/';
        if (!is_dir($target_dir_alt)) @mkdir($target_dir_alt, 0755, true);
        @chmod($target_dir_alt, 0755);

        $f_key = isset($_FILES['gold_photo']) ? 'gold_photo' : (isset($_FILES['gold_photo_path']) ? 'gold_photo_path' : 'gold_photo_file');
        $files_obj = $_FILES[$f_key];
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

            send_api_json_response([
                'status' => 'success',
                'message' => 'Gold collateral photo(s) uploaded successfully.',
                'gold_photo_path' => $final_path_str,
                'photos' => array_values(array_unique(array_filter($all_photos)))
            ]);
        } else {
            send_api_json_response(['status' => 'error', 'message' => 'No valid photo files were uploaded.'], 400);
        }
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'No gold photo file provided in request.'], 400);
    }
} else {
    if ($stmt_check) $stmt_check->close();
    send_api_json_response(['status' => 'error', 'message' => 'Loan record not found or unauthorized.'], 404);
}
?>
