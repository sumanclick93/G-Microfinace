<?php
// 1. Start the session to be able to access session variables.
session_start();

// 2. Unset all of the session variables.
$_SESSION = array();

// 3. Destroy the session completely.
session_destroy();

// 4. Redirect the user to the login page.
header("Location: index.php");

// 5. Ensure no further code is executed after the redirect.
exit();
?>