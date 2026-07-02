<?php
// Include config and check for admin login
include('config.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

// Check if an agent ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-agents.php");
    exit();
}

$agent_id = $_GET['id'];
$message = '';

// --- STEP 1: Check for a success message from the previous redirect ---
// This is part of the Post-Redirect-Get pattern.
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Clear the message so it doesn't show again on refresh.
}

// --- Handle Wallet Recharge Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['recharge_amount'])) {
    $recharge_amount = (float)$_POST['recharge_amount'];
    $description = $conn->real_escape_string($_POST['description']);

    if ($recharge_amount > 0) {
        // Use a transaction to ensure both queries succeed or neither do $created_at
        $conn->begin_transaction();
        try {
            // 1. Update the agent's wallet balance
            $update_sql = "UPDATE agent_wallets SET balance = balance + ?, last_updated = ? WHERE agent_id = ?";
            $stmt_update = $conn->prepare($update_sql);
            $stmt_update->bind_param("dsi", $recharge_amount,$created_at, $agent_id);
            $stmt_update->execute();

            // 2. Log the transaction
            $log_sql = "INSERT INTO wallet_transactions (agent_id, transaction_type, amount, description, transaction_date) VALUES (?, 'recharge', ?, ?, ?)";
            $stmt_log = $conn->prepare($log_sql);
            $stmt_log->bind_param("idss", $agent_id, $recharge_amount, $description, $created_at);
            $stmt_log->execute();
            
            // If both were successful, commit the transaction
            $conn->commit();

            // --- STEP 2: Redirect after successful submission ---
            // Set a "flash message" in the session to be displayed after the redirect.
            $_SESSION['message'] = "<div class='alert alert-success'>Wallet recharged successfully!</div>";

            // Redirect to the same page (with the agent's ID) using a GET request.
            header("Location: agent-wallet.php?id=" . $agent_id);
            exit(); // ALWAYS exit after a header redirect to stop script execution.

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $message = "<div class='alert alert-danger'>Error: Failed to recharge wallet.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please enter a valid amount.</div>";
    }
}

// --- Fetch Agent Details, Wallet Balance, and Transactions ---
// (This part of the code remains exactly the same)
$agent_details = null;
$wallet_balance = 0;
$transactions = [];

$agent_stmt = $conn->prepare("SELECT first_name, last_name FROM agents WHERE id = ?");
$agent_stmt->bind_param("i", $agent_id);
$agent_stmt->execute();
$agent_result = $agent_stmt->get_result();
if ($agent_result->num_rows > 0) {
    $agent_details = $agent_result->fetch_assoc();
} else {
    header("Location: all-agents.php");
    exit();
}

$wallet_stmt = $conn->prepare("SELECT balance FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->bind_param("i", $agent_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
if ($wallet_result->num_rows > 0) {
    $wallet_balance = $wallet_result->fetch_assoc()['balance'];
}

$trans_stmt = $conn->prepare("SELECT transaction_type, amount, description, transaction_date FROM wallet_transactions WHERE agent_id = ? ORDER BY transaction_date DESC");
$trans_stmt->bind_param("i", $agent_id);
$trans_stmt->execute();
$trans_result = $trans_stmt->get_result();
if ($trans_result->num_rows > 0) {
    while ($row = $trans_result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include('head.php'); ?>
<body>
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <?php include('header.php'); ?>
        <div class="page-body-wrapper">
            <?php include('sidebaar.php'); ?>
            <div class="page-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Wallet for <?php echo htmlspecialchars($agent_details['first_name'] . ' ' . $agent_details['last_name']); ?></h5>
                                    </div>
                                    <div class="wallet-balance text-center">
                                        <h6 class="text-muted">Current Balance</h6>
                                        <h2>₹<?php echo number_format($wallet_balance, 2); ?></h2>
                                    </div>
                                    <hr>
                                    <h5>Recharge Wallet</h5>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form method="POST" action="agent-wallet.php?id=<?php echo $agent_id; ?>">
                                        <div class="mb-3">
                                            <label for="recharge_amount" class="form-label">Amount (₹)</label>
                                            <input type="number" step="0.01" class="form-control" name="recharge_amount" id="recharge_amount" placeholder="e.g., 500.00" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description (Optional)</label>
                                            <input type="text" class="form-control" name="description" id="description" placeholder="e.g., Monthly bonus">
                                        </div>
                                        <button type="submit" class="btn btn-primary w-100">Add Funds</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Transaction History</h5>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Amount (₹)</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($transactions)) : ?>
                                                    <tr><td colspan="4" class="text-center">No transactions found.</td></tr>
                                                <?php else : ?>
                                                    <?php foreach ($transactions as $trans) : ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y, h:i A', strtotime($trans['transaction_date'])); ?></td>
                                                            <td><span class="badge bg-<?php echo ($trans['transaction_type'] == 'recharge') ? 'success' : 'danger'; ?>"><?php echo ucfirst($trans['transaction_type']); ?></span></td>
                                                            <td><?php echo number_format($trans['amount'], 2); ?></td>
                                                            <td><?php echo htmlspecialchars($trans['description']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</body>
</html>