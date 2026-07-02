<?php
// Set the password you want to use
$myPassword = '789456';

// Generate the secure hash
$hashedPassword = password_hash($myPassword, PASSWORD_DEFAULT);

// Display the hash
echo 'Your password is: ' . $myPassword . '<br><br>';
echo 'Copy this hash into your database:<br>';
echo '<strong>' . $hashedPassword . '</strong>';
?>