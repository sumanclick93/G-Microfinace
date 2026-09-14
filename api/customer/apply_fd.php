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

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['customer_id'])) {
    $customer_id = (int)$_SESSION['customer_id'];

    // Get input (JSON or POST)
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $deposit_amount = isset($input['deposit_amount']) ? (float)$input['deposit_amount'] : 0;
    $interest_rate = isset($input['interest_rate']) ? (float)$input['interest_rate'] : 0;
    $tenure = isset($input['tenure']) ? (int)$input['tenure'] : 0; // Months
    $payout_frequency = isset($input['payout_frequency']) ? $input['payout_frequency'] : 'on_maturity';
    $start_date = isset($input['start_date']) ? $input['start_date'] : date('Y-m-d');

    // Fetch customer's agent ID
    $agent_id = NULL;
    $agent_stmt = $conn->prepare("SELECT agent_id FROM customers WHERE id = ?");
    $agent_stmt->bind_param("i", $customer_id);
    $agent_stmt->execute();
    $res_agent = $agent_stmt->get_result();
    if ($row_a = $res_agent->fetch_assoc()) {
        $agent_id = $row_a['agent_id'];
    }

    if ($deposit_amount <= 0 || $tenure <= 0 || $interest_rate <= 0 || empty($start_date)) {
        http_response_code(400);
        $response['message'] = 'Invalid parameters. Please specify deposit_amount, interest_rate, tenure, and start_date.';
    } else {
        $time_in_years = $tenure / 12.0;
        $total_interest = round($deposit_amount * ($interest_rate / 100.0) * $time_in_years, 2);
        $maturity_amount = round($deposit_amount + $total_interest, 2);

        $start_date_obj = new DateTime($start_date);
        $maturity_date_obj = clone $start_date_obj;
        $maturity_date_obj->add(new DateInterval("P{$tenure}M"));
        $maturity_date = $maturity_date_obj->format('Y-m-d');

        $fd_number = 'FD-' . date('Ymd') . '-' . rand(1000, 9999);

        $sql = "INSERT INTO fixed_deposits (
                    fd_number, customer_id, agent_id, deposit_amount, 
                    interest_rate, tenure, payout_frequency, total_interest, 
                    maturity_amount, start_date, maturity_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param(
            "siiddissdss",
            $fd_number, $customer_id, $agent_id, $deposit_amount,
            $interest_rate, $tenure, $payout_frequency, $total_interest,
            $maturity_amount, $start_date, $maturity_date
        );

        if ($stmt_insert->execute()) {
            $fd_id = $stmt_insert->insert_id;
            $response = [
                'status' => 'success',
                'message' => 'Fixed Deposit application submitted successfully and is pending approval.',
                'data' => [
                    'fd_id' => $fd_id,
                    'fd_number' => $fd_number,
                    'deposit_amount' => $deposit_amount,
                    'interest_rate' => $interest_rate,
                    'tenure_months' => $tenure,
                    'total_interest' => $total_interest,
                    'maturity_amount' => $maturity_amount,
                    'start_date' => $start_date,
                    'maturity_date' => $maturity_date,
                    'status' => 'pending'
                ]
            ];
        } else {
            http_response_code(500);
            $response['message'] = 'Database error submitting FD application: ' . $stmt_insert->error;
        }
    }
} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

echo json_encode($response);
$conn->close();
?>
