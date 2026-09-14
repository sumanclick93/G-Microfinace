<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rd_id = isset($_POST['rd_id']) ? intval($_POST['rd_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

        if ($rd_id > 0 && $amount > 0 && isset($_FILES['proof'])) {
            
            $file = $_FILES['proof'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            
            if (in_array($file['type'], $allowed_types) && $file['error'] === 0) {
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'rd_proof_' . $customer_id . '_' . time() . '.' . $ext;
                
                $upload_dir = '../uploads/proofs/'; 
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    
                    date_default_timezone_set('Asia/Kolkata');
                    $payment_date = date('Y-m-d H:i:s');

                    $rd_count_stmt = $conn->prepare("SELECT COUNT(*) FROM rd_payments WHERE rd_id = ?");
                    $rd_count_stmt->bind_param("i", $rd_id);
                    $rd_count_stmt->execute();
                    $rd_count = $rd_count_stmt->get_result()->fetch_row()[0] + 1;
                    $rd_count_stmt->close();
                    
                    $notes = "RD Installment #" . $rd_count . " paid by Customer";

                    $sql = "INSERT INTO rd_payments (rd_id, amount_paid, proof_image, status, payment_date, collected_by_agent_id, notes) 
                            VALUES (?, ?, ?, 'pending', ?, 'self', ?)";
                    $stmt = $conn->prepare($sql);
                    
                    $stmt->bind_param("idsss", $rd_id, $amount, $new_filename, $payment_date, $notes);
                    
                    if ($stmt->execute()) {
                        $stmt->close();
                        send_api_json_response(['status' => 'success', 'message' => 'RD payment proof uploaded successfully. Pending Admin approval.'], 200, $conn);
                    } else {
                        $err = $stmt->error;
                        $stmt->close();
                        send_api_json_response(['status' => 'error', 'message' => 'Database error: Could not save your payment record. ' . $err], 500, $conn);
                    }

                } else {
                    send_api_json_response(['status' => 'error', 'message' => 'Failed to save the uploaded file to the server.'], 500, $conn);
                }
            } else {
                send_api_json_response(['status' => 'error', 'message' => 'Invalid file type. Please upload a JPG, PNG, or PDF.'], 400, $conn);
            }
        } else {
            send_api_json_response(['status' => 'error', 'message' => 'Missing rd_id, amount, or proof file.'], 400, $conn);
        }
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'Invalid request method. Only POST is accepted.'], 405, $conn);
    }
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>