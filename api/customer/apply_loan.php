<?php
header('Content-Type: application/json');

$configPath = 'config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Config file not found.']);
    exit();
}
include($configPath);

if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit();
}

$response = ['status' => 'error', 'message' => 'Authentication required.'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['customer_id'])) {
    $customer_id = (int)$_SESSION['customer_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loan_type = isset($_POST['loan_type']) ? trim($_POST['loan_type']) : 'standard';
        $loan_amount = isset($_POST['loan_amount']) ? (float)$_POST['loan_amount'] : 0;
        $interest_rate = isset($_POST['interest_rate']) ? (float)$_POST['interest_rate'] : 0;
        $tenure = isset($_POST['tenure']) ? (int)$_POST['tenure'] : 0;
        $repayment_cycle = isset($_POST['repayment_cycle']) ? trim($_POST['repayment_cycle']) : 'monthly';
        $start_date = isset($_POST['start_date']) ? trim($_POST['start_date']) : date('Y-m-d');

        if (!in_array($loan_type, ['standard', 'interest_only', 'gold'])) {
            $loan_type = 'standard';
        }

        $interest_calculation_type = ($loan_type === 'interest_only') ? 'monthly_interest_only' : 'flat_total';

        // Fetch customer's assigned agent_id
        $agent_id = 0;
        $stmt_cust = $conn->prepare("SELECT agent_id FROM customers WHERE id = ?");
        $stmt_cust->bind_param("i", $customer_id);
        $stmt_cust->execute();
        $res_cust = $stmt_cust->get_result();
        if ($res_cust && $row_cust = $res_cust->fetch_assoc()) {
            $agent_id = (int)$row_cust['agent_id'];
        }
        $stmt_cust->close();

        if ($agent_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No active agent assigned to your account.']);
            exit();
        }

        $gold_rate_per_gram = (float)get_system_setting($conn, 'gold_rate_per_gram', '5500.00');
        $processing_fee_percent = (float)get_system_setting($conn, 'gold_loan_processing_fee_percent', '1.50');

        $gold_weight_grams = null;
        $gold_photo_path = null;
        $gold_rate_applied = null;
        $processing_fee = 0.00;

        if ($loan_type === 'gold') {
            $gold_weight_grams = isset($_POST['gold_weight_grams']) ? (float)$_POST['gold_weight_grams'] : 0;
            $gold_rate_applied = $gold_rate_per_gram;
            $processing_fee = round(($loan_amount * $processing_fee_percent) / 100, 2);

            $gold_valuation = $gold_weight_grams * $gold_rate_applied;

            if ($gold_weight_grams <= 0) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Gold weight in grams is required for Gold Loan.']);
                exit();
            }

            if ($loan_amount > $gold_valuation) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Requested loan amount exceeds gold collateral valuation (₹' . number_format($gold_valuation, 2) . ').']);
                exit();
            }

            if (isset($_FILES['gold_photo']) && $_FILES['gold_photo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['gold_photo']['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
                if (in_array($ext, $allowed_exts)) {
                    $upload_dir = '../../Agents/upload/gold_collateral/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $new_filename = 'gold_api_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                    $destination = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['gold_photo']['tmp_name'], $destination)) {
                        $gold_photo_path = 'upload/gold_collateral/' . $new_filename;
                    } else {
                        http_response_code(500);
                        echo json_encode(['status' => 'error', 'message' => 'Failed to save gold item photo to server folder.']);
                        exit();
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid gold photo format (.' . $ext . '). Please upload JPG, PNG, or WEBP.']);
                    exit();
                }
            } else {
                $err_code = $_FILES['gold_photo']['error'] ?? 'NO_FILE';
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Gold collateral photo upload is required (Error code: ' . $err_code . ').']);
                exit();
            }
        }

        if ($loan_amount <= 0 || $tenure <= 0 || $interest_rate < 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid loan parameters provided.']);
            exit();
        }

        // Calculations
        if ($loan_type === 'interest_only') {
            $monthly_installment = round($loan_amount * ($interest_rate / 100), 2);
            $total_repayable_amount = round($loan_amount + ($monthly_installment * $tenure), 2);
        } else {
            $total_repayable_amount = round($loan_amount * (1 + ($interest_rate / 100)), 2);
            $monthly_installment = round($total_repayable_amount / $tenure, 2);
        }

        date_default_timezone_set('Asia/Kolkata');
        $application_date = date('Y-m-d H:i:s');

        $sql = "INSERT INTO loans (
                    customer_id, agent_id, loan_type, interest_calculation_type,
                    loan_amount, interest_rate, tenure, repayment_cycle,
                    total_repayable_amount, monthly_installment, gold_weight_grams,
                    gold_photo_path, gold_rate_per_gram, processing_fee,
                    status, application_date, approval_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "iissddissddddsss",
            $customer_id, $agent_id, $loan_type, $interest_calculation_type,
            $loan_amount, $interest_rate, $tenure, $repayment_cycle,
            $total_repayable_amount, $monthly_installment, $gold_weight_grams,
            $gold_photo_path, $gold_rate_applied, $processing_fee,
            $application_date, $start_date
        );

        if ($stmt->execute()) {
            $new_loan_id = $stmt->insert_id;
            $response['status'] = 'success';
            $response['message'] = 'Loan application submitted successfully.';
            $response['data'] = [
                'loan_id' => $new_loan_id,
                'loan_type' => $loan_type,
                'loan_amount' => $loan_amount,
                'total_repayable_amount' => $total_repayable_amount,
                'monthly_installment' => $monthly_installment,
                'processing_fee' => $processing_fee,
                'status' => 'pending'
            ];
        } else {
            http_response_code(500);
            $response['message'] = 'Database insertion error: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        http_response_code(405);
        $response['message'] = 'Only POST request method allowed.';
    }
} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

echo json_encode($response);
$conn->close();
?>
