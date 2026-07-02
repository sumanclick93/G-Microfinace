<?php
header('Content-Type: application/json');

$configPath = '../../Super/config.php';
if (!file_exists($configPath)) {
    http_response_code(500); 
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error.']);
    exit();
}
include($configPath);

$response = ['status' => 'error', 'message' => 'Authentication required.'];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loan_id = isset($_POST['loan_id']) ? intval($_POST['loan_id']) : 0;
        $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

        if ($loan_id > 0 && $amount > 0 && isset($_FILES['proof'])) {
            
            $file = $_FILES['proof'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            
            if (in_array($file['type'], $allowed_types) && $file['error'] === 0) {
                
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $new_filename = 'loan_proof_' . $customer_id . '_' . time() . '.' . $ext;
                
                $upload_dir = '../uploads/proofs/'; 
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    
                    // 1. Set Timezone and grab current time
                    date_default_timezone_set('Asia/Kolkata');
                    $payment_date = date('Y-m-d H:i:s');

                    // 2. Automatically calculate the EMI Number
                    $emi_count_stmt = $conn->prepare("SELECT COUNT(*) FROM payments WHERE loan_id = ?");
                    $emi_count_stmt->bind_param("i", $loan_id);
                    $emi_count_stmt->execute();
                    $emi_count = $emi_count_stmt->get_result()->fetch_row()[0] + 1; // Add 1 for the current payment
                    $emi_count_stmt->close();
                    
                    $notes = "EMI #" . $emi_count . " paid by Customer";

                    // 3. Insert into Database as PENDING with new fields
                    $sql = "INSERT INTO payments (loan_id, amount_paid, proof_image, status, payment_date, collected_by_agent_id, notes) 
                            VALUES (?, ?, ?, 'pending', ?, 'self', ?)";
                    $stmt = $conn->prepare($sql);
                    
                    // Types: Integer(loan_id), Double(amount), String(proof), String(date), String(notes)
                    $stmt->bind_param("idsss", $loan_id, $amount, $new_filename, $payment_date, $notes);
                    
                    if ($stmt->execute()) {
                        $response['status'] = 'success';
                        $response['message'] = 'Payment proof uploaded successfully. Pending Admin approval.';
                    } else {
                        http_response_code(500);
                        $response['message'] = 'Database error: Could not save your payment record.';
                    }
                    $stmt->close();

                } else {
                    $response['message'] = 'Failed to save the uploaded file to the server.';
                }
            } else {
                $response['message'] = 'Invalid file type. Please upload a JPG, PNG, or PDF.';
            }
        } else {
            $response['message'] = 'Missing loan_id, amount, or proof file.';
        }
    } else {
        http_response_code(405);
        $response['message'] = 'Invalid request method. Only POST is accepted.';
    }
} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

echo json_encode($response);
$conn->close();
?>