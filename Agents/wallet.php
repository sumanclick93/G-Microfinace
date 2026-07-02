<?php
// Include the config file from the Super admin directory
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];

// --- 2. Fetch Wallet Balance ---
$wallet_balance = 0;
$wallet_stmt = $conn->prepare("SELECT balance FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->bind_param("i", $agent_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
if ($wallet_result->num_rows > 0) {
    $wallet_balance = $wallet_result->fetch_assoc()['balance'];
}
$wallet_stmt->close();

// --- 3. Fetch Transaction History ---
$transactions = [];
$trans_stmt = $conn->prepare("SELECT transaction_type, amount, description, transaction_date FROM wallet_transactions WHERE agent_id = ? ORDER BY transaction_date DESC");
$trans_stmt->bind_param("i", $agent_id);
$trans_stmt->execute();
$trans_result = $trans_stmt->get_result();
if ($trans_result->num_rows > 0) {
    while ($row = $trans_result->fetch_assoc()) {
        $transactions[] = $row;
    }
}
$trans_stmt->close();
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

                        <div class="col-12">
                            <div class="card">
                                <div class="card-body text-center">
                                    <h6 class="text-muted mb-2">My Current Wallet Balance</h6>
                                    <h2 class="mb-0">₹<?php echo number_format($wallet_balance, 2); ?></h2>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>My Transaction History</h5>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table theme-table">
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
                                                    <tr>
                                                        <td colspan="4" class="text-center text-muted">You have no transactions yet.</td>
                                                    </tr>
                                                <?php else : ?>
                                                    <?php foreach ($transactions as $trans) : ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y, h:i A', strtotime($trans['transaction_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                    // Set badge color based on transaction type
                                                                    $badge_color = 'secondary';
                                                                    if ($trans['transaction_type'] == 'recharge' || $trans['transaction_type'] == 'emi-received') {
                                                                        $badge_color = 'success'; // Money In
                                                                    } elseif ($trans['transaction_type'] == 'loan-debit') {
                                                                        $badge_color = 'danger'; // Money Out
                                                                    }
                                                                ?>
                                                                <span class="badge bg-<?php echo $badge_color; ?>"><?php echo str_replace('-', ' ', ucfirst($trans['transaction_type'])); ?></span>
                                                            </td>
                                                            <td class="text-<?php echo $badge_color; ?>">
                                                                <?php echo number_format($trans['amount'], 2); ?>
                                                            </td>
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