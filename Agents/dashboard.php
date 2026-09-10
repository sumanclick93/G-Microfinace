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
// My Total Customers
$total_customers_result = $conn->query("SELECT COUNT(id) as total FROM customers WHERE agent_id = $agent_id");
$total_customers = $total_customers_result->fetch_assoc()['total'] ?? 0;

// My Active Loans
$active_loans_result = $conn->query("SELECT COUNT(id) as total FROM loans WHERE agent_id = $agent_id AND status = 'active'");
$active_loans_count = $active_loans_result->fetch_assoc()['total'] ?? 0;

// My Active RDs
$active_rds_result = $conn->query("SELECT COUNT(id) as total FROM recurring_deposits WHERE agent_id = $agent_id AND status = 'active'");
$total_active_rds = $active_rds_result->fetch_assoc()['total'] ?? 0;

// My Loan Collections
$total_collections_result = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE collected_by_agent_id = $agent_id");
$total_collections = $total_collections_result->fetch_assoc()['total'] ?? 0;

// My RD Collections
$total_rd_collected_result = $conn->query("SELECT SUM(amount_paid) as total FROM rd_payments WHERE collected_by_agent_id = $agent_id");
$total_rd_collections = $total_rd_collected_result->fetch_assoc()['total'] ?? 0;

// My Total Collections (Loan + RD)
$my_total_collections = $total_collections + $total_rd_collections;

// My Wallet Balance
$wallet_balance_result = $conn->query("SELECT balance FROM agent_wallets WHERE agent_id = $agent_id");
$wallet_balance = $wallet_balance_result->fetch_assoc()['balance'] ?? 0;

// 3. Calculate Defaults for Agent Dashboard
$default_accounts_count = 0;
$total_default_amount = 0;

// Calculate Loan Defaults for active/approved loans under this agent
$loans_query = $conn->query("
    SELECT 
        l.id, l.loan_amount, l.total_repayable_amount, l.monthly_installment, l.tenure, l.repayment_cycle, l.approval_date,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE loan_id = l.id) as overall_paid
    FROM loans l
    WHERE l.agent_id = $agent_id AND l.status IN ('approved', 'active')
");

if ($loans_query) {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    while ($row = $loans_query->fetch_assoc()) {
        $approval_date_str = $row['approval_date'];
        if (!empty($approval_date_str) && $approval_date_str !== '0000-00-00' && $approval_date_str !== '0000-00-00 00:00:00') {
            try {
                $approval_date = new DateTime($approval_date_str);
                $approval_date->setTime(0, 0, 0);
                if ($today > $approval_date) {
                    $interval_str = '1 month';
                    switch (strtolower($row['repayment_cycle'])) {
                        case 'daily': $interval_str = '1 day'; break;
                        case 'weekly': $interval_str = '1 week'; break;
                        case 'monthly': $interval_str = '1 month'; break;
                        case 'quarterly': $interval_str = '3 months'; break;
                        case 'half-yearly': $interval_str = '6 months'; break;
                        case 'annually': $interval_str = '1 year'; break;
                    }
                    $installments_due = 0;
                    $temp_date = clone $approval_date;
                    while ($temp_date < $today && $installments_due < (int)$row['tenure']) {
                        $temp_date->modify('+' . $interval_str);
                        if ($temp_date <= $today) {
                            $installments_due++;
                        }
                    }
                    $expected_paid = $installments_due * (float)$row['monthly_installment'];
                    $rem_balance = (float)$row['total_repayable_amount'] - (float)$row['overall_paid'];
                    $overdue = max(0.0, $expected_paid - (float)$row['overall_paid']);
                    $default_amt = min($overdue, $rem_balance);
                    
                    if ($default_amt > 0) {
                        $default_accounts_count++;
                        $total_default_amount += $default_amt;
                    }
                }
            } catch (Exception $e) {
                // Skip on parser error
            }
        }
    }
}

// Calculate RD Defaults for active recurring deposits under this agent
$rds_query = $conn->query("
    SELECT 
        rd.id, rd.deposit_amount, rd.tenure, rd.repayment_cycle, rd.start_date,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM rd_payments WHERE rd_id = rd.id) as overall_paid
    FROM recurring_deposits rd
    WHERE rd.agent_id = $agent_id AND rd.status = 'active'
");

if ($rds_query) {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    while ($row = $rds_query->fetch_assoc()) {
        $start_date_str = $row['start_date'];
        if (!empty($start_date_str) && $start_date_str !== '0000-00-00' && $start_date_str !== '0000-00-00 00:00:00') {
            try {
                $rd_start_dt = new DateTime($start_date_str);
                $rd_start_dt->setTime(0, 0, 0);
                if ($today > $rd_start_dt) {
                    $interval_str = '1 month';
                    switch (strtolower($row['repayment_cycle'])) {
                        case 'daily': $interval_str = '1 day'; break;
                        case 'weekly': $interval_str = '1 week'; break;
                        case 'monthly': $interval_str = '1 month'; break;
                        case 'quarterly': $interval_str = '3 months'; break;
                        case 'half-yearly': $interval_str = '6 months'; break;
                        case 'annually': $interval_str = '1 year'; break;
                    }
                    $installments_due = 0;
                    $temp_date = clone $rd_start_dt;
                    while ($temp_date < $today && $installments_due < (int)$row['tenure']) {
                        $temp_date->modify('+' . $interval_str);
                        if ($temp_date <= $today) {
                            $installments_due++;
                        }
                    }
                    $expected_paid = $installments_due * (float)$row['deposit_amount'];
                    $total_principal_due = (float)$row['deposit_amount'] * (int)$row['tenure'];
                    $rem_balance = $total_principal_due - (float)$row['overall_paid'];
                    $overdue = max(0.0, $expected_paid - (float)$row['overall_paid']);
                    $default_amt = min($overdue, $rem_balance);
                    
                    if ($default_amt > 0) {
                        $default_accounts_count++;
                        $total_default_amount += $default_amt;
                    }
                }
            } catch (Exception $e) {
                // Skip on parser error
            }
        }
    }
}

// 4. Loan Type Breakdown Metrics for Agent
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

                        <!-- Overall Agent Overview -->
                        <div class="col-12 mb-4">
                            <h5 class="mb-3 fw-bold text-dark"><i class="ri-dashboard-3-line me-2"></i>My Portfolio Overview</h5>
                            <div class="row g-3">
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Active Loans</h6><h2><?php echo $active_loans_count; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Active RDs</h6><h2><?php echo $total_active_rds; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Loan Collections</h6><h2>₹<?php echo number_format($total_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My RD Collections</h6><h2>₹<?php echo number_format($total_rd_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Total Collections</h6><h2 class="text-success">₹<?php echo number_format($my_total_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Defaulter Accounts</h6><h2 class="text-danger"><?php echo $default_accounts_count; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Total Default Amount</h6><h2 class="text-danger">₹<?php echo number_format($total_default_amount); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100 shadow-sm border-0"><h6 class="text-muted">My Wallet Balance</h6><h2 class="text-success">₹<?php echo number_format($wallet_balance); ?></h2></div></div>
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
                                        <a href="all-loans.php" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">My Loan Report</h6>
                                                <small><i class="ri-arrow-right-s-line"></i></small>
                                            </div>
                                            <p class="mb-1">View and filter all loan applications you have created.</p>
                                        </a>
                                        <a href="report-my-collections.php" class="list-group-item list-group-item-action">
                                             <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">My Collection Report</h6>
                                                <small><i class="ri-arrow-right-s-line"></i></small>
                                            </div>
                                            <p class="mb-1">Track all EMI payments you have collected within a date range.</p>
                                        </a>
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