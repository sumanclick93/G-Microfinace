<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$today = date('Y-m-d');
$filter_agent_id    = isset($_GET['agent_id']) ? intval($_GET['agent_id']) : 0;
$filter_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$filter_start_date  = !empty($_GET['start_date']) ? $_GET['start_date'] : $today;
$filter_end_date    = !empty($_GET['end_date'])   ? $_GET['end_date']   : $today;

// All agents for dropdown
$all_agents = [];
$agents_res = $conn->query("SELECT id, first_name, last_name, username FROM agents WHERE is_active = 1 ORDER BY first_name ASC, last_name ASC");
if ($agents_res) {
    while ($row = $agents_res->fetch_assoc()) {
        $all_agents[] = $row;
    }
}

// Resolve selected agent
$agent_id = 0;
$agent_name = '';
if ($filter_agent_id > 0) {
    $astmt = $conn->prepare("SELECT id, first_name, last_name, username FROM agents WHERE id = ? AND is_active = 1 LIMIT 1");
    $astmt->bind_param("i", $filter_agent_id);
    $astmt->execute();
    $ares = $astmt->get_result();
    if ($arow = $ares->fetch_assoc()) {
        $agent_id = (int)$arow['id'];
        $agent_name = trim($arow['first_name'] . ' ' . $arow['last_name']);
        if ($agent_name === '') {
            $agent_name = $arow['username'];
        }
    }
    $astmt->close();
}

// Sanitize dates
$start_dt = DateTime::createFromFormat('Y-m-d', $filter_start_date);
$end_dt   = DateTime::createFromFormat('Y-m-d', $filter_end_date);
if (!$start_dt) { $start_dt = new DateTime($today); $filter_start_date = $today; }
if (!$end_dt)   { $end_dt   = new DateTime($today); $filter_end_date   = $today; }
if ($end_dt < $start_dt) {
    $tmp = $start_dt; $start_dt = $end_dt; $end_dt = $tmp;
    $filter_start_date = $start_dt->format('Y-m-d');
    $filter_end_date   = $end_dt->format('Y-m-d');
}

$day_span = (int)$start_dt->diff($end_dt)->days + 1;
$range_capped = false;
if ($day_span > 31) {
    $end_dt = clone $start_dt;
    $end_dt->modify('+30 days');
    $filter_end_date = $end_dt->format('Y-m-d');
    $day_span = 31;
    $range_capped = true;
}

$dates = [];
$cursor = clone $start_dt;
while ($cursor <= $end_dt) {
    $dates[] = $cursor->format('Y-m-d');
    $cursor->modify('+1 day');
}

$customers = [];
$all_customers = [];
$grand_loan = 0.0;
$grand_rd   = 0.0;
$date_totals = [];
foreach ($dates as $d) {
    $date_totals[$d] = ['loan' => 0.0, 'rd' => 0.0];
}

