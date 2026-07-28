<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['collection_agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id   = intval($_SESSION['collection_agent_id']);
$agent_name = $_SESSION['collection_agent_name'] ?? 'Agent';

$total_customers = ($conn->query("SELECT COUNT(id) as total FROM customers WHERE agent_id = $agent_id")->fetch_assoc()['total']) ?? 0;
$active_loans    = ($conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id AND status = 'active'")->fetch_assoc()['total']) ?? 0;
$active_rds      = ($conn->query("SELECT COUNT(id) as total FROM recurring_deposits WHERE agent_id = $agent_id AND status = 'active'")->fetch_assoc()['total']) ?? 0;

// Collection stats from collection mirror tables (same as Collection History)
$loan_collected = ($conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM loan_payments_collection WHERE collected_by_agent_id = $agent_id")->fetch_assoc()['total']) ?? 0;
$rd_collected   = ($conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM rd_payments_collection WHERE collected_by_agent_id = $agent_id")->fetch_assoc()['total']) ?? 0;
$total_collected = $loan_collected + $rd_collected;

$wallet_balance = ($conn->query("SELECT COALESCE(balance, 0) as balance FROM agent_wallets WHERE agent_id = $agent_id")->fetch_assoc()['balance']) ?? 0;

$today = date('Y-m-d');
$today_loan = ($conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM loan_payments_collection WHERE collected_by_agent_id = $agent_id AND DATE(payment_date) = '$today'")->fetch_assoc()['total']) ?? 0;
$today_rd   = ($conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM rd_payments_collection WHERE collected_by_agent_id = $agent_id AND DATE(payment_date) = '$today'")->fetch_assoc()['total']) ?? 0;
$today_total = $today_loan + $today_rd;

$recent_loan = $conn->query("
    SELECT lpc.amount_paid, lpc.payment_date, c.full_name, l.id as loan_id
    FROM loan_payments_collection lpc
    JOIN loans l ON lpc.loan_id = l.id
    JOIN customers c ON l.customer_id = c.id
    WHERE lpc.collected_by_agent_id = $agent_id
    ORDER BY lpc.payment_date DESC
    LIMIT 10
");

$recent_rd = $conn->query("
    SELECT rpc.amount_paid, rpc.payment_date, c.full_name, rd.id as rd_id
    FROM rd_payments_collection rpc
    JOIN recurring_deposits rd ON rpc.rd_id = rd.id
    JOIN customers c ON rd.customer_id = c.id
    WHERE rpc.collected_by_agent_id = $agent_id
    ORDER BY rpc.payment_date DESC
    LIMIT 10
");
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
                            <div class="title-header option-title">
                                <h5>Dashboard</h5>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="row g-3">
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">Today's Collection</h6>
                                        <h2 class="text-primary">₹<?php echo number_format($today_total); ?></h2>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">Total Collected</h6>
                                        <h2 class="text-success">₹<?php echo number_format($total_collected); ?></h2>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">Wallet Balance</h6>
                                        <h2 class="text-success">₹<?php echo number_format($wallet_balance); ?></h2>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">My Customers</h6>
                                        <h2><?php echo number_format($total_customers); ?></h2>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">Active Loans</h6>
                                        <h2><?php echo number_format($active_loans); ?></h2>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <div class="card card-body text-center h-100">
                                        <h6 class="text-muted">Active RDs</h6>
                                        <h2><?php echo number_format($active_rds); ?></h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Recent Loan Collections</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($recent_loan && $recent_loan->num_rows > 0): ?>
                                                    <?php while ($row = $recent_loan->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                        <td>₹<?php echo number_format($row['amount_paid'], 2); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="3" class="text-center text-muted">No loan collections yet.</td></tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Recent RD Collections</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($recent_rd && $recent_rd->num_rows > 0): ?>
                                                    <?php while ($row = $recent_rd->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                                        <td>₹<?php echo number_format($row['amount_paid'], 2); ?></td>
                                                        <td><?php echo date('d M Y', strtotime($row['payment_date'])); ?></td>
                                                    </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr><td colspan="3" class="text-center text-muted">No RD collections yet.</td></tr>
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
<?php $conn->close(); ?>
