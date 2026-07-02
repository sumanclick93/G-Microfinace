<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// --- 2. Fetch data for filter dropdowns ---
// Fetch all agents
$agents_for_filter = [];
$agent_result = $conn->query("SELECT id, first_name, last_name FROM agents ORDER BY first_name ASC");
while ($row = $agent_result->fetch_assoc()) {
    $agents_for_filter[] = $row;
}
// Fetch all customers
$customers_for_filter = [];
$cust_result = $conn->query("SELECT id, full_name FROM customers ORDER BY full_name ASC");
while ($row = $cust_result->fetch_assoc()) {
    $customers_for_filter[] = $row;
}

// --- 3. Process filter parameters from the URL ---
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_customer_id = $_GET['customer_id'] ?? '';
$filter_agent_id = $_GET['agent_id'] ?? '';

// --- 4. Build the dynamic SQL query based on filters ---
$sql = "SELECT
            rd.id,
            rd.deposit_amount,
            rd.repayment_cycle,
            rd.tenure,
            rd.start_date,
            rd.maturity_date,
            rd.status,
            c.full_name as customer_name,
            c.avatar as customer_avatar,
            a.first_name as agent_first_name,
            a.last_name as agent_last_name
        FROM recurring_deposits rd
        JOIN customers c ON rd.customer_id = c.id
        JOIN agents a ON rd.agent_id = a.id
        WHERE 1=1"; // Start with a true condition

$params = [];
$types = '';

if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND rd.start_date BETWEEN ? AND ?";
    $params[] = $filter_start_date;
    $params[] = $filter_end_date;
    $types .= 'ss';
}
if (!empty($filter_status)) {
    $sql .= " AND rd.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($filter_customer_id)) {
    $sql .= " AND rd.customer_id = ?";
    $params[] = $filter_customer_id;
    $types .= 'i';
}
if (!empty($filter_agent_id)) {
    $sql .= " AND rd.agent_id = ?";
    $params[] = $filter_agent_id;
    $types .= 'i';
}

$sql .= " ORDER BY rd.start_date DESC";

// --- 5. Execute the query ---
$rds = [];
$stmt = $conn->prepare($sql);
if (!empty($types)) { // Bind parameters only if there are any
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rds[] = $row;
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
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>All Recurring Deposits</h5>
                                    </div>

                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Filter RDs</h5>
                                            <form class="row g-3" method="GET" action="all-rds.php">
                                                <div class="col-md-3"><label class="form-label">Start Date From</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                                                <div class="col-md-3"><label class="form-label">Start Date To</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="">All Statuses</option>
                                                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="matured" <?php echo ($filter_status == 'matured') ? 'selected' : ''; ?>>Matured</option>
                                                        <option value="closed" <?php echo ($filter_status == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                                        <option value="premature-closed" <?php echo ($filter_status == 'premature-closed') ? 'selected' : ''; ?>>Premature Closed</option>
                                                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Agent</label>
                                                    <select name="agent_id" class="form-select">
                                                        <option value="">All Agents</option>
                                                        <?php foreach ($agents_for_filter as $agent): ?>
                                                            <option value="<?php echo $agent['id']; ?>" <?php echo ($filter_agent_id == $agent['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Customer</label>
                                                    <select name="customer_id" class="form-select">
                                                        <option value="">All Customers</option>
                                                        <?php foreach ($customers_for_filter as $customer): ?>
                                                            <option value="<?php echo $customer['id']; ?>" <?php echo ($filter_customer_id == $customer['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($customer['full_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 align-self-end">
                                                    <button type="submit" class="btn btn-primary">Filter</button>
                                                    <a href="all-rds.php" class="btn btn-secondary">Reset Filters</a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Photo</th>
                                                    <th>Customer Name</th>
                                                    <th>Agent Name</th>
                                                    <th>Installment</th>
                                                    <th>Tenure</th>
                                                    <th>Start Date</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rds)) : ?>
                                                    <tr><td colspan="8" class="text-center text-muted">No RDs found matching your criteria.</td></tr>
                                                <?php else : ?>
                                                    <?php foreach ($rds as $rd) : ?>
                                                        <tr>
                                                            <td>
                                                                <div class="table-image">
                                                                    <?php $avatar_path = !empty($rd['customer_avatar']) ? '/Agents/upload/customers/avatars/' . $rd['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Avatar" style="max-width: 40px; border-radius: 5px;">
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($rd['customer_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($rd['agent_first_name'] . ' ' . $rd['agent_last_name']); ?></td>
                                                            <td>₹<?php echo number_format($rd['deposit_amount']); ?> / <?php echo ucfirst($rd['repayment_cycle']);?></td>
                                                            <td><?php echo $rd['tenure'] . ' ' . (($rd['tenure'] > 1) ? ucfirst($rd['repayment_cycle']).'s' : ucfirst($rd['repayment_cycle'])); ?></td>
                                                            <td><?php echo date('d M, Y', strtotime($rd['start_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                    $status_color = 'primary'; // active
                                                                    if ($rd['status'] == 'pending') $status_color = 'warning';
                                                                    elseif ($rd['status'] == 'matured' || $rd['status'] == 'closed') $status_color = 'success';
                                                                    elseif ($rd['status'] == 'premature-closed') $status_color = 'info';
                                                                    elseif ($rd['status'] == 'rejected') $status_color = 'danger';
                                                                ?>
                                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucwords(str_replace('-', ' ', $rd['status'])); ?></span>
                                                            </td>
                                                            <td>
                                                                <ul><li><a href="admin-rd-details.php?id=<?php echo $rd['id']; ?>" title="View RD Details"><i class="ri-eye-line"></i></a></li></ul>
                                                            </td>
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