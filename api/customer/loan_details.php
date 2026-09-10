<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (Corrected path)
include('config.php');

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 2. Get Loan ID from URL and Validate ---
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $loan_id = (int)$_GET['id'];

        // --- 3. Prepare Query to fetch loan details ---
        $sql_loan = "SELECT
                        l.id, l.loan_amount, l.total_repayable_amount, l.monthly_installment,
                        l.interest_rate, l.tenure, l.repayment_cycle, l.status,
                        l.application_date, l.approval_date, l.notes as admin_notes,
                        l.loan_type, l.interest_calculation_type, l.gold_weight_grams,
                        l.gold_photo_path, l.gold_rate_per_gram, l.processing_fee
                    FROM loans l
                    WHERE l.id = ? AND l.customer_id = ?";

        $stmt_loan = $conn->prepare($sql_loan);
        $stmt_loan->bind_param("ii", $loan_id, $customer_id);

        if ($stmt_loan->execute()) {
            $result_loan = $stmt_loan->get_result();

            if ($result_loan->num_rows === 1) {
                $loan_details = $result_loan->fetch_assoc();

                // --- 4. Fetch Payment History (MODIFIED to include status & proof) ---
                $payments = [];
                $total_paid = 0;
                $no_of_paid_emi = 0; // Only counts approved EMIs now

                // Fetching the new status and proof_image columns
                $sql_payments = "SELECT payment_date, amount_paid, notes, status, proof_image 
                                 FROM payments 
                                 WHERE loan_id = ? 
                                 ORDER BY payment_date DESC";
                                 
                $stmt_payments = $conn->prepare($sql_payments);
                $stmt_payments->bind_param("i", $loan_id);

                if ($stmt_payments->execute()) {
                    $result_payments = $stmt_payments->get_result();

                    while ($row = $result_payments->fetch_assoc()) {
                        $row['amount_paid'] = (float)$row['amount_paid'];
                        
                        // Helper for the App: Provide a clean URL path if a proof image exists
                        if (!empty($row['proof_image'])) {
                            $row['proof_url'] = 'uploads/proofs/' . $row['proof_image'];
                        } else {
                            $row['proof_url'] = null;
                        }
                        
                        $payments[] = $row;

                        // --- STRICT MATH LOGIC ---
                        // Only count the payment toward the balance if it is fully approved!
                        if ($row['status'] === 'approved') {
                            $total_paid += $row['amount_paid'];
                            $no_of_paid_emi++;
                        }
                    }
                    $stmt_payments->close();

                    // --- 5. Calculate Remaining Balance ---
                    $remaining_balance = max(0, (float)$loan_details['total_repayable_amount'] - $total_paid);

                    $gold_weight = !is_null($loan_details['gold_weight_grams']) ? (float)$loan_details['gold_weight_grams'] : null;
                    $gold_rate = !is_null($loan_details['gold_rate_per_gram']) ? (float)$loan_details['gold_rate_per_gram'] : null;
                    $gold_valuation = (!empty($gold_weight) && !empty($gold_rate)) ? round($gold_weight * $gold_rate, 2) : null;
                    $raw_dt_photo = $loan_details['gold_photo_path'] ?? '';
                    if (!empty($raw_dt_photo)) {
                        if (strpos($raw_dt_photo, 'http') === 0) {
                            $gold_photo_url = $raw_dt_photo;
                        } else {
                            $rel_dt = (strpos($raw_dt_photo, 'Agents/') === 0) ? $raw_dt_photo : 'Agents/' . ltrim($raw_dt_photo, '/');
                            $gold_photo_url = $rel_dt;
                            $disk_dt = __DIR__ . '/../../' . $rel_dt;
                            if (!file_exists($disk_dt)) {
                                if (strpos($rel_dt, 'Agents/upload/') === 0) {
                                    $alt_dt = 'Agents/uploads/' . substr($rel_dt, 14);
                                    if (file_exists(__DIR__ . '/../../' . $alt_dt)) $gold_photo_url = $alt_dt;
                                } elseif (strpos($rel_dt, 'Agents/uploads/') === 0) {
                                    $alt_dt = 'Agents/upload/' . substr($rel_dt, 15);
                                    if (file_exists(__DIR__ . '/../../' . $alt_dt)) $gold_photo_url = $alt_dt;
                                }
                            }
                        }
                    } else {
                        $gold_photo_url = null;
                    }

                    // --- 6. Format Success Response ---
                    $response['status'] = 'success';
                    $response['data'] = [
                        'id' => $loan_details['id'],
                        'loan_type' => $loan_details['loan_type'] ?? 'standard',
                        'interest_calculation_type' => $loan_details['interest_calculation_type'] ?? 'flat_total',
                        'loan_amount' => (float)$loan_details['loan_amount'],
                        'total_repayable_amount' => (float)$loan_details['total_repayable_amount'],
                        'monthly_installment' => (float)$loan_details['monthly_installment'],
                        'processing_fee' => (float)$loan_details['processing_fee'],
                        'gold_weight_grams' => $gold_weight,
                        'gold_rate_per_gram' => $gold_rate,
                        'gold_valuation' => $gold_valuation,
                        'gold_photo_url' => $gold_photo_url,
                        'interest_rate' => (float)$loan_details['interest_rate'],
                        'tenure_description' => $loan_details['tenure'] . ' ' . ucfirst($loan_details['repayment_cycle']) . ' Payments',
                        'status' => $loan_details['status'],
                        'application_date' => $loan_details['application_date'],
                        'approval_date' => $loan_details['approval_date'],
                        'admin_notes' => $loan_details['admin_notes'],
                        
                        // Strict balances based ONLY on approved payments
                        'total_paid' => round($total_paid, 2),
                        'remaining_balance' => round($remaining_balance, 2),
                        'total_emi' => (int)$loan_details['tenure'],
                        'no_of_paid_emi' => $no_of_paid_emi,

                        // Includes the status and proof_url for each transaction
                        'payments' => $payments
                    ];
                    unset($response['message']);

                } else {
                    $response['message'] = 'Database error fetching payment history: ' . $stmt_payments->error;
                }
            } else {
                http_response_code(404); // Not Found
                $response['message'] = 'Loan not found or access denied.';
            }
        } else {
            $response['message'] = 'Database error fetching loan details: ' . $stmt_loan->error;
        }
        $stmt_loan->close();

    } else {
        http_response_code(400); // Bad Request
        $response['message'] = 'Invalid or missing loan ID.';
    }

} else {
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 7. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>