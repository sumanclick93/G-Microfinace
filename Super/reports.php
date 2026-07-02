<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Fetch Key Metrics for the entire system
// Loan Metrics
$total_disbursed_result = $conn->query("SELECT SUM(loan_amount) as total FROM loans WHERE status IN ('active', 'paid')");
$total_disbursed = $total_disbursed_result->fetch_assoc()['total'] ?? 0;
$total_collections_result = $conn->query("SELECT SUM(amount_paid) as total FROM payments");
$total_loan_collections = $total_collections_result->fetch_assoc()['total'] ?? 0;
$outstanding_loan_balance = $total_disbursed - $total_loan_collections;

// RD Metrics
$active_rds_result = $conn->query("SELECT COUNT(id) as total FROM recurring_deposits WHERE status = 'active'");
$total_active_rds = $active_rds_result->fetch_assoc()['total'] ?? 0;
$total_rd_collected_result = $conn->query("SELECT SUM(amount_paid) as total FROM rd_payments");
$total_rd_collections = $total_rd_collected_result->fetch_assoc()['total'] ?? 0;

// Agent & Customer Counts
$active_agents_result = $conn->query("SELECT COUNT(id) as total FROM agents WHERE is_active = TRUE");
$active_agents = $active_agents_result->fetch_assoc()['total'] ?? 0;
$total_customers_result = $conn->query("SELECT COUNT(id) as total FROM customers");
$total_customers = $total_customers_result->fetch_assoc()['total'] ?? 0;
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
                                <h5>Reports & Analytics</h5>
                            </div>
                        </div>

                        <div class="col-12">
                             <h5 class="mb-3">System Overview</h5>
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Total Loans Disbursed</h6><h2>₹<?php echo number_format($total_disbursed); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Total Loan Collections</h6><h2>₹<?php echo number_format($total_loan_collections); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Total RD Collections</h6><h2 class="text-success">₹<?php echo number_format($total_rd_collections); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Active RDs</h6><h2><?php echo $total_active_rds; ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Outstanding Loan Balance</h6><h2 class="text-danger">₹<?php echo number_format($outstanding_loan_balance); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Active Agents</h6><h2><?php echo $active_agents; ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                            </div>
                        </div>

                        <div class="col-12 mt-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Generate Detailed Reports</h5>
                                    <div class="list-group">
                                        <a href="all-loans.php" class="list-group-item list-group-item-action"><h6 class="mb-1">Loan Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">View and filter all loan applications.</p></a>
                                        <a href="report-collections.php" class="list-group-item list-group-item-action"><h6 class="mb-1">Loan Collection Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">Track EMI collections by date range and agent.</p></a>
                                        <a href="all-rds.php" class="list-group-item list-group-item-action"><h6 class="mb-1">RD Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">View and filter all recurring deposit accounts.</p></a>
                                        <a href="report-rd-collections.php" class="list-group-item list-group-item-action"><h6 class="mb-1">RD Collection Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">Track RD installments collected by date range and agent.</p></a>
                                        <a href="report-agent-performance.php" class="list-group-item list-group-item-action"><h6 class="mb-1">Agent Performance Report <i class="ri-arrow-right-s-line float-end"></i></h6><p class="mb-1 small">Rank agents based on loans, RDs, and collections.</p></a>
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