<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
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

            $stmt->close();
            send_api_json_response(['status' => 'success', 'data' => $fds], 200, $conn);
        } else {
            $stmt->close();
            send_api_json_response(['status' => 'error', 'message' => 'Database error fetching Fixed Deposits: ' . $stmt->error], 500, $conn);
        }
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'Database query preparation error.'], 500, $conn);
    }
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>
