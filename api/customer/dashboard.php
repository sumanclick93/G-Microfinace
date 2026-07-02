<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration
include('../../Super/config.php');

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];
    $today = new DateTime(); // Today's date for comparison
    $today->setTime(0, 0, 0); // Set time to midnight for accurate date comparisons

    // --- 2. Fetch Dashboard Data ---
    try {
        // -- Loan Data --
        $active_loans_count = 0;
        $total_loan_repayable = 0; // This is the "total due"
        $total_loan_paid = 0;      // This is the "total paid"
        $next_loan_payments = [];
        $active_loan_ids = [];

        $stmt_loans = $conn->prepare("SELECT id, total_repayable_amount, monthly_installment, approval_date, repayment_cycle, tenure FROM loans WHERE customer_id = ? AND status = 'active'");
        $stmt_loans->bind_param("i", $customer_id);
        $stmt_loans->execute();
        $result_loans = $stmt_loans->get_result();

        while ($loan = $result_loans->fetch_assoc()) {
            $active_loans_count++;
            $total_loan_repayable += $loan['total_repayable_amount']; // Add to total due
            $active_loan_ids[] = $loan['id'];

            // --- Calculate next theoretical due date for THIS loan ---
            $start_date_string = isset($loan['approval_date']) ? $loan['approval_date'] : date('Y-m-d');
            try {
                $start_date = new DateTime($start_date_string);
                $start_date->setTime(0, 0, 0); // Normalize time
                $interval_string = 'P1M'; // Default
                switch ($loan['repayment_cycle']) {
                    case 'daily': $interval_string = 'P1D'; break;
                    case 'weekly': $interval_string = 'P1W'; break;
                    case 'monthly': $interval_string = 'P1M'; break;
                    case 'quarterly': $interval_string = 'P3M'; break;
                    case 'half-yearly': $interval_string = 'P6M'; break;
                    case 'annually': $interval_string = 'P1Y'; break;
                }
                $interval = new DateInterval($interval_string);
                $current_due_date = clone $start_date;
                for ($i = 0; $i < $loan['tenure']; $i++) {
                    $current_due_date->add($interval); 
                    if ($current_due_date >= $today) {
                        $next_loan_payments[] = [
                            'loan_id' => $loan['id'],
                            'due_date' => $current_due_date->format('Y-m-d'),
                            'amount' => (float)$loan['monthly_installment']
                        ];
                        break; 
                    }
                }
            } catch (Exception $e) { /* ... error logging ... */ }
        }
        $stmt_loans->close();

        // Sort upcoming payments by date
        usort($next_loan_payments, function($a, $b) { /* ... sorting logic ... */ return ($a['due_date'] < $b['due_date']) ? -1 : 1; });

        // Get total paid for active loans
        if (!empty($active_loan_ids)) {
             $placeholders = implode(',', array_fill(0, count($active_loan_ids), '?'));
             $types = str_repeat('i', count($active_loan_ids));
             $stmt_loan_payments = $conn->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE loan_id IN ($placeholders)");
             if ($stmt_loan_payments) {
                 $stmt_loan_payments->bind_param($types, ...$active_loan_ids);
                 if ($stmt_loan_payments->execute()) {
                     $total_loan_paid = $stmt_loan_payments->get_result()->fetch_assoc()['total'] ?? 0;
                 }
                 $stmt_loan_payments->close();
             }
        }
        $total_outstanding_loan = max(0, $total_loan_repayable - $total_loan_paid);

        // -- RD Data --
        $active_rds_count = 0;
        $total_rd_deposited = 0;     // This is the "total paid"
        $total_rd_principal_due = 0; // This is the "total due"
        $next_rd_payments = [];
        $active_rd_ids = [];

        $stmt_rds = $conn->prepare("SELECT id, start_date, repayment_cycle, tenure, deposit_amount FROM recurring_deposits WHERE customer_id = ? AND status = 'active'");
        $stmt_rds->bind_param("i", $customer_id);
        $stmt_rds->execute();
        $result_rds = $stmt_rds->get_result();
        
        while ($rd = $result_rds->fetch_assoc()) {
            $active_rds_count++;
            $active_rd_ids[] = $rd['id'];
            // ## ADDED: Calculate total principal due for all active RDs ##
            $total_rd_principal_due += ((float)$rd['deposit_amount'] * (int)$rd['tenure']);

             // --- Calculate next theoretical due date for this RD ---
             $start_date_string = isset($rd['start_date']) ? $rd['start_date'] : date('Y-m-d');
             try {
                $start_date = new DateTime($start_date_string);
                $start_date->setTime(0,0,0); // Normalize time
                $interval_string = 'P1M'; // Default
                switch ($rd['repayment_cycle']) {
                    case 'daily': $interval_string = 'P1D'; break;
                    case 'weekly': $interval_string = 'P1W'; break;
                    case 'monthly': $interval_string = 'P1M'; break;
                    case 'quarterly': $interval_string = 'P3M'; break;
                    case 'half-yearly': $interval_string = 'P6M'; break;
                    case 'annually': $interval_string = 'P1Y'; break;
                }
                $interval = new DateInterval($interval_string);
                $current_due_date = clone $start_date;
                for ($i = 0; $i < $rd['tenure']; $i++) {
                     $current_due_date->add($interval);
                     if ($current_due_date >= $today) {
                         $next_rd_payments[] = [
                             'rd_id' => $rd['id'],
                             'due_date' => $current_due_date->format('Y-m-d'),
                             'amount' => (float)$rd['deposit_amount']
                         ];
                         break;
                     }
                }
             } catch (Exception $e) { /* ... error logging ... */ }
        }
        $stmt_rds->close();

        // Sort upcoming RD payments by date
        usort($next_rd_payments, function($a, $b) { /* ... sorting logic ... */ return ($a['due_date'] < $b['due_date']) ? -1 : 1; });

        // Get total paid for active RDs
        if (!empty($active_rd_ids)) {
             $placeholders = implode(',', array_fill(0, count($active_rd_ids), '?'));
             $types = str_repeat('i', count($active_rd_ids));
             $stmt_rd_payments = $conn->prepare("SELECT SUM(amount_paid) as total FROM rd_payments WHERE rd_id IN ($placeholders)");
             if ($stmt_rd_payments){
                 $stmt_rd_payments->bind_param($types, ...$active_rd_ids);
                 if ($stmt_rd_payments->execute()) {
                     $total_rd_deposited = $stmt_rd_payments->get_result()->fetch_assoc()['total'] ?? 0;
                 }
                 $stmt_rd_payments->close();
             }
        }

        // --- 3. Format Success Response ---
        $response['status'] = 'success';
        $response['data'] = [
            'loan_summary' => [
                'active_count' => $active_loans_count,
                'total_repayable' => round($total_loan_repayable, 2),
                'total_paid' => round($total_loan_paid, 2),
                'total_outstanding' => round($total_outstanding_loan, 2)
            ],
            'next_loan_payments' => $next_loan_payments,
            'rd_summary' => [
                'active_count' => $active_rds_count,
                'total_principal_due' => round($total_rd_principal_due, 2),
                'total_deposited' => round($total_rd_deposited, 2)
            ],
            'next_rd_payments' => $next_rd_payments
        ];
        unset($response['message']);

    } catch (Exception $e) {
        http_response_code(500);
        error_log("Error in dashboard API: " . $e->getMessage());
        $response['message'] = 'Error fetching dashboard data.';
    }

} else {
    http_response_code(401);
    $response['message'] = 'Authentication required. Please login.';
}

// --- 4. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>