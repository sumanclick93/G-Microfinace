<?php
include('config.php');

// 1. Security Check
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = intval($_SESSION['agent_id']);

// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; 
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_customer = isset($_GET['customer_id']) ? $_GET['customer_id'] : 'all';

// Fetch all unique customers that THIS AGENT has collected from
$customers_query = "
    SELECT DISTINCT c.id, c.full_name 
    FROM wallet_transactions t
    LEFT JOIN loans l ON t.loan_id = l.id
    LEFT JOIN recurring_deposits rd ON t.rd_id = rd.id
    JOIN customers c ON c.id = COALESCE(l.customer_id, rd.customer_id)
    WHERE t.agent_id = $agent_id
    ORDER BY c.full_name ASC
";
$customers_result = $conn->query($customers_query);

// Build the dynamic WHERE clause (Strictly locked to $agent_id)
$where_clauses = ["t.agent_id = $agent_id"];

if (!empty($start_date)) {
    $where_clauses[] = "DATE(t.transaction_date) >= '$start_date'";
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(t.transaction_date) <= '$end_date'";
}

if ($filter_type === 'emi') {
    $where_clauses[] = "t.transaction_type = 'emi-received'";
} elseif ($filter_type === 'rd') {
    $where_clauses[] = "t.transaction_type = 'rd-received'";
} else {
    $where_clauses[] = "t.transaction_type IN ('emi-received', 'rd-received')";
}

if ($filter_customer !== 'all') {
    $customer_id_safe = intval($filter_customer);
    $where_clauses[] = "(l.customer_id = $customer_id_safe OR rd.customer_id = $customer_id_safe)";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch ALL Detailed Data (Corrected l.loan_amount based on your schema)
$details_query = "
    SELECT 
        t.transaction_type, 
        t.amount, 
        t.description, 
        t.transaction_date,
        c.full_name as customer_name,
        c.avatar as customer_photo,
        l.id as loan_id,
        l.status as loan_status,
        l.approval_date as loan_approval_date,
        l.repayment_cycle as loan_repayment_cycle,
        l.tenure as loan_tenure,
        l.monthly_installment as loan_monthly_installment,
        rd.id as rd_id,
        rd.status as rd_status,
        rd.start_date as rd_start_date,
        rd.repayment_cycle as rd_repayment_cycle,
        rd.tenure as rd_tenure,
        rd.deposit_amount as rd_deposit_amount,
        
        COALESCE(l.loan_amount, 0) as loan_principal,
        COALESCE(l.total_repayable_amount, 0) as loan_maturity,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE loan_id = l.id AND status != 'rejected') as loan_overall_paid,
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt WHERE wt.loan_id = t.loan_id AND wt.loan_id IS NOT NULL AND wt.loan_id > 0 AND wt.transaction_type = 'emi-received' AND wt.transaction_date <= t.transaction_date) as running_loan_paid,
        
        COALESCE((rd.deposit_amount * rd.tenure), 0) as rd_principal,
        COALESCE(rd.maturity_amount, 0) as rd_maturity,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM rd_payments WHERE rd_id = rd.id AND status != 'rejected') as rd_overall_paid,
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt2 WHERE wt2.rd_id = t.rd_id AND wt2.rd_id IS NOT NULL AND wt2.rd_id > 0 AND wt2.transaction_type = 'rd-received' AND wt2.transaction_date <= t.transaction_date) as running_rd_paid
        
    FROM wallet_transactions t
    LEFT JOIN loans l ON t.loan_id = l.id
    LEFT JOIN recurring_deposits rd ON t.rd_id = rd.id
    LEFT JOIN customers c ON c.id = COALESCE(l.customer_id, rd.customer_id)
    WHERE $where_sql
    ORDER BY t.transaction_date DESC
";

$details_result = null;
$query_error = '';
try {
    $details_result = $conn->query($details_query);
} catch (Throwable $e) {
    $query_error = $e->getMessage();
}

// Process the data in PHP for 100% accurate deduplication
$details_map = [];
$summary_stats = [
    'emi-received' => ['txn_count' => 0, 'collected' => 0, 'accounts' => [], 'total_amt' => 0, 'total_mat' => 0, 'total_rem' => 0, 'default_count' => 0, 'default_amount' => 0],
    'rd-received' => ['txn_count' => 0, 'collected' => 0, 'accounts' => [], 'total_amt' => 0, 'total_mat' => 0, 'total_rem' => 0, 'default_count' => 0, 'default_amount' => 0]
];

