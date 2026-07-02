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
                            <div class="row g-3">
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Total Customers</h6><h2><?php echo $total_customers; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Active Loans</h6><h2><?php echo $active_loans_count; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Active RDs</h6><h2><?php echo $total_active_rds; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Loan Collections</h6><h2>₹<?php echo number_format($total_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My RD Collections</h6><h2>₹<?php echo number_format($total_rd_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Total Collections</h6><h2 class="text-success">₹<?php echo number_format($my_total_collections); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Defaulter Accounts</h6><h2 class="text-danger"><?php echo $default_accounts_count; ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Total Default Amount</h6><h2 class="text-danger">₹<?php echo number_format($total_default_amount); ?></h2></div></div>
                                <div class="col-lg-4 col-md-6"><div class="card card-body text-center h-100"><h6 class="text-muted">My Wallet Balance</h6><h2 class="text-success">₹<?php echo number_format($wallet_balance); ?></h2></div></div>
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