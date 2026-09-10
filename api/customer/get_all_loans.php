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
                l.loan_type,
                l.interest_calculation_type,
                l.gold_weight_grams,
                l.gold_photo_path,
                l.gold_rate_per_gram,
                l.processing_fee,
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

    // --- 4. Execute and Fetch Data ---
    $loans = [];
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // Format data
            $row['loan_amount'] = (float)$row['loan_amount'];
            $row['total_repayable_amount'] = (float)$row['total_repayable_amount'];
            $row['loan_type'] = $row['loan_type'] ?? 'standard';
            $row['interest_calculation_type'] = $row['interest_calculation_type'] ?? 'flat_total';
            $row['gold_weight_grams'] = !is_null($row['gold_weight_grams']) ? (float)$row['gold_weight_grams'] : null;
            $row['gold_rate_per_gram'] = !is_null($row['gold_rate_per_gram']) ? (float)$row['gold_rate_per_gram'] : null;
            $row['processing_fee'] = (float)$row['processing_fee'];

            if (!empty($row['gold_weight_grams']) && !empty($row['gold_rate_per_gram'])) {
                $row['gold_valuation'] = round($row['gold_weight_grams'] * $row['gold_rate_per_gram'], 2);
            } else {
                $row['gold_valuation'] = null;
            }

            $raw_api_photo = $row['gold_photo_path'] ?? '';
            $gold_photo_urls = [];
            if (!empty($raw_api_photo)) {
                $raw_paths = explode(',', $raw_api_photo);
                foreach ($raw_paths as $raw_path_item) {
                    $raw_path_item = trim($raw_path_item);
                    if (empty($raw_path_item)) continue;

                    if (strpos($raw_path_item, 'http') === 0) {
                        $gold_photo_urls[] = $raw_path_item;
                    } else {
                        $rel_api = (strpos($raw_path_item, 'Agents/') === 0) ? $raw_path_item : 'Agents/' . ltrim($raw_path_item, '/');
                        $url_api = $rel_api;
                        $disk_p = __DIR__ . '/../../' . $rel_api;
                        if (!file_exists($disk_p)) {
                            if (strpos($rel_api, 'Agents/upload/') === 0) {
                                $alt_api = 'Agents/uploads/' . substr($rel_api, 14);
                                if (file_exists(__DIR__ . '/../../' . $alt_api)) $url_api = $alt_api;
                            } elseif (strpos($rel_api, 'Agents/uploads/') === 0) {
                                $alt_api = 'Agents/upload/' . substr($rel_api, 15);
                                if (file_exists(__DIR__ . '/../../' . $alt_api)) $url_api = $alt_api;
                            }
                        }
                        $gold_photo_urls[] = $url_api;
                    }
                }
            }
            $row['gold_photo_url'] = !empty($gold_photo_urls) ? $gold_photo_urls[0] : null;
            $row['gold_photo_urls'] = $gold_photo_urls;
            $row['gold_photos'] = $gold_photo_urls;
            unset($row['gold_photo_path']);

            // Add keys
            $row['total_emi'] = (int)$row['tenure'];
            $row['no_of_paid_emi'] = (int)$row['no_of_paid_emi'];
            
            // Tenure description
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