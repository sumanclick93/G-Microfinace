<?php
// Set header for JSON response
header('Content-Type: application/json');

// --- START DEBUGGING ---
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// --- END DEBUGGING ---

// Include the database configuration (Using the correct relative path)
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

// Response array
$response = ['status' => 'error', 'message' => 'Authentication required.'];

// Start session to check login state
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- 1. Authentication Check ---
if (isset($_SESSION['customer_id'])) {
    $customer_id = $_SESSION['customer_id'];

    // --- 2. Prepare Query to fetch RDs for this customer (MODIFIED) ---
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
        http_response_code(500);
        error_log("SQL Prepare Error in get_all_rds.php: " . $conn->error);
        $response['message'] = 'Database error preparing statement.';
        echo json_encode($response);
        $conn->close();
        exit();
    }

    $stmt->bind_param("i", $customer_id);

    // --- 3. Execute and Fetch Data (MODIFIED) ---
    $rds = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format data as needed for the app
            $row['deposit_amount'] = (float)$row['deposit_amount'];
            $row['interest_rate'] = (float)$row['interest_rate'];
            $row['maturity_amount'] = (float)$row['maturity_amount'];
            
            // Add the new keys
            $row['total_emi'] = (int)$row['tenure'];
            $row['no_of_paid_emi'] = (int)$row['no_of_paid_emi'];
            
            // Keep tenure_description
            $row['tenure_description'] = $row['tenure'] . ' ' . ucfirst($row['repayment_cycle']) . ' Deposits';
            $row['status_formatted'] = ucwords(str_replace('-', ' ', $row['status']));

            // ** MODIFICATION: Only unset tenure, keep repayment_cycle **
            unset($row['tenure']);

            $rds[] = $row;
        }

        $response['status'] = 'success';
        $response['data'] = $rds;
        unset($response['message']); // Remove default error message

    } else {
        http_response_code(500);
        error_log("SQL Execute Error in get_all_rds.php: " . $stmt->error);
        $response['message'] = 'Database error fetching recurring deposits: ' . $stmt->error;
    }
    $stmt->close();

} else {
    // Session ID not found, user is not logged in
     http_response_code(401); // Unauthorized status code
     $response['message'] = 'Authentication required. Please login.';
}

// --- 4. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>