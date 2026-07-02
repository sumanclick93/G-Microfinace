<?php
// Set header for JSON response
header('Content-Type: application/json');

// Include the database configuration (adjust path as needed)
include('config.php'); // Assuming config is two levels up in Super folder

// Response array
$response = ['status' => 'success', 'data' => []];

// --- 1. Prepare and Execute Query ---
// Select only active agents, ordering by name for the dropdown
$sql = "SELECT id, first_name, last_name FROM agents WHERE is_active = TRUE ORDER BY first_name ASC, last_name ASC";
$result = $conn->query($sql);

// --- 2. Fetch Results ---
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Combine first and last name for display
        $row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
        unset($row['first_name']); // Remove individual name fields if not needed
        unset($row['last_name']);
        $response['data'][] = $row;
    }
} else {
    // Optional: Handle case where no active agents are found
    $response['message'] = 'No active agents found.';
}

// --- 3. Send JSON Response ---
echo json_encode($response);
$conn->close();
?>