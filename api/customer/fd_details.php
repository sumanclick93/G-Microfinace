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
    $fd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($fd_id <= 0) {
        http_response_code(400);
        $response['message'] = 'Missing or invalid FD ID parameter.';
        echo json_encode($response);
        exit();
    }

    $sql = "SELECT 
                fd.*,
                a.full_name as agent_name,
                a.phone_number as agent_phone
            FROM fixed_deposits fd
            LEFT JOIN agents a ON fd.agent_id = a.id
            WHERE fd.id = ? AND fd.customer_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $fd_id, $customer_id);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $fd = $res->fetch_assoc();

                $fd['deposit_amount'] = (float)$fd['deposit_amount'];
                $fd['interest_rate'] = (float)$fd['interest_rate'];
                $fd['tenure_months'] = (int)$fd['tenure'];
                $fd['total_interest'] = (float)$fd['total_interest'];
                $fd['maturity_amount'] = (float)$fd['maturity_amount'];
                $fd['status_formatted'] = ucwords(str_replace('_', ' ', $fd['status']));

                // Fetch Payouts Log
                $payouts = [];
                $stmt_p = $conn->prepare("SELECT id, payout_amount, payout_type, payout_date, payment_mode, remarks FROM fd_payouts WHERE fd_id = ? ORDER BY id DESC");
                $stmt_p->bind_param("i", $fd_id);
                $stmt_p->execute();
                $p_res = $stmt_p->get_result();
                while ($p_row = $p_res->fetch_assoc()) {
                    $p_row['payout_amount'] = (float)$p_row['payout_amount'];
                    $payouts[] = $p_row;
                }

                $fd['payouts'] = $payouts;

                $response = [
                    'status' => 'success',
                    'data' => $fd
                ];
            } else {
                http_response_code(404);
                $response['message'] = 'Fixed Deposit account not found.';
            }
        } else {
            http_response_code(500);
            $response['message'] = 'Database error fetching FD details: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        http_response_code(500);
        $response['message'] = 'Database query preparation error.';
    }
} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

echo json_encode($response);
$conn->close();
?>
