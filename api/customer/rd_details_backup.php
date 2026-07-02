<?php
// Set header for JSON response
header('Content-Type: application/json');

// --- START DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// --- END DEBUGGING ---

// Include the database configuration (Corrected path)
$configPath = '../../Super/config.php';
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

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 2. Get RD ID from URL and Validate ---
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $rd_id = (int)$_GET['id'];

        // --- 3. Prepare Query to fetch RD details ---
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

                // --- 4. Fetch Payment History for this RD ---
                $payments = [];
                $total_paid = 0;
                $installments_paid_count = 0;
                $sql_payments = "SELECT payment_date, amount_paid, notes FROM rd_payments WHERE rd_id = ? ORDER BY payment_date DESC";
                $stmt_payments = $conn->prepare($sql_payments);
                $stmt_payments->bind_param("i", $rd_id);

                if ($stmt_payments->execute()) {
                    $result_payments = $stmt_payments->get_result();
                    $installments_paid_count = $result_payments->num_rows; // Get the count

                    while ($row = $result_payments->fetch_assoc()) {
                        $row['amount_paid'] = (float)$row['amount_paid'];
                        $payments[] = $row;
                        $total_paid += $row['amount_paid'];
                    }
                    $stmt_payments->close();

                    // --- 5. Format Success Response (MODIFIED) ---
                    $response['status'] = 'success';
                    $response['data'] = [
                        'id' => $rd_details['id'],
                        'deposit_amount' => (float)$rd_details['deposit_amount'],
                        'repayment_cycle' => $rd_details['repayment_cycle'],
                        
                        // ## MODIFIED KEYS ##
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
                    ];
                    unset($response['message']);

                } else {
                    $response['message'] = 'Database error fetching deposit history: ' . $stmt_payments->error;
                }
            } else {
                http_response_code(404); // Not Found
                $response['message'] = 'Recurring Deposit not found or access denied.';
            }
        } else {
            $response['message'] = 'Database error fetching RD details: ' . $stmt_rd->error;
        }
        $stmt_rd->close();

    } else {
        http_response_code(400); // Bad Request
        $response['message'] = 'Invalid or missing RD ID.';
    }

} else {
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 6. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>