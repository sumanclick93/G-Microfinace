<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// --- 2. Fetch data for filter dropdowns ---
// Fetch all agents & customers (similar to Super/report-collections.php)
$agents_for_filter = []; $agent_result = $conn->query("SELECT id, first_name, last_name FROM agents ORDER BY first_name ASC"); while ($row = $agent_result->fetch_assoc()){ $agents_for_filter[] = $row; }
$customers_for_filter = []; $cust_result = $conn->query("SELECT id, full_name FROM customers ORDER BY full_name ASC"); while ($row = $cust_result->fetch_assoc()){ $customers_for_filter[] = $row; }

// --- 3. Process filter parameters ---
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_agent_id = $_GET['agent_id'] ?? '';
$filter_customer_id = $_GET['customer_id'] ?? '';

// --- 4. Build the dynamic SQL query ---
$sql = "SELECT
            rdp.payment_date, rdp.amount_paid, rdp.notes, rdp.rd_id,
            c.full_name as customer_name,
            ag.first_name as agent_first_name, ag.last_name as agent_last_name
        FROM rd_payments rdp
        JOIN recurring_deposits rd ON rdp.rd_id = rd.id
        JOIN customers c ON rd.customer_id = c.id
        JOIN agents ag ON rdp.collected_by_agent_id = ag.id
        WHERE 1=1";

$params = []; $types = '';
if (!empty($filter_start_date) && !empty($filter_end_date)) { $sql .= " AND rdp.payment_date BETWEEN ? AND ?"; $params[] = $filter_start_date . " 00:00:00"; $params[] = $filter_end_date . " 23:59:59"; $types .= 'ss'; }
if (!empty($filter_agent_id)) { $sql .= " AND rdp.collected_by_agent_id = ?"; $params[] = $filter_agent_id; $types .= 'i'; }
if (!empty($filter_customer_id)) { $sql .= " AND rd.customer_id = ?"; $params[] = $filter_customer_id; $types .= 'i'; }
$sql .= " ORDER BY rdp.payment_date DESC";

// --- 5. Execute the query ---
$rd_payments = []; $total_collection = 0;
$stmt = $conn->prepare($sql);
if (!empty($types)) { $stmt->bind_param($types, ...$params); }
$stmt->execute(); $result = $stmt->get_result();
if ($result && $result->num_rows > 0) { while ($row = $result->fetch_assoc()) { $rd_payments[] = $row; $total_collection += $row['amount_paid']; } }
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
                        <div class="col-12"><div class="title-header option-title"><h5>RD Collection Report</h5></div></div>
                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <h5 class="card-title">Filter Collections</h5>
                                <form class="row g-3" method="GET" action="report-rd-collections.php">
                                    <div class="col-md-3"><label class="form-label">From Date</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                                    <div class="col-md-3"><label class="form-label">To Date</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                                    <div class="col-md-3"><label class="form-label">Agent</label><select name="agent_id" class="form-select"><option value="">All Agents</option><?php foreach ($agents_for_filter as $agent): ?><option value="<?php echo $agent['id']; ?>" <?php echo ($filter_agent_id == $agent['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-md-3"><label class="form-label">Customer</label><select name="customer_id" class="form-select"><option value="">All Customers</option><?php foreach ($customers_for_filter as $customer): ?><option value="<?php echo $customer['id']; ?>" <?php echo ($filter_customer_id == $customer['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($customer['full_name']); ?></option><?php endforeach; ?></select></div>
                                    <div class="col-12"><button type="submit" class="btn btn-primary">Generate Report</button><a href="report-rd-collections.php" class="btn btn-secondary">Reset</a></div>
                                </form>
                            </div></div>
                        </div>
                        <div class="col-12">
                            <div class="card"><div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="mb-0">Collection Details</h5>
                                    <div class="text-end"><small class="text-muted d-block">Total RD Collection</small><h4 class="text-success mb-0">₹<?php echo number_format($total_collection, 2); ?></h4></div>
                                </div>
                                <div class="table-responsive"><table class="table">
                                    <thead><tr><th>Payment Date</th><th>Customer</th><th>Collected By (Agent)</th><th>RD ID</th><th>Amount (₹)</th><th>Notes</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($rd_payments)): ?><tr><td colspan="6" class="text-center text-muted">No RD collections found.</td></tr>
                                        <?php else: foreach ($rd_payments as $payment): ?>
                                            <tr><td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td><td><?php echo htmlspecialchars($payment['customer_name']); ?></td><td><?php echo htmlspecialchars($payment['agent_first_name'] . ' ' . $payment['agent_last_name']); ?></td><td><a href="admin-rd-details.php?id=<?php echo $payment['rd_id']; ?>">#<?php echo $payment['rd_id']; ?></a></td><td><?php echo number_format($payment['amount_paid'], 2); ?></td><td><?php echo htmlspecialchars($payment['notes']); ?></td></tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table></div>
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