<?php
include('config.php');

// Check for admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Set default filter values
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : ''; 
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$filter_agent = isset($_GET['agent_id']) ? $_GET['agent_id'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$filter_customer = isset($_GET['customer_id']) ? $_GET['customer_id'] : 'all';

// Fetch all agents for the dropdown
$agents_query = "SELECT id, first_name, last_name, username FROM agents ORDER BY first_name ASC";
$agents_result = $conn->query($agents_query);

// Fetch all customers for the dropdown
$customers_query = "SELECT id, full_name FROM customers ORDER BY full_name ASC";
$customers_result = $conn->query($customers_query);

// Build the dynamic WHERE clause
$where_clauses = [];

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

if ($filter_agent !== 'all') {
    $agent_id_safe = intval($filter_agent);
    $where_clauses[] = "t.agent_id = $agent_id_safe";
}

if ($filter_customer !== 'all') {
    $customer_id_safe = intval($filter_customer);
    $where_clauses[] = "(l.customer_id = $customer_id_safe OR rd.customer_id = $customer_id_safe)";
}

$where_sql = implode(' AND ', $where_clauses);

// Fetch ALL Detailed Data (Corrected l.loan_amount instead of principal_amount based on your schema)
$details_query = "
    SELECT 
        t.agent_id, 
        a.first_name, 
        a.last_name, 
        a.username, 
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
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt WHERE wt.loan_id = l.id AND wt.transaction_type = 'emi-received') as loan_overall_paid,
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt WHERE wt.loan_id = t.loan_id AND wt.transaction_type = 'emi-received' AND wt.transaction_date <= t.transaction_date) as running_loan_paid,
        
        COALESCE((rd.deposit_amount * rd.tenure), 0) as rd_principal,
        COALESCE(rd.maturity_amount, 0) as rd_maturity,
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt2 WHERE wt2.rd_id = rd.id AND wt2.transaction_type = 'rd-received') as rd_overall_paid,
        (SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions wt2 WHERE wt2.rd_id = t.rd_id AND wt2.transaction_type = 'rd-received' AND wt2.transaction_date <= t.transaction_date) as running_rd_paid
        
    FROM wallet_transactions t
    JOIN agents a ON t.agent_id = a.id
    LEFT JOIN loans l ON t.loan_id = l.id
    LEFT JOIN recurring_deposits rd ON t.rd_id = rd.id
    LEFT JOIN customers c ON c.id = COALESCE(l.customer_id, rd.customer_id)
    WHERE $where_sql
    ORDER BY a.first_name ASC, t.transaction_date DESC
";
$details_result = $conn->query($details_query);

// Process the data in PHP for 100% accurate deduplication
$details_map = [];
$summary_stats = [];

