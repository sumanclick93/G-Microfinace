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

// --- 3. Process filter parameters from URL ---
$filter_start_date = $_GET['start_date'] ?? '';
$filter_end_date = $_GET['end_date'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_customer_id = $_GET['customer_id'] ?? '';

// --- 4. Build the dynamic SQL query ---
$sql = "SELECT 
            rd.id, 
            rd.deposit_amount, 
            rd.repayment_cycle, 
            rd.tenure, 
            rd.start_date, 
            rd.status,
            c.full_name as customer_name,
            c.avatar as customer_avatar
        FROM recurring_deposits rd
        JOIN customers c ON rd.customer_id = c.id
        WHERE rd.agent_id = ?";

$conditions = [];
$params = [$agent_id];
$types = 'i';

if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $conditions[] = "rd.start_date BETWEEN ? AND ?"; // Filter by start date
    $params[] = $filter_start_date;
    $params[] = $filter_end_date;
    $types .= 'ss';
}
if (!empty($filter_status)) {
    $conditions[] = "rd.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($filter_customer_id)) {
    $conditions[] = "rd.customer_id = ?";
    $params[] = $filter_customer_id;
    $types .= 'i';
}

if (count($conditions) > 0) {
    $sql .= " AND " . implode(" AND ", $conditions);
}
$sql .= " ORDER BY rd.start_date DESC";

// --- 5. Execute the query ---
$rds = [];
$stmt = $conn->prepare($sql);
if (!empty($params)) { // Bind parameters only if filters are applied
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
                                        <h5>My Recurring Deposits</h5>
                                        <a href="apply-rd.php" class="btn btn-theme">Create New RD</a>
                                    </div>

                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Filter RDs</h5>
                                            <form class="row g-3" method="GET" action="all-rds.php">
                                                <div class="col-md-3">
                                                    <label class="form-label">Start Date From</label>
                                                    <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Start Date To</label>
                                                    <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="">All</option>
                                                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="matured" <?php echo ($filter_status == 'matured') ? 'selected' : ''; ?>>Matured</option>
                                                        <option value="closed" <?php echo ($filter_status == 'closed') ? 'selected' : ''; ?>>Closed</option>
                                                        <option value="premature-closed" <?php echo ($filter_status == 'premature-closed') ? 'selected' : ''; ?>>Premature Closed</option>
                                                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Customer</label>
                                                    <select name="customer_id" class="form-select">
                                                        <option value="">All My Customers</option>
                                                        <?php foreach ($customers_for_filter as $customer): ?>
                                                            <option value="<?php echo $customer['id']; ?>" <?php echo ($filter_customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($customer['full_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">Filter</button>
                                                    <a href="all-rds.php" class="btn btn-secondary">Reset</a>
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
                                                    <th>Installment Amt</th>
                                                    <th>Tenure</th>
                                                    <th>Start Date</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rds)) : ?>
                                                    <tr><td colspan="7" class="text-center text-muted">No RDs found matching your criteria.</td></tr>
                                                <?php else : ?>
                                                    <?php foreach ($rds as $rd) : ?>
                                                        <tr>
                                                            <td>
                                                                <div class="table-image">
                                                                    <?php $avatar_path = !empty($rd['customer_avatar']) ? 'upload/customers/avatars/' . $rd['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Avatar" style="max-width: 40px; border-radius: 5px;">
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($rd['customer_name']); ?></td>
                                                            <td>₹<?php echo number_format($rd['deposit_amount']); ?> / <?php echo ucfirst($rd['repayment_cycle']);?></td>
                                                            <td><?php echo $rd['tenure'] . ' ' . (($rd['tenure'] > 1) ? ucfirst($rd['repayment_cycle']).'s' : ucfirst($rd['repayment_cycle'])); ?></td>
                                                            <td><?php echo date('d M, Y', strtotime($rd['start_date'])); ?></td>
                                                            <td>
                                                                 <?php
                                                                     $status_color = 'primary';
                                                                     switch ($rd['status']) {
                                                                         case 'active': case 'matured': case 'closed': $status_color = 'success'; break;
                                                                         case 'pending': $status_color = 'warning'; break;
                                                                         case 'rejected': case 'premature-closed': $status_color = 'danger'; break;
                                                                     }
                                                                 ?>
                                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucwords(str_replace('-', ' ', $rd['status'])); ?></span>
                                                            </td>
                                                             <td>
                                                                 <ul>
                                                                     <li><a href="rd-details.php?id=<?php echo $rd['id']; ?>" title="View RD Details"><i class="ri-eye-line"></i></a></li>
                                                                     <?php if ($rd['status'] === 'pending') : ?>
                                                                         <li><a href="edit-rd.php?id=<?php echo $rd['id']; ?>" title="Edit RD"><i class="ri-pencil-line" style="color: var(--theme-color);"></i></a></li>
                                                                     <?php endif; ?>
                                                                 </ul>
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