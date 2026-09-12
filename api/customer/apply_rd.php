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

// Session auth or optional POST customer_id fallback
$customer_id = null;
if (isset($_SESSION['customer_id'])) {
    $customer_id = (int) $_SESSION['customer_id'];
} elseif (isset($_POST['customer_id']) && is_numeric($_POST['customer_id'])) {
    $customer_id = (int) $_POST['customer_id'];
}

if ($customer_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $deposit_amount = isset($_POST['deposit_amount']) ? (float) $_POST['deposit_amount'] : 0;
        $repayment_cycle = isset($_POST['repayment_cycle']) ? strtolower(trim($_POST['repayment_cycle'])) : 'monthly';
        $tenure = isset($_POST['tenure']) ? (int) $_POST['tenure'] : 0;
        $interest_rate = isset($_POST['interest_rate']) ? (float) $_POST['interest_rate'] : 0;
        $start_date = isset($_POST['start_date']) && !empty($_POST['start_date']) ? trim($_POST['start_date']) : date('Y-m-d');

        $allowed_cycles = ['daily', 'weekly', 'monthly', 'quarterly', 'half-yearly', 'annually'];
        if (!in_array($repayment_cycle, $allowed_cycles)) {
            $repayment_cycle = 'monthly';
        }

        if ($deposit_amount <= 0 || $tenure <= 0 || $interest_rate < 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid RD parameters provided. Installment amount and tenure must be positive.']);
            exit();
        }

        // Fetch customer's assigned agent_id
        $agent_id = 0;
        $stmt_cust = $conn->prepare("SELECT agent_id FROM customers WHERE id = ?");
        $stmt_cust->bind_param("i", $customer_id);
        $stmt_cust->execute();
        $res_cust = $stmt_cust->get_result();
        if ($res_cust && $row_cust = $res_cust->fetch_assoc()) {
            $agent_id = (int) $row_cust['agent_id'];
        }
        $stmt_cust->close();

        if ($agent_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No active agent assigned to your customer account.']);
            exit();
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

        $time_in_years = $tenure / $cycles_per_year;
        $total_interest = $total_principal * ($interest_rate / 100) * $time_in_years;
        $maturity_amount = round($total_principal + $total_interest, 2);

        // Maturity Date calculation
        $maturity_date = null;
        try {
            $start_date_obj = new DateTime($start_date);
            $maturity_date_obj = $start_date_obj->add(new DateInterval($interval_string));
            $maturity_date = $maturity_date_obj->format('Y-m-d');
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid start date or tenure format provided.']);
            exit();
        }

        $sql = "INSERT INTO recurring_deposits (
                    customer_id, agent_id, deposit_amount, repayment_cycle,
                    tenure, interest_rate, maturity_amount, start_date,
                    maturity_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database prepare error: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param(
            "iidsiddss",
            $customer_id,
            $agent_id,
            $deposit_amount,
            $repayment_cycle,
            $tenure,
            $interest_rate,
            $maturity_amount,
            $start_date,
            $maturity_date
        );

        if ($stmt->execute()) {
            $new_rd_id = $stmt->insert_id;
            $response['status'] = 'success';
            $response['message'] = 'Recurring Deposit application submitted successfully.';
            $response['data'] = [
                'rd_id' => $new_rd_id,
                'customer_id' => $customer_id,
                'agent_id' => $agent_id,
                'deposit_amount' => $deposit_amount,
                'repayment_cycle' => $repayment_cycle,
                'tenure' => $tenure,
                'interest_rate' => $interest_rate,
                'total_principal' => $total_principal,
                'total_interest' => round($total_interest, 2),
                'maturity_amount' => $maturity_amount,
                'start_date' => $start_date,
                'maturity_date' => $maturity_date,
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