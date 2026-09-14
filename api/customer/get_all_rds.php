<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    $sql = "SELECT
                rd.id,
                rd.deposit_amount,
                rd.repayment_cycle,
                rd.tenure,
                rd.interest_rate,
                rd.maturity_amount,
                rd.start_date,
                rd.maturity_date,
                rd.status,
                COUNT(rdp.id) AS no_of_paid_emi
            FROM recurring_deposits rd
            LEFT JOIN rd_payments rdp ON rd.id = rdp.rd_id
            WHERE rd.customer_id = ?
            GROUP BY rd.id
            ORDER BY rd.start_date DESC";

    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log("SQL Prepare Error in get_all_rds.php: " . $conn->error);
        send_api_json_response(['status' => 'error', 'message' => 'Database error preparing statement.'], 500, $conn);
    }

    $stmt->bind_param("i", $customer_id);

    $rds = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['deposit_amount'] = (float)$row['deposit_amount'];
            $row['interest_rate'] = (float)$row['interest_rate'];
            $row['maturity_amount'] = (float)$row['maturity_amount'];
            $row['total_emi'] = (int)$row['tenure'];
            $row['no_of_paid_emi'] = (int)$row['no_of_paid_emi'];
            $row['tenure_description'] = $row['tenure'] . ' ' . ucfirst($row['repayment_cycle']) . ' Deposits';
            $row['status_formatted'] = ucwords(str_replace('-', ' ', $row['status']));
            unset($row['tenure']);

            $rds[] = $row;
        }

        $stmt->close();
        send_api_json_response(['status' => 'success', 'data' => $rds], 200, $conn);

    } else {
        error_log("SQL Execute Error in get_all_rds.php: " . $stmt->error);
        $stmt->close();
        send_api_json_response(['status' => 'error', 'message' => 'Database error fetching recurring deposits: ' . $stmt->error], 500, $conn);
    }

} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>