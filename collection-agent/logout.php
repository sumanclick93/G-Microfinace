<?php
include('config.php');

unset($_SESSION['collection_agent_id']);
unset($_SESSION['collection_agent_username']);
unset($_SESSION['collection_agent_name']);

session_destroy();

header("Location: index.php");
exit();
?>
