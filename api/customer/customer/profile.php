<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    $sql = "SELECT
                c.id, c.full_name, c.customer_id_string, c.phone, c.email, c.address, c.avatar,
                a.first_name as agent_first_name, a.last_name as agent_last_name, a.phone as agent_phone
            FROM customers c
            LEFT JOIN agents a ON c.agent_id = a.id
            WHERE c.id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $customer_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $customer_data = $result->fetch_assoc();

            $avatar_url = null;
            if (!empty($customer_data['avatar'])) {
                $avatar_url = $customer_data['avatar'];
            }

            $agent_name = trim(($customer_data['agent_first_name'] ?? '') . ' ' . ($customer_data['agent_last_name'] ?? ''));

            $response = [
                'status' => 'success',
                'data' => [
                    'id' => $customer_data['id'],
                    'full_name' => $customer_data['full_name'],
                    'customer_id_string' => $customer_data['customer_id_string'],
                    'phone' => $customer_data['phone'],
                    'email' => $customer_data['email'],
                    'address' => $customer_data['address'],
                    'avatar_url' => $avatar_url,
                    'agent' => [
                        'name' => !empty($agent_name) ? $agent_name : 'N/A',
                        'phone' => $customer_data['agent_phone'] ?? null
                    ]
                ]
            ];
            $stmt->close();
            send_api_json_response($response, 200, $conn);

        } else {
            $stmt->close();
            send_api_json_response(['status' => 'error', 'message' => 'Customer profile not found.'], 404, $conn);
        }
    } else {
        $err = $stmt->error;
        $stmt->close();
        send_api_json_response(['status' => 'error', 'message' => 'Database error fetching profile: ' . $err], 500, $conn);
    }

} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>