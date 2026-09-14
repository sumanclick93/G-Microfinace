<?php
require_once __DIR__ . '/config.php';

if (!isset($conn) || !$conn instanceof mysqli) {
    send_api_json_response(['status' => 'error', 'message' => 'Database connection failed.'], 500);
}

$customer_id = get_current_customer_id();

if (!$customer_id) {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_api_json_response(['status' => 'error', 'message' => 'Only POST request method allowed.'], 405);
}

// Support both JSON input and $_POST
$input_data = json_decode(file_get_contents('php://input'), true);
if (!is_array($input_data)) {
    $input_data = $_POST;
}

$loan_type = isset($input_data['loan_type']) ? trim($input_data['loan_type']) : 'standard';
$loan_amount = isset($input_data['loan_amount']) ? (float)$input_data['loan_amount'] : 0;
$interest_rate = isset($input_data['interest_rate']) ? (float)$input_data['interest_rate'] : 0;
$tenure = isset($input_data['tenure']) ? (int)$input_data['tenure'] : 0;
$repayment_cycle = isset($input_data['repayment_cycle']) ? trim($input_data['repayment_cycle']) : 'monthly';
$start_date = isset($input_data['start_date']) && !empty($input_data['start_date']) ? trim($input_data['start_date']) : date('Y-m-d');

if (!in_array($loan_type, ['standard', 'interest_only', 'gold'])) {
    $loan_type = 'standard';
}

$interest_calculation_type = ($loan_type === 'interest_only' || $loan_type === 'gold') ? 'monthly_interest_only' : 'flat_total';

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
    send_api_json_response(['status' => 'error', 'message' => 'No active agent assigned to your account.'], 400);
}

$gold_rate_per_gram = (float)get_system_setting($conn, 'gold_rate_per_gram', '5500.00');
$processing_fee_percent = (float)get_system_setting($conn, 'gold_loan_processing_fee_percent', '1.50');

$gold_weight_grams = null;
$gold_photo_path = null;
$gold_rate_applied = null;
$processing_fee = 0.00;

if ($loan_type === 'gold') {
    $gold_weight_grams = isset($input_data['gold_weight_grams']) ? (float)$input_data['gold_weight_grams'] : 0;
    $gold_rate_applied = $gold_rate_per_gram;
    $processing_fee = round(($loan_amount * $processing_fee_percent) / 100, 2);

    $gold_valuation = $gold_weight_grams * $gold_rate_applied;

    if ($gold_weight_grams <= 0) {
        send_api_json_response(['status' => 'error', 'message' => 'Gold weight in grams is required for Gold Loan.'], 400);
    }

    if ($loan_amount > $gold_valuation) {
        send_api_json_response(['status' => 'error', 'message' => 'Requested loan amount exceeds gold collateral valuation (₹' . number_format($gold_valuation, 2) . ').'], 400);
    }

    $file_items = [];
    foreach (['gold_photo', 'gold_photo_path', 'gold_photo_file'] as $f_key) {
        if (isset($_FILES[$f_key])) {
            $f_obj = $_FILES[$f_key];
            if (is_array($f_obj['name'])) {
                foreach ($f_obj['name'] as $idx => $fname) {
                    if (!empty($fname)) {
                        $file_items[] = [
                            'name' => $f_obj['name'][$idx],
                            'tmp_name' => $f_obj['tmp_name'][$idx],
                            'error' => $f_obj['error'][$idx],
                        ];
                    }
                }
            } elseif (!empty($f_obj['name'])) {
                $file_items[] = [
                    'name' => $f_obj['name'],
                    'tmp_name' => $f_obj['tmp_name'],
                    'error' => $f_obj['error'],
                ];
            }
        }
    }

    if (!empty($file_items)) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
        $target_dir = __DIR__ . '/../../../Agents/upload/gold_collateral/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
        @chmod($target_dir, 0755);

        $target_dir_alt = __DIR__ . '/../../../Agents/uploads/gold_collateral/';
        if (!is_dir($target_dir_alt)) @mkdir($target_dir_alt, 0755, true);
        @chmod($target_dir_alt, 0755);

        $uploaded_paths = [];
        foreach ($file_items as $item) {
            if ($item['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_exts)) {
                    $new_filename = 'gold_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $destination = $target_dir . $new_filename;
                    $destination_alt = $target_dir_alt . $new_filename;

                    if (move_uploaded_file($item['tmp_name'], $destination)) {
                        @copy($destination, $destination_alt);
                        $uploaded_paths[] = 'upload/gold_collateral/' . $new_filename;
                    } elseif (move_uploaded_file($item['tmp_name'], $destination_alt)) {
                        @copy($destination_alt, $destination);
                        $uploaded_paths[] = 'upload/gold_collateral/' . $new_filename;
                    }
                }
            }
        }

        if (!empty($uploaded_paths)) {
            $gold_photo_path = implode(',', $uploaded_paths);
        }
    }
}

if ($loan_amount <= 0 || $tenure <= 0 || $interest_rate < 0) {
    send_api_json_response(['status' => 'error', 'message' => 'Invalid loan parameter values.'], 400);
}

if ($interest_calculation_type === 'monthly_interest_only') {
    $monthly_interest = round(($loan_amount * $interest_rate) / 100, 2);
    $total_repayable_amount = round($loan_amount + ($monthly_interest * $tenure), 2);
    $monthly_installment = $monthly_interest;
} else {
    $total_interest = round(($loan_amount * $interest_rate * $tenure) / 100, 2);
    $total_repayable_amount = round($loan_amount + $total_interest, 2);
    $monthly_installment = round($total_repayable_amount / max(1, $tenure), 2);
}

date_default_timezone_set('Asia/Kolkata');
$application_date = date('Y-m-d H:i:s');

$sql = "INSERT INTO loans (
            customer_id, agent_id, loan_type, interest_calculation_type,
            loan_amount, interest_rate, tenure, repayment_cycle,
            total_repayable_amount, monthly_installment, gold_weight_grams,
            gold_photo_path, gold_rate_per_gram, processing_fee,
            status, application_date, approval_date, loan_start_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, NULL, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iissddissddsddss",
    $customer_id, $agent_id, $loan_type, $interest_calculation_type,
    $loan_amount, $interest_rate, $tenure, $repayment_cycle,
    $total_repayable_amount, $monthly_installment, $gold_weight_grams,
    $gold_photo_path, $gold_rate_applied, $processing_fee,
    $application_date, $start_date
);

if ($stmt->execute()) {
    $new_loan_id = $stmt->insert_id;
    $stmt->close();
    send_api_json_response([
        'status' => 'success',
        'message' => 'Loan application submitted successfully.',
        'data' => [
            'loan_id' => $new_loan_id,
            'loan_type' => $loan_type,
            'loan_amount' => $loan_amount,
            'total_repayable_amount' => $total_repayable_amount,
            'monthly_installment' => $monthly_installment,
            'processing_fee' => $processing_fee,
            'status' => 'pending'
        ]
    ]);
} else {
    $err = $stmt->error;
    $stmt->close();
    send_api_json_response(['status' => 'error', 'message' => 'Database insertion error: ' . $err], 500);
}
