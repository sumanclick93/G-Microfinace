<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];

// --- 2. Fetch customers for the filter dropdown ---
$customers_for_filter = [];
$cust_stmt = $conn->prepare("SELECT id, full_name FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
$cust_stmt->bind_param("i", $agent_id);
$cust_stmt->execute();
$cust_result = $cust_stmt->get_result();
while ($row = $cust_result->fetch_assoc()) {
    $customers_for_filter[] = $row;
}
$cust_stmt->close();

// --- 3. Process filter parameters from the URL ---
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_customer_id = $_GET['customer_id'] ?? '';

// --- 4. Build the dynamic SQL query ---
$sql = "SELECT 
            p.payment_date,
            p.amount_paid,
            p.notes,
            p.loan_id,
            c.full_name as customer_name
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        JOIN customers c ON l.customer_id = c.id
        WHERE p.collected_by_agent_id = ?";

$conditions = [];
$params = [$agent_id];
$types = 'i';

if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $conditions[] = "p.payment_date BETWEEN ? AND ?";
    $params[] = $filter_start_date . " 00:00:00";
    $params[] = $filter_end_date . " 23:59:59";
    $types .= 'ss';
}
if (!empty($filter_customer_id)) {
    $conditions[] = "l.customer_id = ?";
    $params[] = $filter_customer_id;
    $types .= 'i';
}

if (count($conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY p.payment_date DESC";

// --- 5. Execute the query and calculate the total ---
$payments = [];
$total_collection = 0;
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
        $total_collection += $row['amount_paid'];
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
                        <div class="col-12">
                            <div class="title-header option-title">
                                <h5>My Collection Report</h5>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Filter Collections</h5>
                                    <form class="row g-3" method="GET" action="report-my-collections.php">
                                        <div class="col-md-4">
                                            <label for="start_date" class="form-label">From Date</label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="end_date" class="form-label">To Date</label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="customer_id" class="form-label">Customer</label>
                                            <select id="customer_id" name="customer_id" class="form-select">
                                                <option value="">All My Customers</option>
                                                <?php foreach ($customers_for_filter as $customer): ?>
                                                    <option value="<?php echo $customer['id']; ?>" <?php echo ($filter_customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($customer['full_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">Generate Report</button>
                                            <a href="report-my-collections.php" class="btn btn-secondary">Reset</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Collection Details</h5>
                                        <div class="text-end">
                                            <small class="text-muted d-block">Total Collection for this Period</small>
                                            <h4 class="text-success mb-0">₹<?php echo number_format($total_collection, 2); ?></h4>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Payment Date</th>
                                                    <th>Customer Name</th>
                                                    <th>Loan ID</th>
                                                    <th>Amount Paid (₹)</th>
                                                    <th>Notes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($payments)): ?>
                                                    <tr><td colspan="5" class="text-center text-muted">No collections found for the selected criteria.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($payments as $payment): ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td>
                                                            <td><?php echo htmlspecialchars($payment['customer_name']); ?></td>
                                                            <td><a href="loan-details.php?id=<?php echo $payment['loan_id']; ?>">#<?php echo $payment['loan_id']; ?></a></td>
                                                            <td><?php echo number_format($payment['amount_paid'], 2); ?></td>
                                                            <td><?php echo htmlspecialchars($payment['notes']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
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