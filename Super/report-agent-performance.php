<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Process filter parameters from the URL
$default_start_date = date('Y-m-01');
$default_end_date = date('Y-m-t');
$filter_start_date = $_GET['start_date'] ?? $default_start_date;
$filter_end_date = $_GET['end_date'] ?? $default_end_date;
$filter_sort_by = $_GET['sort_by'] ?? 'total_collections_amount'; // Default sort

// 3. Build the dynamic SQL query with subqueries for LOAN and RD stats
$sql = "
    SELECT
        a.id,
        a.first_name,
        a.last_name,
        COALESCE(cust_counts.total_customers, 0) AS total_customers,
        -- Loan Stats
        COALESCE(loan_stats.total_disbursed_amount, 0) AS total_disbursed_amount,
        COALESCE(loan_stats.total_disbursed_count, 0) AS total_disbursed_count,
        COALESCE(loan_payment_stats.total_loan_collections, 0) AS total_loan_collections,
        -- RD Stats
        COALESCE(rd_stats.total_rds_created, 0) AS total_rds_created,
        COALESCE(rd_payment_stats.total_rd_collections, 0) AS total_rd_collections,
        -- Combined Collections
        (COALESCE(loan_payment_stats.total_loan_collections, 0) + COALESCE(rd_payment_stats.total_rd_collections, 0)) AS total_collections_amount

    FROM agents a

    -- Subquery for total customer count (not date-dependent)
    LEFT JOIN ( SELECT agent_id, COUNT(id) as total_customers FROM customers GROUP BY agent_id ) AS cust_counts ON a.id = cust_counts.agent_id

    -- Subquery for loan stats within the date range (approval date)
    LEFT JOIN ( SELECT agent_id, SUM(loan_amount) as total_disbursed_amount, COUNT(id) as total_disbursed_count
                FROM loans WHERE status IN ('active', 'paid') AND approval_date BETWEEN ? AND ? GROUP BY agent_id
              ) AS loan_stats ON a.id = loan_stats.agent_id

    -- Subquery for loan payment stats within the date range (payment date)
    LEFT JOIN ( SELECT collected_by_agent_id, SUM(amount_paid) as total_loan_collections
                FROM payments WHERE payment_date BETWEEN ? AND ? GROUP BY collected_by_agent_id
              ) AS loan_payment_stats ON a.id = loan_payment_stats.collected_by_agent_id

    -- Subquery for RD creation stats within the date range (created_at)
    LEFT JOIN ( SELECT agent_id, COUNT(id) as total_rds_created
                FROM recurring_deposits WHERE created_at BETWEEN ? AND ? GROUP BY agent_id
              ) AS rd_stats ON a.id = rd_stats.agent_id

    -- Subquery for RD payment stats within the date range (payment date)
    LEFT JOIN ( SELECT collected_by_agent_id, SUM(amount_paid) as total_rd_collections
                FROM rd_payments WHERE payment_date BETWEEN ? AND ? GROUP BY collected_by_agent_id
              ) AS rd_payment_stats ON a.id = rd_payment_stats.collected_by_agent_id
";

// 4. Set the sorting order dynamically
// Map sort options to actual calculated columns/aliases
$valid_sort_columns = [
    'total_collections_amount' => 'total_collections_amount',
    'total_disbursed_amount' => 'total_disbursed_amount',
    'total_customers' => 'total_customers',
    'total_rd_collections' => 'total_rd_collections',
    'total_loan_collections' => 'total_loan_collections'
];
$sort_column = $valid_sort_columns[$filter_sort_by] ?? 'total_collections_amount'; // Default if invalid
$order_by_clause = " ORDER BY " . $sort_column . " DESC";
$sql .= $order_by_clause;

// 5. Prepare and execute the query (Binding 6 date parameters)
$start_date_time = $filter_start_date . " 00:00:00";
$end_date_time = $filter_end_date . " 23:59:59";

$stmt = $conn->prepare($sql);
// s = string (for dates) - Bind dates for loan approval, loan payment, rd creation, rd payment
// s = string (for dates) - Bind dates 4 times (8 total parameters)
$stmt->bind_param("ssssssss", $start_date_time, $end_date_time, $start_date_time, $end_date_time, $start_date_time, $end_date_time, $start_date_time, $end_date_time);
$stmt->execute();
$result = $stmt->get_result();

$agents_performance = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $agents_performance[] = $row;
    }
}
$stmt->close();
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
                        <div class="col-12"><div class="title-header option-title"><h5>Agent Performance Report</h5></div></div>

                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h5 class="card-title">Filter Report</h5>
                                <form class="row g-3" method="GET" action="report-agent-performance.php">
                                    <div class="col-md-4"><label class="form-label">From Date</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                                    <div class="col-md-4"><label class="form-label">To Date</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                                    <div class="col-md-4">
                                        <label class="form-label">Rank By</label>
                                        <select name="sort_by" class="form-select">
                                            <option value="total_collections_amount" <?php echo ($filter_sort_by == 'total_collections_amount') ? 'selected' : ''; ?>>Total Collections (Loan+RD)</option>
                                            <option value="total_loan_collections" <?php echo ($filter_sort_by == 'total_loan_collections') ? 'selected' : ''; ?>>Loan Collections</option>
                                            <option value="total_rd_collections" <?php echo ($filter_sort_by == 'total_rd_collections') ? 'selected' : ''; ?>>RD Collections</option>
                                            <option value="total_disbursed_amount" <?php echo ($filter_sort_by == 'total_disbursed_amount') ? 'selected' : ''; ?>>Loan Amount Disbursed</option>
                                            <option value="total_customers" <?php echo ($filter_sort_by == 'total_customers') ? 'selected' : ''; ?>>Most Customers</option>
                                        </select>
                                    </div>
                                    <div class="col-12"><button type="submit" class="btn btn-primary">Generate Report</button><a href="report-agent-performance.php" class="btn btn-secondary">Reset to Current Month</a></div>
                                </form>
                            </div></div>
                        </div>

                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h5 class="mb-3">Performance from <?php echo date('d M, Y', strtotime($filter_start_date)); ?> to <?php echo date('d M, Y', strtotime($filter_end_date)); ?></h5>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Agent Name</th>
                                                <th>Customers</th>
                                                <th>Loans (#)</th>
                                                <th>Loan Amt (₹)</th>
                                                <th>Loan Coll. (₹)</th>
                                                <th>RDs (#)</th>
                                                <th>RD Coll. (₹)</th>
                                                <th>Total Coll. (₹)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($agents_performance)): ?>
                                                <tr><td colspan="9" class="text-center text-muted">No agent activity found for the selected period.</td></tr>
                                            <?php else: ?>
                                                <?php foreach ($agents_performance as $index => $agent): ?>
                                                    <tr>
                                                        <td><strong><?php echo $index + 1; ?></strong></td>
                                                        <td><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></td>
                                                        <td><?php echo $agent['total_customers']; ?></td>
                                                        <td><?php echo $agent['total_disbursed_count']; ?></td>
                                                        <td><?php echo number_format($agent['total_disbursed_amount'], 0); ?></td>
                                                        <td><?php echo number_format($agent['total_loan_collections'], 0); ?></td>
                                                        <td><?php echo $agent['total_rds_created']; ?></td>
                                                        <td><?php echo number_format($agent['total_rd_collections'], 0); ?></td>
                                                        <td class="fw-bold text-primary"><?php echo number_format($agent['total_collections_amount'], 0); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</body>
</html>