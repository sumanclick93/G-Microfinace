<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

$logged_time = date("Y-m-d H:i:s");

// 1. Security Check
if (!isset($_SESSION['collection_agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = intval($_SESSION['collection_agent_id']);

// 2. Form Processing: Handle the Bulk Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_accounts'])) {
    $selected_accounts = $_POST['selected_accounts'];
    $amounts = $_POST['amounts'];
    $action_types = $_POST['action_type']; // Fetch the Dropdown states

    foreach ($selected_accounts as $acc_key) {
        $raw_amount = floatval($amounts[$acc_key]);
        
        if ($raw_amount > 0) {
            list($type, $account_id) = explode('_', $acc_key);
            $account_id = intval($account_id);
            
            // Determine if it's a deposit or withdrawal
            $action = isset($action_types[$acc_key]) ? $action_types[$acc_key] : 'deposit';
            
            // Convert to Negative for database math if it's a withdrawal
            $db_amount = ($action === 'withdraw') ? -$raw_amount : $raw_amount;
            
            $trans_type = ($type === 'loan') ? 'emi-received' : 'rd-received';
            $desc = ($action === 'withdraw') ? 'Agent ' . strtoupper($type) . ' Withdrawal' : 'Agent ' . strtoupper($type) . ' Collection';
            
            if ($type === 'loan') {
                $check_owner = $conn->query("SELECT c.agent_id FROM loans l JOIN customers c ON l.customer_id = c.id WHERE l.id = $account_id");
                if (!$check_owner || ($row_owner = $check_owner->fetch_assoc())['agent_id'] != $agent_id) {
                    continue;
                }
                // Cap deposit at total pending EMI (same as Banking UI)
                $loan_info = $conn->query("
                    SELECT COALESCE(l.total_repayable_amount, 0) as target_amount,
                           COALESCE(l.monthly_installment, 0) as installment_amount,
                           COALESCE(l.tenure, 0) as tenure,
                           l.repayment_cycle,
                           l.approval_date as start_date,
                           (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE loan_id = l.id AND (status IS NULL OR status != 'rejected')) as total_paid
                    FROM loans l WHERE l.id = $account_id
                ");
                if ($loan_info && $li = $loan_info->fetch_assoc()) {
                    $rem = max(0.0, floatval($li['target_amount']) - floatval($li['total_paid']));
                    $pend = get_pending_emi($li['start_date'], $li['repayment_cycle'], $li['tenure'], $li['installment_amount'], $li['total_paid'], $rem);
                    $pend_amt = floatval($pend['amount']);
                    if ($action === 'deposit' && $pend_amt > 0 && $raw_amount > $pend_amt) {
                        $raw_amount = $pend_amt;
                    }
                    if ($action === 'deposit' && $pend_amt <= 0) {
                        continue;
                    }
                }
                $db_amount = ($action === 'withdraw') ? -$raw_amount : $raw_amount;

                // Same ledger Super uses (payments) + collection mirror for history
                // $conn->query("INSERT INTO payments (loan_id, amount_paid, collected_by_agent_id, payment_date, status) VALUES ($account_id, $db_amount, $agent_id, '$logged_time', 'approved')");
                $conn->query("INSERT INTO loan_payments_collection (loan_id, amount_paid, collected_by_agent_id, payment_date) VALUES ($account_id, $db_amount, $agent_id, '$logged_time')");
                $conn->query("INSERT INTO wallet_transactions (agent_id, loan_id, transaction_type, amount, description) VALUES ($agent_id, $account_id, '$trans_type', $db_amount, '$desc')");
                // Check if the loan is now fully paid (Super-aligned: non-rejected amounts)
                $check_loan = $conn->query("SELECT l.total_repayable_amount, COALESCE(SUM(p.amount_paid), 0) as paid FROM loans l LEFT JOIN payments p ON l.id = p.loan_id AND (p.status IS NULL OR p.status != 'rejected') WHERE l.id = $account_id");
                if ($check_loan && $row_loan = $check_loan->fetch_assoc()) {
                    if (floatval($row_loan['paid']) >= floatval($row_loan['total_repayable_amount']) - 0.01 && floatval($row_loan['total_repayable_amount']) > 0) {
                        $conn->query("UPDATE loans SET status = 'paid' WHERE id = $account_id AND status NOT IN ('closed', 'paid')");
                    }
                }
            } elseif ($type === 'rd') {
                $check_owner = $conn->query("SELECT c.agent_id FROM recurring_deposits rd JOIN customers c ON rd.customer_id = c.id WHERE rd.id = $account_id");
                if (!$check_owner || ($row_owner = $check_owner->fetch_assoc())['agent_id'] != $agent_id) {
                    continue;
                }
                // Cap deposit at total pending EMI (same as Banking UI)
                $rd_info = $conn->query("
                    SELECT COALESCE((rd.deposit_amount * rd.tenure), 0) as target_amount,
                           COALESCE(rd.deposit_amount, 0) as installment_amount,
                           COALESCE(rd.tenure, 0) as tenure,
                           rd.repayment_cycle,
                           rd.start_date,
                           (SELECT COALESCE(SUM(amount_paid), 0) FROM rd_payments WHERE rd_id = rd.id AND (status IS NULL OR status != 'rejected')) as total_paid
                    FROM recurring_deposits rd WHERE rd.id = $account_id
                ");
                if ($rd_info && $ri = $rd_info->fetch_assoc()) {
                    $rem = max(0.0, floatval($ri['target_amount']) - floatval($ri['total_paid']));
                    $pend = get_pending_emi($ri['start_date'], $ri['repayment_cycle'], $ri['tenure'], $ri['installment_amount'], $ri['total_paid'], $rem);
                    $pend_amt = floatval($pend['amount']);
                    if ($action === 'deposit' && $pend_amt > 0 && $raw_amount > $pend_amt) {
                        $raw_amount = $pend_amt;
                    }
                    if ($action === 'deposit' && $pend_amt <= 0) {
                        continue;
                    }
                }
                $db_amount = ($action === 'withdraw') ? -$raw_amount : $raw_amount;

                // Same ledger Super uses (rd_payments) + collection mirror for history
                // $conn->query("INSERT INTO rd_payments (rd_id, amount_paid, collected_by_agent_id, payment_date, status) VALUES ($account_id, $db_amount, $agent_id, '$logged_time', 'approved')");
                $conn->query("INSERT INTO rd_payments_collection (rd_id, amount_paid, collected_by_agent_id, payment_date) VALUES ($account_id, $db_amount, $agent_id, '$logged_time')");
                $conn->query("INSERT INTO wallet_transactions (agent_id, rd_id, transaction_type, amount, description) VALUES ($agent_id, $account_id, '$trans_type', $db_amount, '$desc')");
                // Maturity check — Super-aligned: sum of non-rejected payments vs deposit_amount * tenure
                $check_rd = $conn->query("SELECT rd.deposit_amount, rd.tenure, COALESCE(SUM(p.amount_paid), 0) as paid FROM recurring_deposits rd LEFT JOIN rd_payments p ON rd.id = p.rd_id AND (p.status IS NULL OR p.status != 'rejected') WHERE rd.id = $account_id GROUP BY rd.id");
                if ($check_rd && $row_rd = $check_rd->fetch_assoc()) {
                    $rd_target = floatval($row_rd['deposit_amount']) * intval($row_rd['tenure']);
                    if (floatval($row_rd['paid']) >= $rd_target - 0.01 && $rd_target > 0) {
                        $conn->query("UPDATE recurring_deposits SET status = 'matured' WHERE id = $account_id AND status NOT IN ('closed', 'matured')");
                    }
                }
            }
            
            // Updates agent wallet (+ for deposit, - for withdrawal)
            $conn->query("UPDATE agent_wallets SET balance = balance + $db_amount WHERE agent_id = $agent_id");
        }
    }
    echo "<script>alert('Transactions securely logged and wallet updated!'); window.location.href='agent-collections.php';</script>";
    exit();
}

// 3. Helpers — same paid / EMI logic as Super admin-loan-details & admin-rd-details
function super_paid_emis_count($total_paid, $installment_amount, $tenure, $approved_count = 0) {
    $paid_emis = (int)$approved_count;
    $tenure = (int)$tenure;
    $installment_amount = (float)$installment_amount;
    if ($installment_amount > 0 && $tenure > 0) {
        $calc = (int)floor(((float)$total_paid) / $installment_amount);
        $paid_emis = min($tenure, max($paid_emis, $calc));
    }
    return $paid_emis;
}

function get_pending_emi($start_date_str, $repayment_cycle, $tenure, $installment_amount, $total_paid, $remaining) {
    $remaining = max(0.0, (float)$remaining);
    $installment_amount = (float)$installment_amount;
    $tenure = (int)$tenure;
    $one_emi = ($installment_amount > 0) ? min($installment_amount, $remaining) : $remaining;

    $result = [
        'amount' => 0.0,
        'pending_count' => 0,
        'installment' => $installment_amount,
    ];

    if ($remaining <= 0) {
        return $result;
    }
    if (empty($start_date_str) || $start_date_str === '0000-00-00' || $start_date_str === '0000-00-00 00:00:00') {
        $result['amount'] = $one_emi;
        $result['pending_count'] = ($installment_amount > 0) ? 1 : 0;
        return $result;
    }

    try {
        $start_date = new DateTime($start_date_str);
        $start_date->setTime(0, 0, 0);
        $today = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $today->setTime(0, 0, 0);

        if ($today <= $start_date) {
            $result['amount'] = $one_emi;
            $result['pending_count'] = ($installment_amount > 0) ? 1 : 0;
            return $result;
        }

        $interval_str = '1 month';
        switch (strtolower((string)$repayment_cycle)) {
            case 'daily': $interval_str = '1 day'; break;
            case 'weekly': $interval_str = '1 week'; break;
            case 'monthly': $interval_str = '1 month'; break;
            case 'quarterly': $interval_str = '3 months'; break;
            case 'half-yearly': $interval_str = '6 months'; break;
            case 'annually': $interval_str = '1 year'; break;
        }

        $paid_emis = super_paid_emis_count($total_paid, $installment_amount, $tenure);

        $installments_due = 0;
        $temp_date = clone $start_date;
        while ($temp_date < $today && $installments_due < $tenure) {
            $temp_date->modify('+' . $interval_str);
            if ($temp_date <= $today) {
                $installments_due++;
            }
        }

        $unpaid_due = max(0, $installments_due - $paid_emis);
        $pending = min($unpaid_due * $installment_amount, $remaining);

        // If schedule is current but balance remains, due is one installment
        if ($pending <= 0 && $remaining > 0) {
            $pending = $one_emi;
            $unpaid_due = ($installment_amount > 0) ? 1 : 0;
        } elseif ($installment_amount > 0 && $pending > 0) {
            // Count how many installments fit in the pending amount (capped by remaining)
            $unpaid_due = (int)round($pending / $installment_amount);
            if ($unpaid_due < 1) {
                $unpaid_due = 1;
            }
        }

        $result['amount'] = $pending;
        $result['pending_count'] = $unpaid_due;
        return $result;
    } catch (Exception $e) {
        $result['amount'] = $one_emi;
        $result['pending_count'] = ($installment_amount > 0) ? 1 : 0;
        return $result;
    }
}

// 4. Fetch Data using indestructible PHP Arrays
$active_accounts = [];

// A. Fetch Loans — paid from `payments` like Super (include NULL status, exclude rejected)
$loans_query = "
    SELECT 
        l.id as account_id,
        c.full_name as customer_name,
        c.avatar as customer_photo,
        COALESCE(l.total_repayable_amount, 0) as target_amount,
        COALESCE(l.monthly_installment, 0) as installment_amount,
        COALESCE(l.tenure, 0) as tenure,
        l.repayment_cycle,
        l.approval_date as start_date,
        LOWER(TRIM(l.status)) as loan_status,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE loan_id = l.id AND (status IS NULL OR status != 'rejected')) as total_paid,
        (SELECT COUNT(*) FROM payments WHERE loan_id = l.id AND status = 'approved') as approved_count
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    WHERE c.agent_id = $agent_id
      AND LOWER(TRIM(l.status)) IN ('active', 'approved', 'pending')
      AND LOWER(TRIM(l.status)) NOT IN ('rejected', 'closed', 'paid', 'settled', 'completed', 'premature-closed')
";
$loans_res = $conn->query($loans_query);
if ($loans_res && $loans_res->num_rows > 0) {
    while ($row = $loans_res->fetch_assoc()) {
        $target = floatval($row['target_amount']);
        $paid = floatval($row['total_paid']);
        $remaining = max(0.0, $target - $paid);
        $paid_emis = super_paid_emis_count($paid, $row['installment_amount'], $row['tenure'], (int)$row['approved_count']);
        // Hide fully paid / completed loans even if status wasn't updated
        if ($remaining > 0.01) { 
            $row['account_type'] = 'loan';
            $row['display_type'] = 'Loan EMI';
            $row['badge_color'] = '#17a2b8';
            $row['paid_emis'] = $paid_emis;
            $pending_info = get_pending_emi(
                $row['start_date'],
                $row['repayment_cycle'],
                $row['tenure'],
                $row['installment_amount'],
                $paid,
                $remaining
            );
            $row['pending_emi'] = $pending_info['amount'];
            $row['pending_emi_count'] = $pending_info['pending_count'];
            $active_accounts[] = $row;
        }
    }
}

// B. Fetch RDs — paid from `rd_payments` like Super
$rds_query = "
    SELECT 
        rd.id as account_id,
        c.full_name as customer_name,
        c.avatar as customer_photo,
        COALESCE((rd.deposit_amount * rd.tenure), 0) as target_amount,
        COALESCE(rd.deposit_amount, 0) as installment_amount,
        COALESCE(rd.tenure, 0) as tenure,
        rd.repayment_cycle,
        rd.start_date,
        LOWER(TRIM(rd.status)) as rd_status,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM rd_payments WHERE rd_id = rd.id AND (status IS NULL OR status != 'rejected')) as total_paid,
        (SELECT COUNT(*) FROM rd_payments WHERE rd_id = rd.id AND status = 'approved') as approved_count
    FROM recurring_deposits rd
    JOIN customers c ON rd.customer_id = c.id
    WHERE c.agent_id = $agent_id
      AND LOWER(TRIM(rd.status)) IN ('active', 'approved', 'pending')
      AND LOWER(TRIM(rd.status)) NOT IN ('rejected', 'closed', 'matured', 'premature-closed', 'settled', 'completed', 'paid')
";
$rds_res = $conn->query($rds_query);
if ($rds_res && $rds_res->num_rows > 0) {
    while ($row = $rds_res->fetch_assoc()) {
        $target = floatval($row['target_amount']);
        $paid = floatval($row['total_paid']);
        $remaining = max(0.0, $target - $paid);
        $paid_emis = super_paid_emis_count($paid, $row['installment_amount'], $row['tenure'], (int)$row['approved_count']);
        // Hide fully paid / matured RDs even if status wasn't updated
        if ($remaining > 0.01) { 
            $row['account_type'] = 'rd';
            $row['display_type'] = 'RD Deposit';
            $row['badge_color'] = '#28a745';
            $row['paid_emis'] = $paid_emis;
            $pending_info = get_pending_emi(
                $row['start_date'],
                $row['repayment_cycle'],
                $row['tenure'],
                $row['installment_amount'],
                $paid,
                $remaining
            );
            $row['pending_emi'] = $pending_info['amount'];
            $row['pending_emi_count'] = $pending_info['pending_count'];
            $active_accounts[] = $row;
        }
    }
}

// Alphabetize the final merged list by Customer Name
usort($active_accounts, function($a, $b) {
    $nameA = !empty($a['customer_name']) ? $a['customer_name'] : 'Z';
    $nameB = !empty($b['customer_name']) ? $b['customer_name'] : 'Z';
    return strcmp($nameA, $nameB);
});
?>

<!DOCTYPE html>
<html lang="en">
<?php include('head.php'); ?>
<style>
    /* Customer Avatar styling */
    .customer-avatar-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        justify-content: center;
    }
    .customer-avatar-img {
        width: 36px;
        height: 36px;
        border-radius: 6px; /* Square with rounded corners */
        object-fit: cover;
        border: 1px solid #dee2e6;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        transition: transform 0.2s ease;
    }
    .customer-avatar-img:hover {
        transform: scale(1.08);
    }
    .customer-info-wrap {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        text-align: left;
    }
    .customer-label-mobile {
        display: none; /* Hidden on desktop */
    }
    .customer-name-text {
        font-weight: 600;
        color: #333;
    }

    /* Banking mobile card layout fixes */
    @media (max-width: 768px) {
        /* Customize the Customer Name td structure */
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td::before {
            display: none !important; /* Hide default label on the left */
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .cell-content {
            max-width: 100% !important;
            width: 100% !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .customer-avatar-wrap {
            display: flex !important;
            flex-direction: row !important; /* Side-by-side layout on mobile */
            align-items: center !important;
            justify-content: flex-start !important;
            gap: 15px !important;
            width: 100% !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .customer-avatar-img {
            width: 50px !important;
            height: 50px !important;
            border-radius: 8px !important;
            border: 1px solid #dee2e6 !important;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15) !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .customer-info-wrap {
            display: flex !important;
            flex-direction: column !important;
            align-items: flex-end !important; /* Align label and name text to the right */
            text-align: right !important;
            flex-grow: 1 !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .customer-label-mobile {
            display: block !important; /* Show label on mobile */
            font-weight: 700 !important;
            color: #475569 !important;
            font-size: 11px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            margin-bottom: 2px !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.customer-name-td .customer-name-text {
            font-size: 15px !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            word-break: break-word !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.action-cell {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 8px !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.action-cell::before {
            max-width: 100% !important;
            margin-bottom: 4px !important;
            padding-top: 0 !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.action-cell .cell-content {
            max-width: 100% !important;
            width: 100% !important;
            align-items: stretch !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table .ca-action-wrap {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table .ca-action-wrap .action-toggle {
            max-width: 100% !important;
            width: 100% !important;
            border-radius: 6px !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table .ca-amount-row {
            display: flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            align-items: center !important;
            gap: 8px !important;
            width: 100% !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table .ca-amount-row .input-group {
            flex: 1 1 auto !important;
            width: auto !important;
            max-width: none !important;
            min-width: 0 !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table .ca-amount-row .amt-input {
            min-width: 0 !important;
            max-width: none !important;
            flex: 1 1 auto !important;
        }

        .dataTables_wrapper.mobile-card-mode #collection_table .btn-card-submit {
            display: block !important;
            width: 100% !important;
            margin-top: 4px !important;
            font-weight: 700 !important;
            padding: 10px 12px !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.pending-cell {
            align-items: flex-start !important;
        }
        .dataTables_wrapper.mobile-card-mode #collection_table tbody tr td.pending-cell .cell-content {
            white-space: normal !important;
            word-break: break-word !important;
        }
        .bulk-submit-wrap {
            display: none !important;
        }
        .ca-filter-bar {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 10px !important;
        }
        .ca-filter-bar > div {
            width: 100% !important;
        }
        .ca-filter-bar select {
            min-width: 0 !important;
            width: 100% !important;
        }
    }
    @media (min-width: 769px) {
        .btn-card-submit {
            display: none !important;
        }
        .ca-action-wrap {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }
        .ca-amount-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ca-amount-row .input-group {
            width: 150px;
        }
    }

    /* Floating scroll top / bottom buttons */
    .ca-float-scroll {
        position: fixed;
        right: 16px;
        bottom: 88px;
        z-index: 1050;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .ca-float-scroll button {
        width: 46px;
        height: 46px;
        border: none;
        border-radius: 50%;
        background: #0f5132;
        color: #fff;
        box-shadow: 0 4px 14px rgba(15, 81, 50, 0.35);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        line-height: 1;
        cursor: pointer;
        opacity: 0.92;
        transition: transform 0.15s ease, opacity 0.15s ease, background 0.15s ease;
    }
    .ca-float-scroll button:hover,
    .ca-float-scroll button:focus {
        opacity: 1;
        transform: scale(1.06);
        background: #198754;
        outline: none;
    }
    .ca-float-scroll button i {
        font-size: 22px;
        line-height: 1;
    }
    @media (max-width: 768px) {
        .ca-float-scroll {
            right: 12px;
            bottom: 72px;
        }
        .ca-float-scroll button {
            width: 44px;
            height: 44px;
        }
    }
</style>
<body>
    <div class="tap-top"><span class="lnr lnr-chevron-up"></span></div>

    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <?php include('header.php'); ?>

        <div class="page-body-wrapper">
            <?php include('sidebaar.php'); ?>

            <div class="page-body">
                <div class="container-fluid">
                    <div class="row mt-4">
                        <div class="col-sm-12">
                            
                            <div class="card">
                                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                                    <div class="card-header-title">
                                        <h4>My Daily Collections</h4>
                                        <p class="text-muted mb-0" style="font-size: 13px;">Showing your assigned active accounts. Toggle Deposit/Withdraw to log transactions.</p>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <form action="agent-collections.php" method="POST" id="collectionForm">
                                        
                                        <?php 
                                            // Extract unique customers for the custom dropdown
                                            $unique_customers = [];
                                            foreach ($active_accounts as $item) {
                                                $cname = trim(!empty($item['customer_name']) ? $item['customer_name'] : 'Unknown Customer');
                                                if (!in_array($cname, $unique_customers)) {
                                                    $unique_customers[] = $cname;
                                                }
                                            }
                                            sort($unique_customers);
                                        ?>

                                        <div class="d-flex justify-content-between align-items-center mb-3 p-2 ca-filter-bar" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;">
                                            
                                            <div>
                                                <label style="font-weight: 600; margin-right: 10px; color: #444; margin-bottom: 0;">Show 
                                                    <select class="custom-length-trigger" data-table="collection_table" style="padding: 5px 10px; border: 1px solid #ced4da; border-radius: 4px; background: #fff; font-weight: bold; cursor: pointer;">
                                                        <option value="10">10</option>
                                                        <option value="25" selected>25</option>
                                                        <option value="50">50</option>
                                                        <option value="-1">All</option>
                                                    </select> Entries
                                                </label>
                                            </div>
                                            
                                            <div>
                                                <label style="font-weight: 600; margin-right: 10px; color: #444; margin-bottom: 0;">Filter:</label>
                                                <select class="custom-filter-trigger" data-table="collection_table" style="padding: 5px 10px; border: 1px solid #007bff; border-radius: 4px; background: #fff; font-weight: bold; cursor: pointer; min-width: 200px;">
                                                    <option value="">-- All My Customers --</option>
                                                    <?php foreach($unique_customers as $uc) { ?>
                                                        <option value="<?php echo htmlspecialchars($uc); ?>"><?php echo htmlspecialchars($uc); ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="table-responsive category-table">
                                            <table class="table table-sm table-striped table-bordered" id="collection_table" style="width:100%;">
                                                <thead style="background-color: #e9ecef;">
                                                    <tr>
                                                        <th style="display:none;">Customer Name Hidden</th>
                                                        <th style="width: 50px;">S.No.</th>
                                                        <th>Customer Name</th>
                                                        <th>Account Type</th>
                                                        <th>Target Amount</th>
                                                        <th>Remaining Balance</th>
                                                        <th>EMIs Paid</th>
                                                        <th>Actual Installment</th>
                                                        <th>Pending EMI</th>
                                                        <th>Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if (count($active_accounts) > 0) {
                                                        $s_no = 1;
                                                        foreach ($active_accounts as $row) {
                                                            $target = floatval($row['target_amount']);
                                                            $paid = floatval($row['total_paid']);
                                                            $remaining = max(0.0, $target - $paid);
                                                            $pending_emi = floatval($row['pending_emi'] ?? 0);
                                                            $pending_count = (int)($row['pending_emi_count'] ?? 0);
                                                            $installment = floatval($row['installment_amount'] ?? 0);
                                                            $paid_emis = (int)($row['paid_emis'] ?? 0);
                                                            $tenure = (int)($row['tenure'] ?? 0);
                                                            
                                                            $unique_key = $row['account_type'] . '_' . $row['account_id'];
                                                            $name = !empty($row['customer_name']) ? $row['customer_name'] : 'Unknown Customer';
                                                    ?>
                                                        <tr>
                                                            <td style="display:none;"><?php echo htmlspecialchars($name); ?></td>
                                                            <td class="sno-cell" style="font-weight: 500; font-size: 14px;"><?php echo $s_no++; ?></td>

                                                            <td class="customer-name-td">
                                                                <?php 
                                                                $photo = !empty($row['customer_photo']) ? '../Agents/upload/customers/avatars/' . $row['customer_photo'] : 'assets/images/users/default-avatar.png'; 
                                                                ?>
                                                                <div class="customer-avatar-wrap">
                                                                    <img src="<?php echo htmlspecialchars($photo); ?>" class="customer-avatar-img" alt="Avatar">
                                                                    <div class="customer-info-wrap">
                                                                        <span class="customer-label-mobile">Customer Name</span>
                                                                        <span class="customer-name-text"><?php echo htmlspecialchars($name); ?></span>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <span style="background-color: <?php echo $row['badge_color']; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                                    <?php echo $row['display_type']; ?>
                                                                </span>
                                                            </td>

                                                            <td style="font-weight: 500; color: #555;">₹<?php echo number_format($target, 2); ?></td>
                                                            <td style="color:#dc3545; font-weight: bold;">₹<?php echo number_format($remaining, 2); ?></td>
                                                            <td style="font-weight: 600; color: #0d6efd;">
                                                                <?php echo $paid_emis; ?> of <?php echo $tenure; ?>
                                                            </td>
                                                            <td style="font-weight: 600; color: #495057;">
                                                                ₹<?php echo number_format($installment, 2); ?>
                                                            </td>
                                                            <td class="pending-cell" style="color:<?php echo $pending_emi > 0 ? '#fd7e14' : '#28a745'; ?>; font-weight: bold;">
                                                                <?php if ($pending_count > 1 && $installment > 0): ?>
                                                                    <?php echo $pending_count; ?> × ₹<?php echo number_format($installment, 2); ?> = ₹<?php echo number_format($pending_emi, 2); ?>
                                                                <?php else: ?>
                                                                    ₹<?php echo number_format($pending_emi, 2); ?>
                                                                <?php endif; ?>
                                                            </td>

                                                            <td class="action-cell" data-label="Action">
                                                                <input type="checkbox" name="selected_accounts[]" value="<?php echo $unique_key; ?>" id="chk_<?php echo $unique_key; ?>" style="display:none;">
                                                                
                                                                <div class="ca-action-wrap">
                                                                    <select name="action_type[<?php echo $unique_key; ?>]" class="form-select form-select-sm bg-light text-dark action-toggle" style="max-width: 105px; font-size: 12px; font-weight: bold;">
                                                                        <option value="deposit">Deposit</option>
                                                                        <option value="withdraw">Withdraw</option>
                                                                    </select>

                                                                    <div class="ca-amount-row">
                                                                        <div class="input-group input-group-sm">
                                                                            <span class="input-group-text bg-light">₹</span>
                                                                            <input type="number" name="amounts[<?php echo $unique_key; ?>]" id="amt_<?php echo $unique_key; ?>" 
                                                                                   class="form-control amt-input" data-key="<?php echo $unique_key; ?>" 
                                                                                   data-pending="<?php echo htmlspecialchars((string)$pending_emi); ?>"
                                                                                   placeholder="0.00" step="0.01" min="0"
                                                                                   max="<?php echo htmlspecialchars((string)$pending_emi); ?>"
                                                                                   style="font-weight: bold; color: #28a745;">
                                                                        </div>

                                                                    </div>

                                                                    <button type="button" class="btn btn-success btn-card-submit" data-key="<?php echo htmlspecialchars($unique_key); ?>">
                                                                        <i class="ri-save-3-line"></i> Submit Transaction
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php } } ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="d-flex justify-content-end mt-4 bulk-submit-wrap">
                                            <button type="submit" class="btn btn-success" style="padding: 10px 25px; font-weight: bold; font-size: 15px;">
                                                <i class="ri-save-3-line"></i> Submit Transactions
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <?php include('footer.php'); ?>
            </div>
        </div>
    </div>

    <div class="ca-float-scroll" aria-label="Page scroll controls">
        <button type="button" id="caScrollTop" title="Scroll to top" aria-label="Scroll to top">
            <i class="ri-arrow-up-line"></i>
        </button>
        <button type="button" id="caScrollBottom" title="Scroll to bottom" aria-label="Scroll to bottom">
            <i class="ri-arrow-down-line"></i>
        </button>
    </div>
    
    <script>
        $(document).ready(function () {

            $('#caScrollTop').on('click', function () {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            $('#caScrollBottom').on('click', function () {
                var doc = document.documentElement;
                var bottom = Math.max(
                    doc.scrollHeight,
                    document.body ? document.body.scrollHeight : 0
                );
                window.scrollTo({ top: bottom, behavior: 'smooth' });
            });

            // Initialize Table
            var table = $('#collection_table').DataTable({
                pageLength: 100,
                order: [],
                dom: "t<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                columnDefs: [
                    { targets: 0, visible: false }, // Hides the clean text column used for filtering
                    { targets: 1, orderable: false, searchable: false } // S.No
                ]
            });

            // Enable mobile card view (default on screens <= 768px), same as All Customer page
            if (typeof window.initMobileCardView === 'function') {
                window.initMobileCardView($('#collection_table'));
            }

            // Handle Customer Filtering
            $('.custom-filter-trigger').on('change', function () {
                var val = $.fn.dataTable.util.escapeRegex($(this).val());
                // Exact string match on the hidden column
                table.column(0).search(val ? '^' + val + '$' : '', true, false).draw();
            });

            // Handle Length Modification
            $('.custom-length-trigger').on('change', function () {
                table.page.len($(this).val()).draw();
            });

            // Auto-Check Row Checkbox + cap amount at pending EMI
            $(document).on('input', '.amt-input', function() {
                var key = $(this).data('key');
                var pendingMax = parseFloat($(this).data('pending')) || 0;
                var val = parseFloat($(this).val());
                if (isNaN(val) || val < 0) {
                    val = 0;
                }
                if (pendingMax > 0 && val > pendingMax) {
                    val = pendingMax;
                    $(this).val(val.toFixed(2));
                }
                $('#chk_' + key).prop('checked', (val > 0));
            });



            // Mobile card: submit ONLY this card's transaction
            $(document).on('click', '.btn-card-submit', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var key = $(this).data('key');
                var $input = $('#amt_' + key);
                if (!$input.length) {
                    $input = table.$('#amt_' + key);
                }
                var val = parseFloat($input.val());
                if (isNaN(val) || val <= 0) {
                    alert('Please enter an amount for this customer first.');
                    return;
                }
                var pendingMax = parseFloat($input.data('pending')) || 0;
                if (pendingMax > 0 && val > pendingMax) {
                    val = pendingMax;
                    $input.val(val.toFixed(2));
                }

                // Uncheck every account, then check only this card
                table.$('input[name="selected_accounts[]"]').prop('checked', false);
                $('#chk_' + key).prop('checked', true);
                if (!$('#chk_' + key).length) {
                    table.$('#chk_' + key).prop('checked', true);
                }

                // Trigger the existing form submit serializer (only checked rows are sent)
                $('#collectionForm').trigger('submit');
            });

            // Visual UI Feedback: Turn input RED if it's a Withdrawal
            $(document).on('change', '.action-toggle', function() {
                var key = $(this).closest('.input-group').find('.amt-input').data('key');
                if ($(this).val() === 'withdraw') {
                    $('#amt_' + key).css('color', '#dc3545'); // Red Text
                } else {
                    $('#amt_' + key).css('color', '#28a745'); // Green Text
                }
            });

            // On form submit, serialize all checked inputs across all DataTable pages
            $('#collectionForm').on('submit', function(e) {
                // Remove any previously appended hidden inputs to avoid duplicate submissions
                $('.appended-hidden-inputs').remove();
                
                var form = this;
                var hasChecked = false;
                
                // Query all pages of the DataTable for checked checkboxes
                table.$('input[name="selected_accounts[]"]:checked').each(function() {
                    var key = $(this).val();
                    hasChecked = true;
                    
                    // Append the checkbox value
                    $(form).append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'selected_accounts[]')
                            .addClass('appended-hidden-inputs')
                            .val(key)
                    );
                    
                    // Retrieve amount from active DOM or DataTables hidden cache
                    var amountVal = $('#amt_' + key).val() || table.$('#amt_' + key).val();
                    $(form).append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'amounts[' + key + ']')
                            .addClass('appended-hidden-inputs')
                            .val(amountVal)
                    );
                    
                    // Retrieve action type from active DOM or DataTables hidden cache
                    var actionVal = $('select[name="action_type[' + key + ']"]').val() || table.$('select[name="action_type[' + key + ']"]').val();
                    $(form).append(
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('name', 'action_type[' + key + ']')
                            .addClass('appended-hidden-inputs')
                            .val(actionVal)
                    );
                });
                
                // Validate that at least one transaction is being submitted
                if (!hasChecked) {
                    e.preventDefault();
                    alert("Please enter a transaction amount for at least one customer before submitting.");
                } else {
                    // Remove name attributes from visible inputs to prevent double submission of active page elements
                    $('#collection_table').find('input, select').removeAttr('name');
                }
            });

        });
    </script>
</body>
</html>