if ($agent_id > 0) {
    // Customers belonging to selected agent
    $cust_sql = "SELECT id, full_name FROM customers WHERE agent_id = ?";
    if ($filter_customer_id > 0) {
        $cust_sql .= " AND id = ?";
    }
    $cust_sql .= " ORDER BY full_name ASC";
    $cust_stmt = $conn->prepare($cust_sql);
    if ($filter_customer_id > 0) {
        $cust_stmt->bind_param("ii", $agent_id, $filter_customer_id);
    } else {
        $cust_stmt->bind_param("i", $agent_id);
    }
    $cust_stmt->execute();
    $cust_res = $cust_stmt->get_result();
    while ($row = $cust_res->fetch_assoc()) {
        $customers[(int)$row['id']] = [
            'id'   => (int)$row['id'],
            'name' => $row['full_name'],
            'days' => []
        ];
    }
    $cust_stmt->close();

    foreach ($customers as $cid => &$c) {
        foreach ($dates as $d) {
            $c['days'][$d] = ['loan' => 0.0, 'rd' => 0.0];
        }
    }
    unset($c);

    $start_sql = $filter_start_date . ' 00:00:00';
    $end_sql   = $filter_end_date   . ' 23:59:59';

    $loan_sql = "
        SELECT c.id AS customer_id, DATE(lpc.payment_date) AS pay_date, COALESCE(SUM(lpc.amount_paid), 0) AS total
        FROM loan_payments_collection lpc
        JOIN loans l ON lpc.loan_id = l.id
        JOIN customers c ON l.customer_id = c.id
        WHERE lpc.collected_by_agent_id = ?
          AND c.agent_id = ?
          AND lpc.payment_date BETWEEN ? AND ?
    ";
    if ($filter_customer_id > 0) {
        $loan_sql .= " AND c.id = ?";
    }
    $loan_sql .= " GROUP BY c.id, DATE(lpc.payment_date)";

    if ($filter_customer_id > 0) {
        $loan_stmt = $conn->prepare($loan_sql);
        $loan_stmt->bind_param("iissi", $agent_id, $agent_id, $start_sql, $end_sql, $filter_customer_id);
    } else {
        $loan_stmt = $conn->prepare($loan_sql);
        $loan_stmt->bind_param("iiss", $agent_id, $agent_id, $start_sql, $end_sql);
    }
    $loan_stmt->execute();
    $loan_res = $loan_stmt->get_result();
    while ($row = $loan_res->fetch_assoc()) {
        $cid = (int)$row['customer_id'];
        $d   = $row['pay_date'];
        if (isset($customers[$cid]['days'][$d])) {
            $customers[$cid]['days'][$d]['loan'] = (float)$row['total'];
        }
    }
    $loan_stmt->close();

    $rd_sql = "
        SELECT c.id AS customer_id, DATE(rpc.payment_date) AS pay_date, COALESCE(SUM(rpc.amount_paid), 0) AS total
        FROM rd_payments_collection rpc
        JOIN recurring_deposits rd ON rpc.rd_id = rd.id
        JOIN customers c ON rd.customer_id = c.id
        WHERE rpc.collected_by_agent_id = ?
          AND c.agent_id = ?
          AND rpc.payment_date BETWEEN ? AND ?
    ";
    if ($filter_customer_id > 0) {
        $rd_sql .= " AND c.id = ?";
    }
    $rd_sql .= " GROUP BY c.id, DATE(rpc.payment_date)";

    if ($filter_customer_id > 0) {
        $rd_stmt = $conn->prepare($rd_sql);
        $rd_stmt->bind_param("iissi", $agent_id, $agent_id, $start_sql, $end_sql, $filter_customer_id);
    } else {
        $rd_stmt = $conn->prepare($rd_sql);
        $rd_stmt->bind_param("iiss", $agent_id, $agent_id, $start_sql, $end_sql);
    }
    $rd_stmt->execute();
    $rd_res = $rd_stmt->get_result();
    while ($row = $rd_res->fetch_assoc()) {
        $cid = (int)$row['customer_id'];
        $d   = $row['pay_date'];
        if (isset($customers[$cid]['days'][$d])) {
            $customers[$cid]['days'][$d]['rd'] = (float)$row['total'];
        }
    }
    $rd_stmt->close();

    // Keep only customers with collections
    $customers = array_filter($customers, static function (array $c): bool {
        foreach ($c['days'] as $day) {
            if ((float)($day['loan'] ?? 0) > 0 || (float)($day['rd'] ?? 0) > 0) {
                return true;
            }
        }
        return false;
    });

    // Keep every date in the selected range as its own Loan/RD column group.
    // (Do not drop empty days — mirrors the collection-agent pivot layout.)

    // Customer dropdown for selected agent
    $all_cust = $conn->prepare("SELECT id, full_name FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
    $all_cust->bind_param("i", $agent_id);
    $all_cust->execute();
    $all_res = $all_cust->get_result();
    while ($row = $all_res->fetch_assoc()) {
        $all_customers[] = $row;
    }
    $all_cust->close();

    $date_totals = [];
    foreach ($dates as $d) {
        $date_totals[$d] = ['loan' => 0.0, 'rd' => 0.0];
    }
    foreach ($customers as $c) {
        foreach ($dates as $d) {
            $loan = (float)($c['days'][$d]['loan'] ?? 0);
            $rd   = (float)($c['days'][$d]['rd'] ?? 0);
            $date_totals[$d]['loan'] += $loan;
            $date_totals[$d]['rd']   += $rd;
            $grand_loan += $loan;
            $grand_rd   += $rd;
        }
    }
}

function fmt_money($n) {
    return number_format((float)$n, 2);
}
function fmt_date_label($ymd) {
    return date('d M Y', strtotime($ymd));
}

// Row Total is always the last column group when more than one date is selected
$show_row_total = count($dates) > 1;
$col_count = 1 + (count($dates) * 2) + ($show_row_total ? 2 : 0);
$has_agent = $agent_id > 0;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include('head.php'); ?>
<style>
        .ch-filter-bar {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.25rem;
        }
        .ch-filter-bar .form-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 0.35rem;
        }
        .ch-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        #history_table {
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        #history_table thead th {
            background: #e9ecef;
            text-align: center;
            vertical-align: middle;
            white-space: nowrap;
            border: 1px solid #dee2e6;
            padding: 0.55rem 0.65rem;
            font-weight: 600;
        }
        #history_table thead th.customer-col {
            text-align: left;
            min-width: 180px;
            position: sticky;
            left: 0;
            z-index: 2;
            background: #e9ecef;
        }
        #history_table tbody td {
            border: 1px solid #dee2e6;
            padding: 0.5rem 0.65rem;
            vertical-align: middle;
            text-align: right;
            white-space: nowrap;
        }
        #history_table tbody td.customer-col {
            text-align: left;
            font-weight: 600;
            position: sticky;
            left: 0;
            background: #fff;
            z-index: 1;
        }
        #history_table tfoot td {
            border: 1px solid #dee2e6;
            padding: 0.55rem 0.65rem;
            font-weight: 700;
            background: #f1f3f5;
            text-align: right;
        }
        #history_table tfoot td.customer-col {
            text-align: left;
            position: sticky;
            left: 0;
            background: #f1f3f5;
            z-index: 1;
        }
        .amt-loan { color: #0d6efd; }
        .amt-rd { color: #198754; }
        .amt-zero { color: #adb5bd; }
        .date-group-th { background: #dde7f5 !important; }
        .btn-whatsapp {
            background: #25D366;
            border-color: #25D366;
            color: #fff;
            font-weight: 600;
        }
        .btn-whatsapp:hover {
            background: #1ebe57;
            border-color: #1ebe57;
            color: #fff;
        }
        .ch-summary-chip {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .chip-loan { background: #e7f1ff; color: #0d6efd; }
        .chip-rd { background: #e8f8ef; color: #198754; }
        .chip-total { background: #fff3cd; color: #856404; }

        /* Mobile customer cards */
        .ch-cards-wrap { display: none; }
        .ch-mobile-toggle { display: none; margin-bottom: 12px; }
        .ch-cust-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            margin-bottom: 14px;
            overflow: hidden;
        }
        .ch-cust-card-header {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 14px;
            font-weight: 700;
            font-size: 0.95rem;
            color: #1e293b;
        }
        .ch-date-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 12px 14px;
            border-bottom: 1px dashed #e9ecef;
        }
        .ch-date-row:last-child { border-bottom: none; }
        .ch-date-label {
            font-size: 0.78rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            width: 100%;
            margin-bottom: 2px;
        }
        .ch-date-amts {
            display: flex;
            gap: 16px;
            width: 100%;
            justify-content: space-between;
        }
        .ch-amt-block { flex: 1; }
        .ch-amt-block .lbl {
            display: block;
            font-size: 0.7rem;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
        }
        .ch-amt-block .val {
            font-size: 0.95rem;
            font-weight: 700;
        }
        .ch-cust-card-footer {
            background: #f1f5f9;
            padding: 10px 14px;
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 700;
        }

        @media (max-width: 768px) {
            .ch-filter-bar .row > [class*="col-"] { margin-bottom: 0.75rem; }
            #history_table { font-size: 0.8rem; }
            .ch-mobile-toggle { display: flex; justify-content: flex-end; }
            .ch-cards-wrap { display: block; }
            .ch-table-wrap { display: none; }
            body.ch-show-table .ch-cards-wrap { display: none; }
            body.ch-show-table .ch-table-wrap { display: block; }
        }
        @media (min-width: 769px) {
            .ch-cards-wrap { display: none !important; }
            .ch-table-wrap { display: block !important; }
            .ch-mobile-toggle { display: none !important; }
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
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header border-0">
                                    <div class="card-header-title">
                                        <h4>Collection History</h4>
                                        <p class="text-muted mb-0" style="font-size: 13px;">
                                            Select an agent to view Loan &amp; RD collections by customer and date.
                                            <?php if ($has_agent): ?>
                                                Showing: <strong><?php echo htmlspecialchars($agent_name); ?></strong>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="card-body">

                                    <?php if ($range_capped): ?>
                                    <div class="alert alert-warning py-2">
                                        Date range limited to <strong>31 days</strong> for readability. Adjust Start Date if needed.
                                    </div>
                                    <?php endif; ?>

                                    <!-- Filters -->
                                    <form method="GET" action="collection-history.php" class="ch-filter-bar" id="historyFilterForm">
                                        <div class="row align-items-end g-2">
                                            <div class="col-md-3 col-sm-6">
                                                <label class="form-label" for="agent_id">Agent</label>
                                                <select name="agent_id" id="agent_id" class="form-select form-select-sm" required>
                                                    <option value="">-- Select Agent --</option>
                                                    <?php foreach ($all_agents as $ag):
                                                        $ag_label = trim($ag['first_name'] . ' ' . $ag['last_name']);
                                                        if ($ag_label === '') { $ag_label = $ag['username']; }
                                                        $ag_label .= ' (' . $ag['username'] . ')';
                                                    ?>
                                                    <option value="<?php echo (int)$ag['id']; ?>" <?php echo $filter_agent_id == $ag['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($ag_label); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 col-sm-6">
                                                <label class="form-label" for="customer_id">Customer Name</label>
                                                <select name="customer_id" id="customer_id" class="form-select form-select-sm" <?php echo !$has_agent ? 'disabled' : ''; ?>>
                                                    <option value="0">-- All Customers --</option>
                                                    <?php foreach ($all_customers as $ac): ?>
                                                    <option value="<?php echo (int)$ac['id']; ?>" <?php echo $filter_customer_id == $ac['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($ac['full_name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 col-sm-6">
                                                <label class="form-label" for="start_date">Start Date</label>
                                                <input type="date" class="form-control form-control-sm" name="start_date" id="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>" required>
                                            </div>
                                            <div class="col-md-2 col-sm-6">
                                                <label class="form-label" for="end_date">End Date</label>
                                                <input type="date" class="form-control form-control-sm" name="end_date" id="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>" required>
                                            </div>
                                            <div class="col-md-2 col-sm-12 d-flex flex-wrap gap-2">
                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    <i class="ri-filter-3-line"></i> Apply
                                                </button>
                                                <a href="collection-history.php<?php echo $has_agent ? ('?agent_id=' . (int)$agent_id) : ''; ?>" class="btn btn-outline-secondary btn-sm">
                                                    Today
                                                </a>
                                            </div>
                                            <div class="col-12 d-flex flex-wrap gap-2 mt-1">
                                                <button type="button" class="btn btn-whatsapp btn-sm" id="btnShareWhatsApp" <?php echo !$has_agent ? 'disabled' : ''; ?>>
                                                    <i class="ri-whatsapp-line"></i> Share via WhatsApp
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm" id="btnDownloadPdf" <?php echo !$has_agent ? 'disabled' : ''; ?>>
                                                    <i class="ri-file-pdf-line"></i> Download PDF
                                                </button>
                                            </div>
                                        </div>
                                    </form>

                                    <?php if (!$has_agent): ?>
                                    <div class="alert alert-info mb-0">
                                        Please select an <strong>Agent</strong> above to view collection history.
                                    </div>
                                    <?php else: ?>

                                    <div class="mb-3">
                                        <span class="ch-summary-chip chip-loan">Loan Total: ₹<?php echo fmt_money($grand_loan); ?></span>
                                        <span class="ch-summary-chip chip-rd">RD Total: ₹<?php echo fmt_money($grand_rd); ?></span>
                                        <span class="ch-summary-chip chip-total">Grand Total: ₹<?php echo fmt_money($grand_loan + $grand_rd); ?></span>
                                    </div>

                                    <div class="ch-mobile-toggle">
                                        <button type="button" class="btn btn-sm btn-primary" id="btnToggleHistoryView">
                                            <i class="ri-layout-grid-line"></i> <span>Card View (Active)</span>
                                        </button>
                                    </div>

                                    <!-- Mobile: one card per customer (all dates inside) -->
                                    <div class="ch-cards-wrap" id="historyCardsWrap">
                                        <?php if (count($customers) === 0): ?>
                                            <div class="text-muted text-center py-4">No collections found for the selected filters.</div>
                                        <?php else: ?>
                                            <?php foreach ($customers as $c):
                                                $card_loan = 0.0;
                                                $card_rd = 0.0;
                                            ?>
                                            <div class="ch-cust-card">
                                                <div class="ch-cust-card-header">
                                                    <?php echo htmlspecialchars($c['name']); ?>
                                                </div>
                                                <?php foreach ($dates as $d):
                                                    $loan = $c['days'][$d]['loan'];
                                                    $rd   = $c['days'][$d]['rd'];
                                                    $card_loan += $loan;
                                                    $card_rd   += $rd;
                                                ?>
                                                <div class="ch-date-row">
                                                    <div class="ch-date-label"><?php echo fmt_date_label($d); ?></div>
                                                    <div class="ch-date-amts">
                                                        <div class="ch-amt-block">
                                                            <span class="lbl">Loan</span>
                                                            <span class="val <?php echo $loan > 0 ? 'amt-loan' : 'amt-zero'; ?>">₹<?php echo fmt_money($loan); ?></span>
                                                        </div>
                                                        <div class="ch-amt-block" style="text-align:right;">
                                                            <span class="lbl">RD</span>
                                                            <span class="val <?php echo $rd > 0 ? 'amt-rd' : 'amt-zero'; ?>">₹<?php echo fmt_money($rd); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                                <?php if (count($dates) > 1): ?>
                                                <div class="ch-cust-card-footer">
                                                    <span class="amt-loan">Loan: ₹<?php echo fmt_money($card_loan); ?></span>
                                                    <span class="amt-rd">RD: ₹<?php echo fmt_money($card_rd); ?></span>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Desktop / optional table view -->
                                    <div class="ch-table-wrap" id="historyTableWrap">
                                        <table class="table table-bordered table-sm" id="history_table">
                                            <thead>
                                                <tr>
                                                    <th class="customer-col" rowspan="2">Customer Name</th>
                                                    <?php foreach ($dates as $d): ?>
                                                    <th class="date-group-th" colspan="2"><?php echo fmt_date_label($d); ?></th>
                                                    <?php endforeach; ?>
                                                    <?php if ($show_row_total): ?>
                                                    <th class="date-group-th" colspan="2">Row Total</th>
                                                    <?php endif; ?>
                                                </tr>
                                                <tr>
                                                    <?php foreach ($dates as $d): ?>
                                                    <th>Loan</th>
                                                    <th>RD</th>
                                                    <?php endforeach; ?>
                                                    <?php if ($show_row_total): ?>
                                                    <th>Total Loan</th>
                                                    <th>Total RD</th>
                                                    <?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (count($customers) === 0): ?>
                                                <tr>
                                                    <td class="customer-col text-muted" colspan="<?php echo $col_count; ?>">
                                                        No collections found for the selected filters.
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                    <?php foreach ($customers as $c):
                                                        $row_loan = 0.0;
                                                        $row_rd = 0.0;
                                                        foreach ($dates as $d) {
                                                            $row_loan += (float)$c['days'][$d]['loan'];
                                                            $row_rd   += (float)$c['days'][$d]['rd'];
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td class="customer-col"><?php echo htmlspecialchars($c['name']); ?></td>
                                                        <?php foreach ($dates as $d):
                                                            $loan = $c['days'][$d]['loan'];
                                                            $rd   = $c['days'][$d]['rd'];
                                                        ?>
                                                        <td class="<?php echo $loan > 0 ? 'amt-loan' : 'amt-zero'; ?>">
                                                            ₹<?php echo fmt_money($loan); ?>
                                                        </td>
                                                        <td class="<?php echo $rd > 0 ? 'amt-rd' : 'amt-zero'; ?>">
                                                            ₹<?php echo fmt_money($rd); ?>
                                                        </td>
                                                        <?php endforeach; ?>
                                                        <?php if ($show_row_total): ?>
                                                        <td class="<?php echo $row_loan > 0 ? 'amt-loan' : 'amt-zero'; ?>">
                                                            ₹<?php echo fmt_money($row_loan); ?>
                                                        </td>
                                                        <td class="<?php echo $row_rd > 0 ? 'amt-rd' : 'amt-zero'; ?>">
                                                            ₹<?php echo fmt_money($row_rd); ?>
                                                        </td>
                                                        <?php endif; ?>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                            <?php if (count($customers) > 0): ?>
                                            <tfoot>
                                                <tr>
                                                    <td class="customer-col">Column Total</td>
                                                    <?php foreach ($dates as $d): ?>
                                                    <td class="amt-loan">₹<?php echo fmt_money($date_totals[$d]['loan']); ?></td>
                                                    <td class="amt-rd">₹<?php echo fmt_money($date_totals[$d]['rd']); ?></td>
                                                    <?php endforeach; ?>
                                                    <?php if ($show_row_total): ?>
                                                    <td class="amt-loan">₹<?php echo fmt_money($grand_loan); ?></td>
                                                    <td class="amt-rd">₹<?php echo fmt_money($grand_rd); ?></td>
                                                    <?php endif; ?>
                                                </tr>
                                            </tfoot>
                                            <?php endif; ?>
                                        </table>
                                    </div>

                                    <?php endif; /* has_agent */ ?>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('footer.php'); ?>
            </div>
        </div>
    </div>

    <!-- jsPDF + AutoTable (CDN) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <script>
    (function () {
        var reportMeta = {
            agentName: <?php echo json_encode($agent_name); ?>,
            startDate: <?php echo json_encode($filter_start_date); ?>,
            endDate: <?php echo json_encode($filter_end_date); ?>,
            customerFilter: <?php echo json_encode($filter_customer_id > 0
                ? ($customers[$filter_customer_id]['name'] ?? 'Selected')
                : 'All Customers'); ?>,
            grandLoan: <?php echo json_encode((float)$grand_loan); ?>,
            grandRd: <?php echo json_encode((float)$grand_rd); ?>,
            dates: <?php echo json_encode($dates); ?>,
            showRowTotal: <?php echo $show_row_total ? 'true' : 'false'; ?>,
            rows: <?php
                $js_rows = [];
                foreach ($customers as $c) {
                    $cells = [];
                    $row_loan = 0.0;
                    $row_rd = 0.0;
                    foreach ($dates as $d) {
                        $loan = round((float)$c['days'][$d]['loan'], 2);
                        $rd   = round((float)$c['days'][$d]['rd'], 2);
                        $cells[] = $loan;
                        $cells[] = $rd;
                        $row_loan += $loan;
                        $row_rd   += $rd;
                    }
                    $js_rows[] = [
                        'name' => $c['name'],
                        'cells' => $cells,
                        'rowLoan' => round($row_loan, 2),
                        'rowRd' => round($row_rd, 2),
                    ];
                }
                echo json_encode($js_rows);
            ?>
        };

        function formatINR(n) {
            return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function buildPdf() {
            var jsPDF = window.jspdf.jsPDF;
            var orientation = reportMeta.dates.length > 3 ? 'landscape' : 'portrait';
            var doc = new jsPDF({ orientation: orientation, unit: 'pt', format: 'a4' });

            var title = 'Collection History Report';
            var subtitle = 'Agent: ' + reportMeta.agentName
                + '  |  ' + reportMeta.startDate + ' to ' + reportMeta.endDate
                + '  |  ' + reportMeta.customerFilter;

            doc.setFontSize(14);
            doc.setFont(undefined, 'bold');
            doc.text(title, 40, 36);
            doc.setFontSize(9);
            doc.setFont(undefined, 'normal');
            doc.text(subtitle, 40, 52);
            doc.text(
                'Loan Total: ' + formatINR(reportMeta.grandLoan)
                + '   RD Total: ' + formatINR(reportMeta.grandRd)
                + '   Grand: ' + formatINR(reportMeta.grandLoan + reportMeta.grandRd),
                40, 66
            );

            // Header row 1 (date groups) + row 2 (Loan/RD)
            var headRow1 = [{ content: 'Customer Name', rowSpan: 2 }];
            reportMeta.dates.forEach(function (d) {
                var label = d; // Y-m-d compact for PDF width
                try {
                    var parts = d.split('-');
                    label = parts[2] + '/' + parts[1];
                } catch (e) {}
                headRow1.push({ content: label, colSpan: 2, styles: { halign: 'center' } });
            });
            if (reportMeta.showRowTotal) {
                headRow1.push({ content: 'Row Total', colSpan: 2, styles: { halign: 'center' } });
            }

            var headRow2 = [];
            reportMeta.dates.forEach(function () {
                headRow2.push('Loan');
                headRow2.push('RD');
            });
            if (reportMeta.showRowTotal) {
                headRow2.push('Total Loan');
                headRow2.push('Total RD');
            }

            var body = reportMeta.rows.map(function (r) {
                var row = [r.name].concat(r.cells.map(function (v) {
                    return Number(v).toFixed(2);
                }));
                if (reportMeta.showRowTotal) {
                    row.push(Number(r.rowLoan || 0).toFixed(2));
                    row.push(Number(r.rowRd || 0).toFixed(2));
                }
                return row;
            });

            // Footer totals
            var foot = ['Column Total'];
            reportMeta.dates.forEach(function (d, idx) {
                var loanSum = 0, rdSum = 0;
                reportMeta.rows.forEach(function (r) {
                    loanSum += Number(r.cells[idx * 2] || 0);
                    rdSum   += Number(r.cells[idx * 2 + 1] || 0);
                });
                foot.push(loanSum.toFixed(2));
                foot.push(rdSum.toFixed(2));
            });
            if (reportMeta.showRowTotal) {
                foot.push(Number(reportMeta.grandLoan).toFixed(2));
                foot.push(Number(reportMeta.grandRd).toFixed(2));
            }
            body.push(foot);

            doc.autoTable({
                startY: 78,
                head: [headRow1, headRow2],
                body: body,
                theme: 'grid',
                styles: { fontSize: 7, cellPadding: 3, halign: 'right' },
                headStyles: { fillColor: [233, 236, 239], textColor: [33, 37, 41], fontStyle: 'bold', halign: 'center' },
                columnStyles: { 0: { halign: 'left', fontStyle: 'bold', cellWidth: 90 } },
                didParseCell: function (data) {
                    if (data.section === 'body' && data.row.index === body.length - 1) {
                        data.cell.styles.fontStyle = 'bold';
                        data.cell.styles.fillColor = [241, 243, 245];
                    }
                },
                margin: { left: 30, right: 30 }
            });

            return doc;
        }

        function getPdfFile() {
            var doc = buildPdf();
            var filename = 'collection-history_' + reportMeta.startDate + '_to_' + reportMeta.endDate + '.pdf';
            var blob = doc.output('blob');
            return new File([blob], filename, { type: 'application/pdf' });
        }

        function downloadPdf() {
            var doc = buildPdf();
            var filename = 'collection-history_' + reportMeta.startDate + '_to_' + reportMeta.endDate + '.pdf';
            doc.save(filename);
            return filename;
        }

        async function sharePdfOnly() {
            var file = getPdfFile();

            // Mobile browsers (Chrome/Safari): share the PDF file directly → pick WhatsApp
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                try {
                    await navigator.share({
                        files: [file],
                        title: 'Collection History'
                    });
                    return;
                } catch (err) {
                    // User cancelled share sheet — do nothing
                    if (err && err.name === 'AbortError') return;
                }
            }

            // Desktop / unsupported: download PDF only (no WhatsApp text message)
            downloadPdf();
            alert('PDF downloaded. Open WhatsApp and attach this PDF from your Downloads folder.');
        }

        document.getElementById('btnDownloadPdf').addEventListener('click', function () {
            if (!<?php echo $has_agent ? 'true' : 'false'; ?>) return;
            downloadPdf();
        });

        document.getElementById('btnShareWhatsApp').addEventListener('click', function () {
            if (!<?php echo $has_agent ? 'true' : 'false'; ?>) return;
            sharePdfOnly();
        });

        // Mobile: toggle between card view (default) and table view
        var toggleBtn = document.getElementById('btnToggleHistoryView');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                document.body.classList.toggle('ch-show-table');
                var showingTable = document.body.classList.contains('ch-show-table');
                if (showingTable) {
                    this.innerHTML = '<i class="ri-table-line"></i> <span>Switch to Card View</span>';
                    this.classList.remove('btn-primary');
                    this.classList.add('btn-secondary');
                } else {
                    this.innerHTML = '<i class="ri-layout-grid-line"></i> <span>Card View (Active)</span>';
                    this.classList.remove('btn-secondary');
                    this.classList.add('btn-primary');
                }
            });
        }

        // Auto-submit when agent changes (reset customer filter)
        document.getElementById('agent_id').addEventListener('change', function () {
            var cust = document.getElementById('customer_id');
            if (cust) cust.value = '0';
            document.getElementById('historyFilterForm').submit();
        });

        // Auto-submit when customer changes for smoother UX
        var customerSelect = document.getElementById('customer_id');
        if (customerSelect && !customerSelect.disabled) {
            customerSelect.addEventListener('change', function () {
                document.getElementById('historyFilterForm').submit();
            });
        }
    })();
    </script>
</body>
</html>
<?php $conn->close(); ?>
