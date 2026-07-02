<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];

// 2. Fetch Key Metrics for the logged-in agent
// Customer & Loan Counts
$total_customers_result = $conn->query("SELECT COUNT(id) as total FROM customers WHERE agent_id = $agent_id");
$total_customers = $total_customers_result->fetch_assoc()['total'] ?? 0;
$total_loans_result = $conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id");
$total_loans = $total_loans_result->fetch_assoc()['total'] ?? 0;

// Loan Collections
$total_loan_collections_result = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE collected_by_agent_id = $agent_id");
$total_loan_collections = $total_loan_collections_result->fetch_assoc()['total'] ?? 0;

// RD Metrics
$active_rds_result = $conn->query("SELECT COUNT(id) as total FROM recurring_deposits WHERE agent_id = $agent_id AND status = 'active'");
$my_active_rds = $active_rds_result->fetch_assoc()['total'] ?? 0;
$total_rd_collections_result = $conn->query("SELECT SUM(amount_paid) as total FROM rd_payments WHERE collected_by_agent_id = $agent_id");
$my_rd_collections = $total_rd_collections_result->fetch_assoc()['total'] ?? 0;

// Wallet Balance
$wallet_balance_result = $conn->query("SELECT balance FROM agent_wallets WHERE agent_id = $agent_id");
$wallet_balance = $wallet_balance_result->fetch_assoc()['balance'] ?? 0;
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
                                <h5>My Reports</h5>
                            </div>
                        </div>

                        <div class="col-12">
                             <h5 class="mb-3">My Performance Snapshot</h5>
                            <div class="row g-3">
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My Loans Processed</h6><h2><?php echo $total_loans; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My Active RDs</h6><h2><?php echo $my_active_rds; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My Loan Collections</h6><h2>₹<?php echo number_format($total_loan_collections); ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My RD Collections</h6><h2 class="text-success">₹<?php echo number_format($my_rd_collections); ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100"><h6 class="text-muted">My Wallet Balance</h6><h2 class="text-info">₹<?php echo number_format($wallet_balance); ?></h2></div></div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">My Detailed Reports</h5>
                                    <div class="list-group">
                                        <a href="all-loans.php" class="list-group-item list-group-item-action"><h6 class="mb-1">My Loan Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">View and filter all loans you have created.</p></a>
                                        <a href="report-my-collections.php" class="list-group-item list-group-item-action"><h6 class="mb-1">My Loan Collection Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">Track all EMI payments you have collected.</p></a>
                                        <a href="all-rds.php" class="list-group-item list-group-item-action"><h6 class="mb-1">My RD Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">View and filter all RDs you have created.</p></a>
                                        <a href="report-my-rd-collections.php" class="list-group-item list-group-item-action"><h6 class="mb-1">My RD Collection Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">Track all RD installments you have collected.</p></a>
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