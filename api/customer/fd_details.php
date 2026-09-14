<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    $fd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($fd_id <= 0) {
        send_api_json_response(['status' => 'error', 'message' => 'Missing or invalid FD ID parameter.'], 400, $conn);
    }

    $sql = "SELECT 
                fd.*,
                CONCAT(a.first_name, ' ', IFNULL(a.last_name, '')) as agent_name,
                a.phone as agent_phone
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
                $stmt_p->close();

                $fd['payouts'] = $payouts;

                $stmt->close();
                send_api_json_response(['status' => 'success', 'data' => $fd], 200, $conn);

            } else {
                $stmt->close();
                send_api_json_response(['status' => 'error', 'message' => 'Fixed Deposit account not found.'], 404, $conn);
            }
        } else {
            $err = $stmt->error;
            $stmt->close();
            send_api_json_response(['status' => 'error', 'message' => 'Database error fetching FD details: ' . $err], 500, $conn);
        }
    } else {
        send_api_json_response(['status' => 'error', 'message' => 'Database query preparation error.'], 500, $conn);
    }
} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>
