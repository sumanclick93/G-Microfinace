<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $rd_id = (int)$_GET['id'];

        $sql_rd = "SELECT
                     rd.id, rd.deposit_amount, rd.repayment_cycle, rd.tenure,
                     rd.interest_rate, rd.maturity_amount, rd.start_date,
                     rd.maturity_date, rd.status, rd.approval_date, rd.notes as admin_notes
                   FROM recurring_deposits rd
                   WHERE rd.id = ? AND rd.customer_id = ?";

        $stmt_rd = $conn->prepare($sql_rd);
        $stmt_rd->bind_param("ii", $rd_id, $customer_id);

        if ($stmt_rd->execute()) {
            $result_rd = $stmt_rd->get_result();

            if ($result_rd->num_rows === 1) {
                $rd_details = $result_rd->fetch_assoc();

                $payments = [];
                $total_paid = 0;
                $installments_paid_count = 0;
                
                $sql_payments = "SELECT payment_date, amount_paid, notes, status, proof_image 
                                 FROM rd_payments 
                                 WHERE rd_id = ? 
                                 ORDER BY payment_date DESC";
                                 
                $stmt_payments = $conn->prepare($sql_payments);
                $stmt_payments->bind_param("i", $rd_id);

                if ($stmt_payments->execute()) {
                    $result_payments = $stmt_payments->get_result();

                    while ($row = $result_payments->fetch_assoc()) {
                        $row['amount_paid'] = (float)$row['amount_paid'];
                        
                        if (!empty($row['proof_image'])) {
                            $row['proof_url'] = 'uploads/proofs/' . $row['proof_image'];
                        } else {
                            $row['proof_url'] = null;
                        }
                        
                        $payments[] = $row;

                        if ($row['status'] === 'approved') {
                            $total_paid += $row['amount_paid'];
                            $installments_paid_count++;
                        }
                    }
                    $stmt_payments->close();

                    $response = [
                        'status' => 'success',
                        'data' => [
                            'id' => $rd_details['id'],
                            'deposit_amount' => (float)$rd_details['deposit_amount'],
                            'repayment_cycle' => $rd_details['repayment_cycle'],
                            'total_emi' => (int)$rd_details['tenure'],
                            'no_of_paid_emi' => $installments_paid_count,
                            'tenure_description' => $rd_details['tenure'] . ' ' . ucfirst($rd_details['repayment_cycle']) . ' Deposits',
                            'interest_rate' => (float)$rd_details['interest_rate'],
                            'maturity_amount' => (float)$rd_details['maturity_amount'],
                            'start_date' => $rd_details['start_date'],
                            'maturity_date' => $rd_details['maturity_date'],
                            'status' => $rd_details['status'],
                            'status_formatted' => ucwords(str_replace('-', ' ', $rd_details['status'])),
                            'approval_date' => $rd_details['approval_date'],
                            'admin_notes' => $rd_details['admin_notes'],
                            'total_deposited' => round($total_paid, 2),
                            'payments' => $payments
                        ]
                    ];
                    $stmt_rd->close();
                    send_api_json_response($response, 200, $conn);

                } else {
                    $stmt_rd->close();
                    send_api_json_response(['status' => 'error', 'message' => 'Database error fetching deposit history: ' . $stmt_payments->error], 500, $conn);
                }
            } else {
                $stmt_rd->close();
                send_api_json_response(['status' => 'error', 'message' => 'Recurring Deposit not found or access denied.'], 404, $conn);
            }
        } else {
            $stmt_rd->close();
            send_api_json_response(['status' => 'error', 'message' => 'Database error fetching RD details: ' . $stmt_rd->error], 500, $conn);
        }

    } else {
        send_api_json_response(['status' => 'error', 'message' => 'Invalid or missing RD ID.'], 400, $conn);
    }

} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>