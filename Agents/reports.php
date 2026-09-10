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
// Loan Type Breakdown Metrics for Agent
// Standard Loans
$std_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total FROM loans WHERE agent_id = $agent_id AND loan_type = 'standard' AND status IN ('active', 'paid')");
$std_disbursed = $std_disbursed_res->fetch_assoc()['total'] ?? 0;
$std_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id AND loan_type = 'standard' AND status = 'active'");
$std_active_count = $std_active_res->fetch_assoc()['total'] ?? 0;
$std_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE (p.collected_by_agent_id = $agent_id OR l.agent_id = $agent_id) AND l.loan_type = 'standard' AND p.status != 'rejected'");
$std_collections = $std_coll_res->fetch_assoc()['total'] ?? 0;

// Interest-Only Loans
$int_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total FROM loans WHERE agent_id = $agent_id AND loan_type = 'interest_only' AND status IN ('active', 'paid')");
$int_disbursed = $int_disbursed_res->fetch_assoc()['total'] ?? 0;
$int_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id AND loan_type = 'interest_only' AND status = 'active'");
$int_active_count = $int_active_res->fetch_assoc()['total'] ?? 0;
$int_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE (p.collected_by_agent_id = $agent_id OR l.agent_id = $agent_id) AND l.loan_type = 'interest_only' AND p.status != 'rejected'");
$int_collections = $int_coll_res->fetch_assoc()['total'] ?? 0;

// Gold Loans
$gold_disbursed_res = $conn->query("SELECT COALESCE(SUM(loan_amount),0) as total, COALESCE(SUM(gold_weight_grams),0) as total_weight FROM loans WHERE agent_id = $agent_id AND loan_type = 'gold' AND status IN ('active', 'paid')");
$gold_row = $gold_disbursed_res->fetch_assoc();
$gold_disbursed = $gold_row['total'] ?? 0;
$gold_weight = $gold_row['total_weight'] ?? 0;
$gold_active_res = $conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id AND loan_type = 'gold' AND status = 'active'");
$gold_active_count = $gold_active_res->fetch_assoc()['total'] ?? 0;
$gold_coll_res = $conn->query("SELECT COALESCE(SUM(p.amount_paid),0) as total FROM payments p JOIN loans l ON p.loan_id = l.id WHERE (p.collected_by_agent_id = $agent_id OR l.agent_id = $agent_id) AND l.loan_type = 'gold' AND p.status != 'rejected'");
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
                                <h5>My Reports</h5>
                            </div>
                        </div>

                        <div class="col-12 mb-4">
                             <h5 class="mb-3 fw-bold text-dark"><i class="ri-dashboard-3-line me-2"></i>My Performance Snapshot</h5>
                            <div class="row g-3">
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Loans Processed</h6><h2><?php echo $total_loans; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Active RDs</h6><h2><?php echo $my_active_rds; ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Loan Collections</h6><h2>₹<?php echo number_format($total_loan_collections); ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My RD Collections</h6><h2 class="text-success">₹<?php echo number_format($my_rd_collections); ?></h2></div></div>
                                <div class="col-md-4"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Wallet Balance</h6><h2 class="text-info">₹<?php echo number_format($wallet_balance); ?></h2></div></div>
                            </div>
                        </div>

                        <!-- Loan Type Specific Breakdown Blocks -->
                        <div class="col-12 mb-4">
                            <h5 class="mb-3 fw-bold text-dark"><i class="ri-git-branch-line me-2"></i>My Loan Types Overview & Breakdown</h5>
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