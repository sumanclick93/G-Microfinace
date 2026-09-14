<?php
// Include database configuration & API helpers
require_once('config.php');

$customer_id = get_current_customer_id();

if ($customer_id) {
    $support_data = [
        'agent' => null,
        'admin' => null
    ];

    $sql_agent = "SELECT a.first_name, a.last_name, a.phone 
                  FROM agents a
                  JOIN customers c ON a.id = c.agent_id
                  WHERE c.id = ?";
    $stmt_agent = $conn->prepare($sql_agent);
    $stmt_agent->bind_param("i", $customer_id);
    if ($stmt_agent->execute()) {
        $result_agent = $stmt_agent->get_result();
        if ($result_agent->num_rows > 0) {
            $agent = $result_agent->fetch_assoc();
            $support_data['agent'] = [
                'name' => trim(($agent['first_name'] ?? '') . ' ' . ($agent['last_name'] ?? '')),
                'phone' => $agent['phone'] ?? null
            ];
        }
    }
    $stmt_agent->close();

    $sql_admin = "SELECT first_name, last_name, phone FROM admins LIMIT 1";
    $result_admin = $conn->query($sql_admin);
    if ($result_admin && $result_admin->num_rows > 0) {
        $admin = $result_admin->fetch_assoc();
        $support_data['admin'] = [
            'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? '')),
            'phone' => $admin['phone'] ?? null
        ];
    }
    
    send_api_json_response(['status' => 'success', 'data' => $support_data], 200, $conn);

} else {
    send_api_json_response(['status' => 'error', 'message' => 'Authentication required. Please login.'], 401, $conn);
}
?>