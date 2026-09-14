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

$deposit_amount = isset($input_data['deposit_amount']) ? (float)$input_data['deposit_amount'] : 0;
$repayment_cycle = isset($input_data['repayment_cycle']) ? strtolower(trim($input_data['repayment_cycle'])) : 'monthly';
$tenure = isset($input_data['tenure']) ? (int)$input_data['tenure'] : 0;
$interest_rate = isset($input_data['interest_rate']) ? (float)$input_data['interest_rate'] : 0;
$start_date = isset($input_data['start_date']) && !empty($input_data['start_date']) ? trim($input_data['start_date']) : date('Y-m-d');

$allowed_cycles = ['daily', 'weekly', 'monthly', 'quarterly', 'half-yearly', 'annually'];
if (!in_array($repayment_cycle, $allowed_cycles)) {
    $repayment_cycle = 'monthly';
}

if ($deposit_amount <= 0 || $tenure <= 0 || $interest_rate < 0) {
    send_api_json_response([
        'status' => 'error',
        'message' => 'Invalid RD parameters provided. Installment amount and tenure must be positive.'
    ], 400);
}

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
    send_api_json_response(['status' => 'error', 'message' => 'No active agent assigned to your customer account.'], 400);
}

// Calculation logic
$total_principal = $deposit_amount * $tenure;

switch ($repayment_cycle) {
    case 'daily':
        $cycles_per_year = 365;
        $interval_string = "P{$tenure}D";
        break;
    case 'weekly':
        $cycles_per_year = 52;
        $interval_string = "P{$tenure}W";
        break;
    case 'monthly':
        $cycles_per_year = 12;
        $interval_string = "P{$tenure}M";
        break;
    case 'quarterly':
        $cycles_per_year = 4;
        $interval_string = "P" . ($tenure * 3) . "M";
        break;
    case 'half-yearly':
        $cycles_per_year = 2;
        $interval_string = "P" . ($tenure * 6) . "M";
        break;
    case 'annually':
        $cycles_per_year = 1;
        $interval_string = "P{$tenure}Y";
        break;
    default:
        $cycles_per_year = 12;
        $interval_string = "P{$tenure}M";
}

$start_date_obj = new DateTime($start_date);
$maturity_date_obj = clone $start_date_obj;
try {
    $maturity_date_obj->add(new DateInterval($interval_string));
} catch (Exception $e) {
    $maturity_date_obj->add(new DateInterval("P{$tenure}M"));
}
$maturity_date = $maturity_date_obj->format('Y-m-d');

$n = $tenure;
$R = $interest_rate / 100.0;
$total_interest = round(($deposit_amount * $n * ($n + 1) / 2.0) * ($R / $cycles_per_year), 2);
$maturity_amount = round($total_principal + $total_interest, 2);

$rd_number = 'RD-' . date('Ymd') . '-' . rand(1000, 9999);

$sql = "INSERT INTO recurring_deposits (
            rd_number, customer_id, agent_id, deposit_amount, 
            interest_rate, tenure, repayment_cycle, total_principal,
            total_interest, maturity_amount, start_date, maturity_date, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$stmt_insert = $conn->prepare($sql);
$stmt_insert->bind_param(
    "siiddissddss",
    $rd_number, $customer_id, $agent_id, $deposit_amount,
    $interest_rate, $tenure, $repayment_cycle, $total_principal,
    $total_interest, $maturity_amount, $start_date, $maturity_date
);

if ($stmt_insert->execute()) {
    $rd_id = $stmt_insert->insert_id;
    $stmt_insert->close();

    send_api_json_response([
        'status' => 'success',
        'message' => 'Recurring Deposit application submitted successfully and is pending approval.',
        'data' => [
            'rd_id' => $rd_id,
            'rd_number' => $rd_number,
            'installment_amount' => $deposit_amount,
            'repayment_cycle' => $repayment_cycle,
            'tenure_installments' => $tenure,
            'interest_rate' => $interest_rate,
            'total_principal' => $total_principal,
            'total_interest' => $total_interest,
            'maturity_amount' => $maturity_amount,
            'start_date' => $start_date,
            'maturity_date' => $maturity_date,
            'status' => 'pending'
        ]
    ]);
} else {
    $err = $stmt_insert->error;
    $stmt_insert->close();
    send_api_json_response(['status' => 'error', 'message' => 'Database error submitting RD application: ' . $err], 500);
}
?>