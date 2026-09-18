<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'microfinance_fund');
if (!defined('DB_PASS')) define('DB_PASS', 'ys!bnLg0j.T[');
if (!defined('DB_NAME')) define('DB_NAME', 'microfinance_fund');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!function_exists('ensure_db_connection')) {
    function ensure_db_connection(&$conn) {
        $is_alive = false;
        if ($conn instanceof mysqli) {
            try {
                $is_alive = @$conn->ping();
            } catch (Throwable $e) {
                $is_alive = false;
            }
        }
        if (!$is_alive) {
            try {
                if ($conn instanceof mysqli) {
                    @$conn->close();
                }
            } catch (Throwable $e) {}
            
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        return $conn;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
