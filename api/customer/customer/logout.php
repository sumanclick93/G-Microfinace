<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);
unset($_SESSION['customer_login_id']);

session_destroy();

send_api_json_response([
    'status' => 'success',
    'message' => 'Logout successful.'
]);
?>