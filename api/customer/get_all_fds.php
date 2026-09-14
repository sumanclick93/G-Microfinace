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

    $sql = "SELECT 
                fd.id,
                fd.fd_number,
                fd.deposit_amount,
                fd.interest_rate,
                fd.tenure,
                fd.payout_frequency,
                fd.total_interest,
                fd.maturity_amount,
                fd.start_date,
                fd.maturity_date,
                fd.status,
                fd.created_at
            FROM fixed_deposits fd
            WHERE fd.customer_id = ?
            ORDER BY fd.id DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $customer_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $fds = [];
            while ($row = $result->fetch_assoc()) {
                $row['deposit_amount'] = (float)$row['deposit_amount'];
                $row['interest_rate'] = (float)$row['interest_rate'];
                $row['tenure_months'] = (int)$row['tenure'];
                $row['total_interest'] = (float)$row['total_interest'];
                $row['maturity_amount'] = (float)$row['maturity_amount'];
                $row['status_formatted'] = ucwords(str_replace('_', ' ', $row['status']));
                $fds[] = $row;
            }

            $response = [
                'status' => 'success',
                'data' => $fds
            ];
        } else {
            http_response_code(500);
            $response['message'] = 'Database error fetching Fixed Deposits: ' . $stmt->error;
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