if ($details_result && $details_result->num_rows > 0) {
    while ($row = $details_result->fetch_assoc()) {
        $type = !empty($row['transaction_type']) ? $row['transaction_type'] : 'unknown';
        
        // Save to Itemized Details Map
        $details_map[$type][] = $row;
        
        // Add Period Transaction Data
        if (isset($summary_stats[$type])) {
            $summary_stats[$type]['txn_count']++;
            $summary_stats[$type]['collected'] += floatval($row['amount']);
        }
        
        // Add Account Data (ONLY ONCE per unique Loan/RD so we don't multiply principal amounts)
        if ($type === 'emi-received' && !empty($row['loan_id'])) {
            if (!in_array($row['loan_id'], $summary_stats[$type]['accounts'])) {
                $summary_stats[$type]['accounts'][] = $row['loan_id'];
                $summary_stats[$type]['total_amt'] += floatval($row['loan_principal']);
                $summary_stats[$type]['total_mat'] += floatval($row['loan_maturity']);
                $rem = floatval($row['loan_maturity']) - floatval($row['loan_overall_paid']);
                $summary_stats[$type]['total_rem'] += max(0, $rem);
                
                // Calculate Loan Default Amount
                $default_amt = 0;
                if (!empty($row['loan_status']) && $row['loan_status'] === 'defaulted') {
                    $default_amt = max(0, $rem);
                } else if (!empty($row['loan_status']) && in_array($row['loan_status'], ['active', 'approved'])) {
                    $approval_date_str = $row['loan_approval_date'];
                    if (!empty($approval_date_str) && $approval_date_str !== '0000-00-00' && $approval_date_str !== '0000-00-00 00:00:00') {
                        try {
                            $approval_date = new DateTime($approval_date_str);
                            $approval_date->setTime(0, 0, 0);
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);

                            if ($today > $approval_date) {
                                $interval_str = '1 month';
                                switch (strtolower((string)($row['loan_repayment_cycle'] ?? 'monthly'))) {
                                    case 'daily': $interval_str = '1 day'; break;
                                    case 'weekly': $interval_str = '1 week'; break;
                                    case 'monthly': $interval_str = '1 month'; break;
                                    case 'quarterly': $interval_str = '3 months'; break;
                                    case 'half-yearly': $interval_str = '6 months'; break;
                                    case 'annually': $interval_str = '1 year'; break;
                                }
                                
                                $installments_due = 0;
                                $temp_date = clone $approval_date;
                                while ($temp_date < $today && $installments_due < (int)($row['loan_tenure'] ?? 0)) {
                                    $temp_date->modify('+' . $interval_str);
                                    if ($temp_date <= $today) {
                                        $installments_due++;
                                    }
                                }

                                $expected_paid = $installments_due * (float)($row['loan_monthly_installment'] ?? 0);
                                $overdue = max(0.0, $expected_paid - floatval($row['loan_overall_paid'] ?? 0));
                                $default_amt = min($overdue, max(0, $rem));
                            }
                        } catch (Exception $e) {
                            $default_amt = 0;
                        }
                    }
                }
                
                if ($default_amt > 0) {
                    $summary_stats[$type]['default_count']++;
                    $summary_stats[$type]['default_amount'] += $default_amt;
                }
            }
        } elseif ($type === 'rd-received' && !empty($row['rd_id'])) {
            if (!in_array($row['rd_id'], $summary_stats[$type]['accounts'])) {
                $summary_stats[$type]['accounts'][] = $row['rd_id'];
                $summary_stats[$type]['total_amt'] += floatval($row['rd_principal']);
                $summary_stats[$type]['total_mat'] += floatval($row['rd_maturity']);
                $rem = floatval($row['rd_principal']) - floatval($row['rd_overall_paid']);
                $summary_stats[$type]['total_rem'] += max(0, $rem);
                
                // Calculate RD Default Amount
                $default_amt = 0;
                if (!empty($row['rd_status']) && in_array($row['rd_status'], ['active', 'approved'])) {
                    $start_date_str = $row['rd_start_date'];
                    if (!empty($start_date_str) && $start_date_str !== '0000-00-00' && $start_date_str !== '0000-00-00 00:00:00') {
                        try {
                            $rd_start_dt = new DateTime($start_date_str);
                            $rd_start_dt->setTime(0, 0, 0);
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);

                            if ($today > $rd_start_dt) {
                                $interval_str = '1 month';
                                switch (strtolower((string)($row['rd_repayment_cycle'] ?? 'monthly'))) {
                                    case 'daily': $interval_str = '1 day'; break;
                                    case 'weekly': $interval_str = '1 week'; break;
                                    case 'monthly': $interval_str = '1 month'; break;
                                    case 'quarterly': $interval_str = '3 months'; break;
                                    case 'half-yearly': $interval_str = '6 months'; break;
                                    case 'annually': $interval_str = '1 year'; break;
                                }

                                $installments_due = 0;
                                $temp_date = clone $rd_start_dt;
                                while ($temp_date < $today && $installments_due < (int)($row['rd_tenure'] ?? 0)) {
                                    $temp_date->modify('+' . $interval_str);
                                    if ($temp_date <= $today) {
                                        $installments_due++;
                                    }
                                }

                                $expected_paid = $installments_due * (float)($row['rd_deposit_amount'] ?? 0);
                                $overdue = max(0.0, $expected_paid - floatval($row['rd_overall_paid'] ?? 0));
                                $default_amt = min($overdue, max(0, $rem));
                            }
                        } catch (Exception $e) {
                            $default_amt = 0;
                        }
                    }
                }
                
                if ($default_amt > 0) {
                    $summary_stats[$type]['default_count']++;
                    $summary_stats[$type]['default_amount'] += $default_amt;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include('head.php'); ?>
    
    <style>
        /* Force DataTables controls to render properly */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter {
            display: block !important;
            opacity: 1 !important;
            visibility: visible !important;
            margin-bottom: 15px;
        }
        .dataTables_wrapper .dataTables_length select, 
        .dataTables_wrapper .dataTables_filter input {
            display: inline-block !important;
            width: auto !important;
            padding: 4px 10px !important;
            border: 1px solid #ced4da !important;
            border-radius: 4px !important;
            margin-left: 8px !important;
        }
    </style>
</head>

<body>
    <div class="tap-top"><span class="lnr lnr-chevron-up"></span></div>

    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <?php include('header.php'); ?>

        <div class="page-body-wrapper">
            <?php include('sidebaar.php'); ?>

            <div class="page-body">
                <div class="container-fluid">
                    
                    <?php if(!empty($query_error) || !empty($conn->error)): ?>
                        <div class="alert alert-danger mt-4">
                            <h5>Database Query Error!</h5>
                            <p><?php echo htmlspecialchars(!empty($query_error) ? $query_error : $conn->error); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-sm-12">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form action="collection-report.php" method="GET" class="row g-3 align-items-end">
                                        <div class="col-md-3">
                                            <label class="form-label" style="font-weight: 600; color: #444;">Start Date</label>
                                            <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" style="font-weight: 600; color: #444;">End Date</label>
                                            <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" style="font-weight: 600; color: #444;">Collection Type</label>
                                            <select name="type" class="form-select" style="cursor: pointer;">
                                                <option value="all" <?php if($filter_type == 'all') echo 'selected'; ?>>All Types</option>
                                                <option value="emi" <?php if($filter_type == 'emi') echo 'selected'; ?>>Loan EMI</option>
                                                <option value="rd" <?php if($filter_type == 'rd') echo 'selected'; ?>>RD Deposits</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-weight: 600; color: #444;">Specific Customer</label>
                                            <select name="customer_id" class="form-select" style="cursor: pointer;">
                                                <option value="all" <?php if($filter_customer == 'all') echo 'selected'; ?>>-- All My Customers --</option>
                                                <?php 
                                                if ($customers_result && $customers_result->num_rows > 0) {
                                                    while($c = $customers_result->fetch_assoc()) {
                                                        $selected = ($filter_customer == $c['id']) ? 'selected' : '';
                                                        echo "<option value='{$c['id']}' $selected>".htmlspecialchars($c['full_name'])."</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" class="btn btn-primary w-100" style="background-color: #007bff; border-color: #007bff; height: 38px;">
                                                <i class="ri-filter-3-line"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header border-0">
                                    <div class="card-header-title">
                                        <h4>My Collection Report</h4>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive category-table">
                                        <table class="table all-package theme-table" id="main_summary_table">
                                            <thead>
                                                <tr style="background-color: #f8f9fa;">
                                                    <th>Account Type</th>
                                                    <th>Total A/Cs</th>
                                                    <th>Total Amount</th>
                                                    <th>Total Maturity</th>
                                                    <th>Total Remaining</th>
                                                    <th>Default A/Cs</th>
                                                    <th>Default Amount</th>
                                                    <th>Period Collection</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $has_data = false;
                                                $row_counter = 0;
                                                
                                                // Loop through both types manually so we can format them
                                                foreach (['emi-received', 'rd-received'] as $type) {
                                                    if ($summary_stats[$type]['txn_count'] > 0) {
                                                        $has_data = true;
                                                        $row_counter++;
                                                        $stats = $summary_stats[$type];
                                                        
                                                        $display_type = ($type == 'emi-received') ? 'Loan EMI Collections' : 'RD Deposit Collections';
                                                        $badge_color = ($type == 'emi-received') ? '#17a2b8' : '#28a745';
                                                        $icon = ($type == 'emi-received') ? 'ri-bank-line' : 'ri-safe-line';
                                                        
                                                        $row_id = "details_row_" . $row_counter;
                                                        $table_id = "inner_table_" . $row_counter;
                                                ?>
                                                    <tr>
                                                        <td>
                                                            <span style="background-color: <?php echo $badge_color; ?>; color: white; padding: 6px 15px; border-radius: 4px; font-size: 13px; font-weight: bold;">
                                                                <i class="<?php echo $icon; ?> me-1"></i> <?php echo $display_type; ?>
                                                            </span>
                                                        </td>
                                                        <td style="font-weight: bold; font-size: 15px;"><?php echo count($stats['accounts']); ?></td>
                                                        <td style="color: #555; font-weight: 500;">₹<?php echo number_format($stats['total_amt'], 2); ?></td>
                                                        <td style="color: #555; font-weight: 500;">₹<?php echo number_format($stats['total_mat'], 2); ?></td>
                                                        <td style="color: #dc3545; font-weight: bold;">₹<?php echo number_format($stats['total_rem'], 2); ?></td>
                                                        <td style="font-weight: bold; font-size: 15px; color: #dc3545;"><?php echo $stats['default_count']; ?></td>
                                                        <td style="color: #dc3545; font-weight: bold;">₹<?php echo number_format($stats['default_amount'], 2); ?></td>
                                                        <td style="font-weight: bold; color: #28a745; font-size: 15px;">
                                                            ₹<?php echo number_format($stats['collected'], 2); ?><br>
                                                            <small class="text-muted" style="font-weight: normal; font-size: 11px;"><?php echo $stats['txn_count']; ?> Txns Logged</small>
                                                        </td>
                                                        <td>
                                                            <button onclick="toggleDetails(this, '<?php echo $row_id; ?>', '<?php echo $table_id; ?>')" class="btn btn-sm btn-outline-primary" style="font-size: 13px;">
                                                                 <i class="ri-eye-line"></i> View Breakdown
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php 
                                                    } 
                                                }
                                                if (!$has_data) {
                                                    echo "<tr><td colspan='9' class='text-center py-4 text-muted'><strong>No collections logged in this date range.</strong></td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Hidden Details Templates -->
                                    <div style="display: none;">
                                        <?php 
                                        $row_counter = 0;
                                        foreach (['emi-received', 'rd-received'] as $type) {
                                            if ($summary_stats[$type]['txn_count'] > 0) {
                                                $row_counter++;
                                                $row_id = "details_row_" . $row_counter;
                                                $table_id = "inner_table_" . $row_counter;
                                        ?>
                                            <div id="content_<?php echo $row_id; ?>">
                                                <div style="background: #f8f9fa; padding: 20px; border-bottom: 2px solid #ddd;">
                                                    <div style="background: white; padding: 20px; border-radius: 6px; box-shadow: 0 0 10px rgba(0,0,0,0.05);">
                                                        <div class="table-responsive">
                                                            <table id="<?php echo $table_id; ?>" class="table table-sm table-striped table-bordered" style="width:100%;">
                                                                <thead style="background-color: #e9ecef;">
                                                                    <tr>
                                                                        <th style="width: 50px;">S.No.</th>
                                                                        <th>Customer Name</th>
                                                                        <th>Target Amount</th>
                                                                        <th>Running Balance</th>
                                                                        <th>Received Amount</th>
                                                                        <th>Date & Time</th>
                                                                        <th>Description</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    <?php 
                                                                    if (isset($details_map[$type])) {
                                                                        $inner_s_no = 1;
                                                                        foreach ($details_map[$type] as $item) {
                                                                            $date_fmt = date('d M Y, h:i A', strtotime($item['transaction_date']));
                                                                            $name = trim(!empty($item['customer_name']) ? $item['customer_name'] : 'Unknown Customer');
                                                                            $photo = !empty($item['customer_photo']) ? '../uploads/' . $item['customer_photo'] : 'assets/images/default-avatar.png';

                                                                            $total_amt = 0;
                                                                            $remaining_amt = 0;
                                                                            
                                                                            if ($type == 'emi-received') {
                                                                                $total_amt = floatval($item['loan_maturity']);
                                                                                $paid_up_to_this_date = floatval($item['running_loan_paid']);
                                                                                $remaining_amt = $total_amt - $paid_up_to_this_date;
                                                                            } else {
                                                                                $total_amt = floatval($item['rd_principal']);
                                                                                $paid_up_to_this_date = floatval($item['running_rd_paid']);
                                                                                $remaining_amt = $total_amt - $paid_up_to_this_date;
                                                                            }
                                                                    ?>
                                                                        <tr>
                                                                            <td style="font-size: 13px; font-weight: 500;"><?php echo $inner_s_no++; ?></td>
                                                                            <td style="font-size: 13px;">
                                                                                <div style="display: flex; align-items: center; gap: 8px;">
                                                                                    <img src="<?php echo htmlspecialchars($photo); ?>" alt="User" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                                                                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($name); ?></span>
                                                                                </div>
                                                                            </td>
                                                                            <td style="font-size: 13px; color: #555;">₹<?php echo number_format($total_amt, 2); ?></td>
                                                                            <td style="font-size: 13px; color: #dc3545; font-weight: 500;">₹<?php echo number_format(max(0, $remaining_amt), 2); ?></td>
                                                                            <td style="font-size: 14px; font-weight: bold; color: #28a745;">₹<?php echo number_format($item['amount'], 2); ?></td>
                                                                            <td style="font-size: 13px;" data-sort="<?php echo strtotime($item['transaction_date']); ?>"><?php echo $date_fmt; ?></td>
                                                                            <td style="font-size: 13px;"><?php echo htmlspecialchars($item['description']); ?></td>
                                                                        </tr>
                                                                    <?php 
                                                                        }
                                                                    } 
                                                                    ?>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php 
                                            } 
                                        }
                                        ?>
                                    </div>
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
        // Accordion Toggle using DataTable child rows
        function toggleDetails(btn, rowId, tableId) {
            if (typeof $.fn.DataTable === 'undefined') {
                return;
            }

            var table = $('#main_summary_table').DataTable();
            var tr = $(btn).closest('tr');
            var row = table.row(tr);

            if (row.child.isShown()) {
                // This row is already open - close it
                row.child.hide();
                tr.removeClass('shown');
            } else {
                // Close other open details rows first
                table.rows().every(function () {
                    if (this.child.isShown()) {
                        this.child.hide();
                        $(this.node()).removeClass('shown');
                    }
                });

                // Get details content from the hidden container
                var detailsHtml = $('#content_' + rowId).html();
                
                // Show the child row
                row.child(detailsHtml).show();
                tr.addClass('shown');
                
                // Now initialize the inner DataTable
                setTimeout(function() {
                    if (!$.fn.DataTable.isDataTable('#' + tableId)) {
                        $('#' + tableId).DataTable({
                            "destroy": true,
                            "pageLength": 10,
                            "lengthChange": true,
                            "searching": true,
                            "order": [[5, "desc"]], // Auto-sort by Date column
                            "columnDefs": [
                                { "targets": 0, "orderable": false, "searchable": false }
                            ],
                            "dom": "<'row mb-3 align-items-center'<'col-sm-6'l><'col-sm-6 text-end'f>>" +
                                   "<'row'<'col-sm-12'tr>>" +
                                   "<'row mt-3'<'col-sm-5'i><'col-sm-7'p>>",
                            "language": {
                                "search": "Search Text:"
                            }
                        });
                    }
                }, 50); 
            }
        }
    </script>
</body>
</html>