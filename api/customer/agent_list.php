<?php
// Include database configuration & API helpers
require_once('config.php');

$response = ['status' => 'success', 'data' => []];

$sql = "SELECT id, first_name, last_name FROM agents WHERE is_active = 1 OR status = 'active' ORDER BY first_name ASC, last_name ASC";
$result = @$conn->query($sql);
if (!$result) {
    $sql = "SELECT id, first_name, last_name FROM agents ORDER BY first_name ASC, last_name ASC";
    $result = $conn->query($sql);
}

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['full_name'] = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        unset($row['first_name']);
        unset($row['last_name']);
        $response['data'][] = $row;
    }
} else {
    $response['message'] = 'No active agents found.';
}

send_api_json_response($response, 200, $conn);
?>