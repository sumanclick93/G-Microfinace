<?php
// --- START DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// --- END DEBUGGING ---

// Set header for JSON response
header('Content-Type: application/json');

// --- 1. Include Configuration ---
$configPath = 'config.php';
if (!file_exists($configPath)) {
    http_response_code(500); 
    echo json_encode(['status' => 'error', 'message' => 'Server configuration error: Config file not found at ' . $configPath]);
    exit();
}
include($configPath);

if (!isset($conn) || !$conn instanceof mysqli) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Check config.php.']);
    exit();
}

// --- 2. Start Session & Authentication ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$response = ['status' => 'error', 'message' => 'Authentication required.'];

if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 3. Prepare Query (MODIFIED) ---
    // Re-added l.repayment_cycle to the SELECT statement
    $sql = "SELECT
                l.id,
                l.loan_amount,
                l.total_repayable_amount,
                l.status,
                l.application_date,
                l.tenure,
                l.repayment_cycle,
                COUNT(p.id) AS no_of_paid_emi
            FROM loans l
            LEFT JOIN payments p ON l.id = p.loan_id
            WHERE l.customer_id = ?
            GROUP BY l.id
            ORDER BY l.application_date DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        error_log("SQL Prepare Error in get_all_loans.php: " . $conn->error);
        $response['message'] = 'Database error preparing statement.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $stmt->bind_param("i", $customer_id);

    // --- 4. Execute and Fetch Data (MODIFIED) ---
    $loans = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format data
            $row['loan_amount'] = (float)$row['loan_amount'];
            $row['total_repayable_amount'] = (float)$row['total_repayable_amount'];
            
            // Add the new keys as requested
            $row['total_emi'] = (int)$row['tenure'];
            $row['no_of_paid_emi'] = (int)$row['no_of_paid_emi'];
            
            // Re-add tenure_description
            if (isset($row['tenure']) && isset($row['repayment_cycle'])) {
                 $row['tenure_description'] = $row['tenure'] . ' ' . ucfirst($row['repayment_cycle']) . ' Payments';
            } else {
                  $row['tenure_description'] = 'N/A';
            }

            // Clean up original columns
            unset($row['tenure']);
            unset($row['repayment_cycle']);
             
            $loans[] = $row;
        }

        $response['status'] = 'success';
        $response['data'] = $loans;
        unset($response['message']);

    } else {
        http_response_code(500);
        error_log("SQL Execute Error in get_all_loans.php: " . $stmt->error);
        $response['message'] = 'Database error fetching loans.';
    }
    $stmt->close();

} else {
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 5. Send JSON Response ---
echo json_encode($response);

// Close connection
$conn->close();
?>