if ($details_result && $details_result->num_rows > 0) {
    while ($row = $details_result->fetch_assoc()) {
        $aid = $row['agent_id'];
        $type = $row['transaction_type'];
        $key = $aid . '_' . $type;
        
        // Save to Itemized Details Map
        $details_map[$aid][$type][] = $row;
        
        // Initialize Summary Map for this Agent/Type Combo if it doesn't exist
        if (!isset($summary_stats[$key])) {
            $summary_stats[$key] = [
                'agent_id' => $aid,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'username' => $row['username'],
                'type' => $type,
                'txn_count' => 0,
                'collected' => 0,
                'accounts' => [], // Used to track unique accounts
                'total_amt' => 0,
                'total_mat' => 0,
                'total_rem' => 0,
                'default_count' => 0,
                'default_amount' => 0
            ];
        }
        
        // Add Period Transaction Data
        $summary_stats[$key]['txn_count']++;
        $summary_stats[$key]['collected'] += floatval($row['amount']);
        
        // Add Account Data (ONLY ONCE per unique Loan/RD so we don't multiply principal amounts)
        if ($type === 'emi-received' && !empty($row['loan_id'])) {
            if (!in_array($row['loan_id'], $summary_stats[$key]['accounts'])) {
                $summary_stats[$key]['accounts'][] = $row['loan_id'];
                $summary_stats[$key]['total_amt'] += floatval($row['loan_principal']);
                $summary_stats[$key]['total_mat'] += floatval($row['loan_maturity']);
                $rem = floatval($row['loan_maturity']) - floatval($row['loan_overall_paid']);
                $summary_stats[$key]['total_rem'] += max(0, $rem);
                
                // Calculate Loan Default Amount
                $default_amt = 0;
                if ($row['loan_status'] === 'defaulted') {
                    $default_amt = max(0, $rem);
                } else if (in_array($row['loan_status'], ['active', 'approved'])) {
                    $approval_date_str = $row['loan_approval_date'];
                    if (!empty($approval_date_str) && $approval_date_str !== '0000-00-00' && $approval_date_str !== '0000-00-00 00:00:00') {
                        try {
                            $approval_date = new DateTime($approval_date_str);
                            $approval_date->setTime(0, 0, 0);
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);

                            if ($today > $approval_date) {
                                $interval_str = '1 month';
                                switch (strtolower($row['loan_repayment_cycle'])) {
                                    case 'daily': $interval_str = '1 day'; break;
                                    case 'weekly': $interval_str = '1 week'; break;
                                    case 'monthly': $interval_str = '1 month'; break;
                                    case 'quarterly': $interval_str = '3 months'; break;
                                    case 'half-yearly': $interval_str = '6 months'; break;
                                    case 'annually': $interval_str = '1 year'; break;
                                }
                                
                                $installments_due = 0;
                                $temp_date = clone $approval_date;
                                while ($temp_date < $today && $installments_due < (int)$row['loan_tenure']) {
                                    $temp_date->modify('+' . $interval_str);
                                    if ($temp_date <= $today) {
                                        $installments_due++;
                                    }
                                }

                                $expected_paid = $installments_due * (float)$row['loan_monthly_installment'];
                                $overdue = max(0.0, $expected_paid - floatval($row['loan_overall_paid']));
                                $default_amt = min($overdue, $rem);
                            }
                        } catch (Exception $e) {
                            $default_amt = 0;
                        }
                    }
                }
                
                if ($default_amt > 0) {
                    $summary_stats[$key]['default_count']++;
                    $summary_stats[$key]['default_amount'] += $default_amt;
                }
            }
        } elseif ($type === 'rd-received' && !empty($row['rd_id'])) {
            if (!in_array($row['rd_id'], $summary_stats[$key]['accounts'])) {
                $summary_stats[$key]['accounts'][] = $row['rd_id'];
                $summary_stats[$key]['total_amt'] += floatval($row['rd_principal']);
                $summary_stats[$key]['total_mat'] += floatval($row['rd_maturity']);
                $rem = floatval($row['rd_principal']) - floatval($row['rd_overall_paid']);
                $summary_stats[$key]['total_rem'] += max(0, $rem);
                
                // Calculate RD Default Amount
                $default_amt = 0;
                if (in_array($row['rd_status'], ['active', 'approved'])) {
                    $start_date_str = $row['rd_start_date'];
                    if (!empty($start_date_str) && $start_date_str !== '0000-00-00' && $start_date_str !== '0000-00-00 00:00:00') {
                        try {
                            $rd_start_dt = new DateTime($start_date_str);
                            $rd_start_dt->setTime(0, 0, 0);
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);

                            if ($today > $rd_start_dt) {
                                $interval_str = '1 month';
                                switch (strtolower($row['rd_repayment_cycle'])) {
                                    case 'daily': $interval_str = '1 day'; break;
                                    case 'weekly': $interval_str = '1 week'; break;
                                    case 'monthly': $interval_str = '1 month'; break;
                                    case 'quarterly': $interval_str = '3 months'; break;
                                    case 'half-yearly': $interval_str = '6 months'; break;
                                    case 'annually': $interval_str = '1 year'; break;
                                }

                                $installments_due = 0;
                                $temp_date = clone $rd_start_dt;
                                while ($temp_date < $today && $installments_due < (int)$row['rd_tenure']) {
                                    $temp_date->modify('+' . $interval_str);
                                    if ($temp_date <= $today) {
                                        $installments_due++;
                                    }
                                }

                                $expected_paid = $installments_due * (float)$row['rd_deposit_amount'];
                                $overdue = max(0.0, $expected_paid - floatval($row['rd_overall_paid']));
                                $default_amt = min($overdue, $rem);
                            }
                        } catch (Exception $e) {
                            $default_amt = 0;
                        }
                    }
                }
                
                if ($default_amt > 0) {
                    $summary_stats[$key]['default_count']++;
                    $summary_stats[$key]['default_amount'] += $default_amt;
                }
            }
        }
    }
}
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
                    
                    <?php if(!empty($conn->error)): ?>
                        <div class="alert alert-danger mt-4">
                            <h5>Database Error!</h5>
                            <p><?php echo $conn->error; ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="row mt-4">
                        <div class="col-sm-12">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <form method="GET" action="collection-report.php" class="row g-3 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-size: 13px;">Start Date</label>
                                            <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo $start_date; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-size: 13px;">End Date</label>
                                            <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo $end_date; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-size: 13px;">Agent</label>
                                            <select name="agent_id" class="form-select form-select-sm">
                                                <option value="all" <?php if($filter_agent == 'all') echo 'selected'; ?>>All Agents</option>
                                                <?php 
                                                if ($agents_result && $agents_result->num_rows > 0) {
                                                    while($a = $agents_result->fetch_assoc()) {
                                                        $selected = ($filter_agent == $a['id']) ? 'selected' : '';
                                                        echo "<option value='{$a['id']}' $selected>{$a['first_name']} {$a['last_name']}</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-size: 13px;">Collection Type</label>
                                            <select name="type" class="form-select form-select-sm">
                                                <option value="all" <?php if($filter_type == 'all') echo 'selected'; ?>>All Collections</option>
                                                <option value="emi" <?php if($filter_type == 'emi') echo 'selected'; ?>>Loan EMI</option>
                                                <option value="rd" <?php if($filter_type == 'rd') echo 'selected'; ?>>RD Deposits</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label" style="font-size: 13px;">Customer</label>
                                            <select name="customer_id" class="form-select form-select-sm">
                                                <option value="all" <?php if($filter_customer == 'all') echo 'selected'; ?>>All Customers</option>
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
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-primary btn-sm w-100" style="background-color: #007bff; border-color: #007bff; height: 31px;">
                                                <i class="ri-filter-3-line"></i> Filter
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header border-0">
                                    <div class="card-header-title">
                                        <h4>Cumulative Collection Report</h4>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive category-table">
                                        <table class="table all-package theme-table" id="main_summary_table">
                                            <thead>
                                                <tr style="background-color: #f8f9fa;">
                                                    <th>Agent Name</th>
                                                    <th>Type</th>
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
                                                if (!empty($summary_stats)) {
                                                    $row_counter = 0;
                                                    foreach ($summary_stats as $key => $stats) {
                                                        $row_counter++;
                                                        $aid = $stats['agent_id'];
                                                        $type = $stats['type'];
                                                        
                                                        $display_type = ($type == 'emi-received') ? 'Loan EMI' : 'RD Deposit';
                                                        $badge_color = ($type == 'emi-received') ? '#17a2b8' : '#28a745';
                                                        
                                                        $row_id = "details_row_" . $aid . "_" . $row_counter;
                                                        $table_id = "inner_table_" . $aid . "_" . $row_counter;
                                                ?>
                                                    <tr>
                                                        <td style="font-weight: 500;">
                                                            <?php echo $stats['first_name'] . ' ' . $stats['last_name']; ?><br>
                                                            <small class="text-muted">(<?php echo $stats['username']; ?>)</small>
                                                        </td>
                                                        <td>
                                                            <span style="background-color: <?php echo $badge_color; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: bold;">
                                                                <?php echo $display_type; ?>
                                                            </span>
                                                        </td>
                                                        <td style="font-weight: bold; font-size: 14px;"><?php echo count($stats['accounts']); ?></td>
                                                        <td style="color: #555; font-weight: 500;">₹<?php echo number_format($stats['total_amt'], 2); ?></td>
                                                        <td style="color: #555; font-weight: 500;">₹<?php echo number_format($stats['total_mat'], 2); ?></td>
                                                        <td style="color: #dc3545; font-weight: bold;">₹<?php echo number_format($stats['total_rem'], 2); ?></td>
                                                        <td style="font-weight: bold; font-size: 14px; color: #dc3545;"><?php echo $stats['default_count']; ?></td>
                                                        <td style="color: #dc3545; font-weight: bold;">₹<?php echo number_format($stats['default_amount'], 2); ?></td>
                                                        <td style="font-weight: bold; color: #28a745;">
                                                            ₹<?php echo number_format($stats['collected'], 2); ?><br>
                                                            <small class="text-muted" style="font-weight: normal; font-size: 11px;"><?php echo $stats['txn_count']; ?> Txns Logged</small>
                                                        </td>
                                                        <td>
                                                            <button onclick="toggleDetails(this, '<?php echo $row_id; ?>', '<?php echo $table_id; ?>')" class="btn btn-sm btn-outline-primary" style="font-size: 12px;">
                                                                <i class="ri-eye-line"></i> View Details
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php 
                                                    } 
                                                } else {
                                                    echo "<tr><td colspan='10' class='text-center py-4 text-muted'>No collections found for the selected dates and filters.</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Hidden Details Templates -->
                                    <div style="display: none;">
                                        <?php 
                                        if (!empty($summary_stats)) {
                                            $row_counter = 0;
                                            foreach ($summary_stats as $key => $stats) {
                                                $row_counter++;
                                                $aid = $stats['agent_id'];
                                                $type = $stats['type'];
                                                $row_id = "details_row_" . $aid . "_" . $row_counter;
                                                $table_id = "inner_table_" . $aid . "_" . $row_counter;
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
                                                                    if (isset($details_map[$aid][$type])) {
                                                                        $inner_s_no = 1;
                                                                        foreach ($details_map[$aid][$type] as $item) {
                                                                            $date_fmt = date('d M Y, h:i A', strtotime($item['transaction_date']));
                                                                            $name = trim(!empty($item['customer_name']) ? $item['customer_name'] : 'Unknown Customer');
                                                                            $photo = !empty($item['customer_photo']) ? '../Agents/upload/customers/avatars/' . $item['customer_photo'] : 'assets/images/default-avatar.png';

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
                                                                            <td style="font-size: 13px; font-weight: bold; color: #28a745;">₹<?php echo number_format($item['amount'], 2); ?></td>
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
                            "lengthChange": false, 
                            "searching": false,    
                            "order": [[5, "desc"]], 
                            "columnDefs": [
                                { "targets": 0, "orderable": false, "searchable": false }
                            ],
                            "dom": "t<'row mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
                        });
                    }
                }, 50); 
            }
        }
    </script>
</body>
</html>