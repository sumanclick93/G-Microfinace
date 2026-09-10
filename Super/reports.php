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
// Loan Type Breakdown Metrics (Standard, Interest-Only, Gold Loan)
// Standard Loans
$std_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total FROM loans WHERE loan_type = 'standard' AND status IN ('active', 'paid')");
$std_disbursed = $std_disbursed_res->fetch_assoc()['total'] ?? 0;
$std_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE loan_type = 'standard' AND status = 'active'");
$std_active_count = $std_active_res->fetch_assoc()['total'] ?? 0;
$std_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE l.loan_type = 'standard' AND p.status != 'rejected'");
$std_collections = $std_coll_res->fetch_assoc()['total'] ?? 0;

// Interest-Only Loans
$int_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total FROM loans WHERE loan_type = 'interest_only' AND status IN ('active', 'paid')");
$int_disbursed = $int_disbursed_res->fetch_assoc()['total'] ?? 0;
$int_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE loan_type = 'interest_only' AND status = 'active'");
$int_active_count = $int_active_res->fetch_assoc()['total'] ?? 0;
$int_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE l.loan_type = 'interest_only' AND p.status != 'rejected'");
$int_collections = $int_coll_res->fetch_assoc()['total'] ?? 0;

// Gold Loans
$gold_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total, COALESCE(SUM(gold_weight_grams),0) as total_weight FROM loans WHERE loan_type = 'gold' AND status IN ('active', 'paid')");
$gold_row = $gold_disbursed_res->fetch_assoc();
$gold_disbursed = $gold_row['total'] ?? 0;
$gold_weight = $gold_row['total_weight'] ?? 0;
$gold_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE loan_type = 'gold' AND status = 'active'");
$gold_active_count = $gold_active_res->fetch_assoc()['total'] ?? 0;
$gold_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE l.loan_type = 'gold' AND p.status != 'rejected'");
$gold_collections = $gold_coll_res->fetch_assoc()['total'] ?? 0;
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

                        <div class="col-12 mb-4">
                             <h5 class="mb-3 fw-bold text-dark"><i class="ri-dashboard-3-line me-2"></i>System Overview</h5>
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Total Loans Disbursed</h6><h2>₹<?php echo number_format($total_disbursed); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Total Loan Collections</h6><h2>₹<?php echo number_format($total_loan_collections); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Total RD Collections</h6><h2 class="text-success">₹<?php echo number_format($total_rd_collections); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Active RDs</h6><h2><?php echo $total_active_rds; ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Outstanding Loan Balance</h6><h2 class="text-danger">₹<?php echo number_format($outstanding_loan_balance); ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Active Agents</h6><h2><?php echo $active_agents; ?></h2></div></div>
                                <div class="col-lg-3 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                            </div>
                        </div>

                        <!-- Loan Type Specific Breakdown Blocks -->
                        <div class="col-12 mb-4">
                            <h5 class="mb-3 fw-bold text-dark"><i class="ri-git-branch-line me-2"></i>Loan Type Overview & Breakdown</h5>
                            <div class="row g-3">
                                <!-- Standard Loan Block -->
                                <div class="col-lg-4 col-md-12">
                                    <div class="card border-0 shadow-sm rounded-3 h-100" style="border-top: 4px solid #17a2b8 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <span class="badge bg-info text-white fs-6 px-3 py-2"><i class="ri-bank-card-line me-1"></i>Standard Loans</span>
                                                <span class="text-muted small fw-bold">Active: <?php echo $std_active_count; ?></span>
                                            </div>
                                            <div class="row text-center g-2 mt-2">
                                                <div class="col-6">
                                                    <div class="p-2 bg-light rounded">
                                                        <small class="text-muted d-block">Disbursed</small>
                                                        <strong class="text-dark fs-6">₹<?php echo number_format($std_disbursed); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="p-2 bg-light-success rounded">
                                                        <small class="text-muted d-block">Collected</small>
                                                        <strong class="text-success fs-6">₹<?php echo number_format($std_collections); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Interest Loan Block -->
                                <div class="col-lg-4 col-md-12">
                                    <div class="card border-0 shadow-sm rounded-3 h-100" style="border-top: 4px solid #0d6efd !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <span class="badge bg-primary text-white fs-6 px-3 py-2"><i class="ri-percent-line me-1"></i>Interest Loans</span>
                                                <span class="text-muted small fw-bold">Active: <?php echo $int_active_count; ?></span>
                                            </div>
                                            <div class="row text-center g-2 mt-2">
                                                <div class="col-6">
                                                    <div class="p-2 bg-light rounded">
                                                        <small class="text-muted d-block">Disbursed</small>
                                                        <strong class="text-dark fs-6">₹<?php echo number_format($int_disbursed); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="p-2 bg-light-success rounded">
                                                        <small class="text-muted d-block">Collected</small>
                                                        <strong class="text-success fs-6">₹<?php echo number_format($int_collections); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gold Loan Block -->
                                <div class="col-lg-4 col-md-12">
                                    <div class="card border-0 shadow-sm rounded-3 h-100" style="border-top: 4px solid #ffc107 !important;">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center justify-content-between mb-3">
                                                <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="ri-gold-line me-1"></i>Gold Loans</span>
                                                <span class="text-muted small fw-bold">Active: <?php echo $gold_active_count; ?></span>
                                            </div>
                                            <div class="row text-center g-2 mt-2">
                                                <div class="col-4">
                                                    <div class="p-2 bg-light rounded">
                                                        <small class="text-muted d-block">Disbursed</small>
                                                        <strong class="text-dark fs-6">₹<?php echo number_format($gold_disbursed); ?></strong>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 bg-light-warning rounded">
                                                        <small class="text-muted d-block">Gold Pledged</small>
                                                        <strong class="text-dark fs-6"><?php echo number_format($gold_weight, 2); ?>g</strong>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="p-2 bg-light-success rounded">
                                                        <small class="text-muted d-block">Collected</small>
                                                        <strong class="text-success fs-6">₹<?php echo number_format($gold_collections); ?></strong>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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