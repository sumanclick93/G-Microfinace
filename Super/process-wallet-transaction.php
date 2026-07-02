<?php
include('config.php');

// Security check (session is already started in config.php)
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Check if the form was submitted and at least one checkbox was selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_agents'])) {
    
    $selected_agents = $_POST['selected_agents'];
    $amounts = $_POST['amounts'];
    $types = $_POST['transaction_types'];

    // Loop through every agent that was checked
    foreach ($selected_agents as $agent_id) {
        $agent_id = intval($agent_id);
        $amount = floatval($amounts[$agent_id]);
        $type = $conn->real_escape_string($types[$agent_id]);

        // Only process if they actually typed an amount greater than 0
        if ($amount > 0) {
            
            if ($type === 'deposit') {
                $update_wallet = "UPDATE agent_wallets SET balance = balance + $amount WHERE agent_id = $agent_id";
                $description = "Admin Deposit";
                $db_type = 'recharge'; // Maps to your existing ENUM
            } else { 
                $update_wallet = "UPDATE agent_wallets SET balance = balance - $amount WHERE agent_id = $agent_id";
                $description = "Admin Withdrawal";
                $db_type = 'admin-withdraw'; // We will add this to your DB in step 2
            }
            
            // 1. Update Wallet Balance
            $conn->query($update_wallet);

            // 2. Insert Log (using the correct transaction_type column)
            $insert_log = "INSERT INTO wallet_transactions (agent_id, transaction_type, amount, description) 
                           VALUES ($agent_id, '$db_type', $amount, '$description')";
            $conn->query($insert_log);
        }
    }
    
    // Redirect back to the financials page
    header("Location: agent-financials.php");
    exit();
} else {
    // If they clicked submit without checking any boxes, send them back
    header("Location: agent-financials.php");
    exit();
}
?>