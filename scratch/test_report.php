<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['admin_id'] = 1;

chdir(__DIR__ . '/../Super');
include('collection-report.php');
