<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

$logged_time = date("Y-m-d H:i:s");

// 1. Security Check
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = intval($_SESSION['agent_id']);

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
                $conn->query("INSERT INTO payments (loan_id, amount_paid, collected_by_agent_id, payment_date, status) VALUES ($account_id, $db_amount, $agent_id, '$logged_time', 'approved')");
                // $conn->query("INSERT INTO loan_payments_collection (loan_id, amount_paid, collected_by_agent_id, payment_date) VALUES ($account_id, $db_amount, $agent_id, '$logged_time')");
                $conn->query("INSERT INTO wallet_transactions (agent_id, loan_id, transaction_type, amount, description) VALUES ($agent_id, $account_id, '$trans_type', $db_amount, '$desc')");
                // Check if the loan is now fully paid
                $check_loan = $conn->query("SELECT l.total_repayable_amount, COALESCE(SUM(p.amount_paid), 0) as paid FROM loans l LEFT JOIN payments p ON l.id = p.loan_id WHERE l.id = $account_id AND (p.status != 'rejected' OR p.status IS NULL)");
                if ($check_loan && $row_loan = $check_loan->fetch_assoc()) {
                    if (floatval($row_loan['paid']) >= floatval($row_loan['total_repayable_amount']) && floatval($row_loan['total_repayable_amount']) > 0) {
                        $conn->query("UPDATE loans SET status = 'paid' WHERE id = $account_id AND status NOT IN ('closed', 'paid')");
                    }
                }
            } elseif ($type === 'rd') {
                $check_owner = $conn->query("SELECT c.agent_id FROM recurring_deposits rd JOIN customers c ON rd.customer_id = c.id WHERE rd.id = $account_id");
                if (!$check_owner || ($row_owner = $check_owner->fetch_assoc())['agent_id'] != $agent_id) {
                    continue;
                }
                $conn->query("INSERT INTO rd_payments (rd_id, amount_paid, collected_by_agent_id, payment_date) VALUES ($account_id, $db_amount, $agent_id, '$logged_time')");
                // $conn->query("INSERT INTO rd_payments_collection (rd_id, amount_paid, collected_by_agent_id, payment_date) VALUES ($account_id, $db_amount, $agent_id, '$logged_time')");
                $conn->query("INSERT INTO wallet_transactions (agent_id, rd_id, transaction_type, amount, description) VALUES ($agent_id, $account_id, '$trans_type', $db_amount, '$desc')");
                // Check if the RD has reached maturity (all EMIs/installments paid)
                $check_rd = $conn->query("SELECT rd.tenure, COUNT(p.id) as cnt FROM recurring_deposits rd LEFT JOIN rd_payments p ON rd.id = p.rd_id WHERE rd.id = $account_id AND (p.status != 'rejected' OR p.status IS NULL)");
                if ($check_rd && $row_rd = $check_rd->fetch_assoc()) {
                    if (intval($row_rd['cnt']) >= intval($row_rd['tenure']) && intval($row_rd['tenure']) > 0) {
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

// 3. Helper: pending EMI/installment amount for an account
function get_pending_emi($start_date_str, $repayment_cycle, $tenure, $installment_amount, $total_paid, $remaining) {
    $remaining = max(0.0, (float)$remaining);
    $installment_amount = (float)$installment_amount;
    $one_emi = ($installment_amount > 0) ? min($installment_amount, $remaining) : $remaining;

    if ($remaining <= 0) {
        return 0.0;
    }
    if (empty($start_date_str) || $start_date_str === '0000-00-00' || $start_date_str === '0000-00-00 00:00:00') {
        return $one_emi;
    }

    try {
        $start_date = new DateTime($start_date_str);
        $start_date->setTime(0, 0, 0);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        if ($today <= $start_date) {
            return $one_emi;
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

        $installments_due = 0;
        $temp_date = clone $start_date;
        while ($temp_date < $today && $installments_due < (int)$tenure) {
            $temp_date->modify('+' . $interval_str);
            if ($temp_date <= $today) {
                $installments_due++;
            }
        }

        $expected_paid = $installments_due * $installment_amount;
        $overdue = max(0.0, $expected_paid - (float)$total_paid);
        $pending = min($overdue, $remaining);

        if ($pending <= 0 && $remaining > 0) {
            $pending = $one_emi;
        }

        return $pending;
    } catch (Exception $e) {
        return $one_emi;
    }
}

// 4. Fetch Data using indestructible PHP Arrays
$active_accounts = [];

// A. Fetch Loans belonging to this Agent's customers (skip closed / completed)
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
        (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE loan_id = l.id AND status != 'rejected') as total_paid
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    WHERE c.agent_id = $agent_id
      AND LOWER(TRIM(l.status)) IN ('active', 'approved', 'pending')
";
$loans_res = $conn->query($loans_query);
if ($loans_res && $loans_res->num_rows > 0) {
    while ($row = $loans_res->fetch_assoc()) {
        $target = floatval($row['target_amount']);
        $paid = floatval($row['total_paid']);
        $remaining = $target - $paid;
        if ($remaining > 0.01) { 
            $row['account_type'] = 'loan';
            $row['display_type'] = 'Loan EMI';
            $row['badge_color'] = '#17a2b8';
            $row['pending_emi'] = get_pending_emi(
                $row['start_date'],
                $row['repayment_cycle'],
                $row['tenure'],
                $row['installment_amount'],
                $paid,
                $remaining
            );
            $active_accounts[] = $row;
        }
    }
}

// B. Fetch RDs belonging to this Agent's customers (skip closed / matured / completed)
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
        (SELECT COALESCE(SUM(amount_paid), 0) FROM rd_payments WHERE rd_id = rd.id AND status != 'rejected') as total_paid
    FROM recurring_deposits rd
    JOIN customers c ON rd.customer_id = c.id
    WHERE c.agent_id = $agent_id
      AND LOWER(TRIM(rd.status)) IN ('active', 'approved', 'pending')
";
$rds_res = $conn->query($rds_query);
if ($rds_res && $rds_res->num_rows > 0) {
    while ($row = $rds_res->fetch_assoc()) {
        $target = floatval($row['target_amount']);
        $paid = floatval($row['total_paid']);
        $remaining = $target - $paid;
        if ($remaining > 0.01) { 
            $row['account_type'] = 'rd';
            $row['display_type'] = 'RD Deposit';
            $row['badge_color'] = '#28a745';
            $row['pending_emi'] = get_pending_emi(
                $row['start_date'],
                $row['repayment_cycle'],
                $row['tenure'],
                $row['installment_amount'],
                $paid,
                $remaining
            );
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

                                        <div class="d-flex justify-content-between align-items-center mb-3 p-2" style="background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px;">
                                            
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
                                                        <th>Pending EMI</th>
                                                        <th>Transaction Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    if (count($active_accounts) > 0) {
                                                        $s_no = 1;
                                                        foreach ($active_accounts as $row) {
                                                            $target = floatval($row['target_amount']);
                                                            $paid = floatval($row['total_paid']);
                                                            $remaining = $target - $paid;
                                                            $pending_emi = floatval($row['pending_emi'] ?? 0);
                                                            
                                                            $unique_key = $row['account_type'] . '_' . $row['account_id'];
                                                            $name = !empty($row['customer_name']) ? $row['customer_name'] : 'Unknown Customer';
                                                            $photo = !empty($row['customer_photo']) ? '../uploads/' . $row['customer_photo'] : 'assets/images/default-avatar.png';
                                                    ?>
                                                        <tr>
                                                            <td style="display:none;"><?php echo htmlspecialchars($name); ?></td>
                                                            <td class="sno-cell" style="font-weight: 500; font-size: 14px;"><?php echo $s_no++; ?></td>

                                                            <td style="font-size: 14px;">
                                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                                    <img src="<?php echo htmlspecialchars($photo); ?>" style="width: 32px; height: 32px; border-radius: 50%;">
                                                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></span>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                <span style="background-color: <?php echo $row['badge_color']; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                                    <?php echo $row['display_type']; ?>
                                                                </span>
                                                            </td>

                                                            <td style="font-weight: 500; color: #555;">₹<?php echo number_format($target, 2); ?></td>
                                                            <td style="color:#dc3545; font-weight: bold;">₹<?php echo number_format($remaining, 2); ?></td>
                                                            <td style="color:<?php echo $pending_emi > 0 ? '#fd7e14' : '#28a745'; ?>; font-weight: bold;">
                                                                ₹<?php echo number_format($pending_emi, 2); ?>
                                                            </td>

                                                            <td>
                                                                <input type="checkbox" name="selected_accounts[]" value="<?php echo $unique_key; ?>" id="chk_<?php echo $unique_key; ?>" style="display:none;">
                                                                
                                                                <div class="input-group input-group-sm" style="width: 290px;">
                                                                    
                                                                    <select name="action_type[<?php echo $unique_key; ?>]" class="form-select bg-light text-dark action-toggle" style="max-width: 105px; font-size: 12px; font-weight: bold; border-right: 0;">
                                                                        <option value="deposit">Deposit</option>
                                                                        <option value="withdraw">Withdraw</option>
                                                                    </select>

                                                                    <span class="input-group-text bg-light border-start-0">₹</span>
                                                                    
                                                                    <input type="number" name="amounts[<?php echo $unique_key; ?>]" id="amt_<?php echo $unique_key; ?>" 
                                                                           class="form-control amt-input" data-key="<?php echo $unique_key; ?>" 
                                                                           placeholder="0.00" step="0.01" 
                                                                           style="font-weight: bold; color: #28a745;">
                                                                    
                                                                    <button class="btn btn-outline-primary btn-full" type="button"
                                                                            data-key="<?php echo $unique_key; ?>"
                                                                            data-max="<?php echo $remaining; ?>"
                                                                            data-pending="<?php echo $pending_emi; ?>">
                                                                            FULL
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php } } ?>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="d-flex justify-content-end mt-4">
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
    
    <script>
        $(document).ready(function () {

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

            // Auto-Check Row Checkbox when amount is typed
            $(document).on('input', '.amt-input', function() {
                var key = $(this).data('key');
                var val = parseFloat($(this).val());
                $('#chk_' + key).prop('checked', (val > 0));
            });

            // "FULL" Button auto-fill — prefers pending EMI, falls back to remaining balance
            $(document).on('click', '.btn-full', function() {
                var key = $(this).data('key');
                var pending = parseFloat($(this).data('pending')) || 0;
                var maxBalance = parseFloat($(this).data('max')) || 0;
                var fillAmount = (pending > 0 ? pending : maxBalance).toFixed(2);
                $('#amt_' + key).val(fillAmount);
                $('#amt_' + key).trigger('input'); // Trigger auto-check
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