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
                        l.application_date, l.approval_date, l.notes as admin_notes
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

                    // --- 6. Format Success Response ---
                    $response['status'] = 'success';
                    $response['data'] = [
                        'id' => $loan_details['id'],
                        'loan_amount' => (float)$loan_details['loan_amount'],
                        'total_repayable_amount' => (float)$loan_details['total_repayable_amount'],
                        'monthly_installment' => (float)$loan_details['monthly_installment'],
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

                        // Includes the new status and proof_url for each transaction
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