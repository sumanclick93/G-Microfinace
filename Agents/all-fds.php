<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];

// Filter by status if provided
$status_filter = $_GET['status'] ?? 'all';
$search_query = trim($_GET['search'] ?? '');

$agent_id_clean = (int)$agent_id;
$where_clauses = ["(fd.agent_id = $agent_id_clean OR c.agent_id = $agent_id_clean)"];

if ($status_filter !== 'all' && in_array($status_filter, ['pending', 'active', 'matured', 'closed', 'rejected'])) {
    $safe_status = $conn->real_escape_string($status_filter);
    $where_clauses[] = "fd.status = '$safe_status'";
}

if (!empty($search_query)) {
    $safe_search = $conn->real_escape_string($search_query);
    $where_clauses[] = "(c.full_name LIKE '%$safe_search%' OR c.phone LIKE '%$safe_search%' OR fd.fd_number LIKE '%$safe_search%')";
}

$where_sql = implode(" AND ", $where_clauses);

$sql = "SELECT fd.*, IFNULL(c.full_name, 'N/A') as customer_name, IFNULL(c.phone, '') as phone_number 
        FROM fixed_deposits fd 
        LEFT JOIN customers c ON fd.customer_id = c.id 
        WHERE $where_sql 
        ORDER BY fd.id DESC";

$result = $conn->query($sql);

// Stats summary for the logged in agent
$stats = ['total_count' => 0, 'active_amount' => 0, 'pending_count' => 0, 'matured_count' => 0];
$stats_stmt = $conn->prepare("SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN status = 'active' THEN deposit_amount ELSE 0 END) as active_amount,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'matured' THEN 1 ELSE 0 END) as matured_count
    FROM fixed_deposits WHERE agent_id = ?");
if ($stats_stmt) {
    $stats_stmt->bind_param("i", $agent_id);
    $stats_stmt->execute();
    $res = $stats_stmt->get_result();
    if ($res) {
        $stats = $res->fetch_assoc() ?? $stats;
    }
}
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
                    
                    <div class="row mb-4">
                        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1"><i class="ri-bank-line me-2"></i>Fixed Deposits (FD)</h4>
                                <p class="text-muted mb-0">Manage customer lump-sum fixed deposit accounts</p>
                            </div>
                            <a href="apply-fd.php" class="btn btn-primary"><i class="ri-add-line me-1"></i> Apply New FD</a>
                        </div>
                    </div>

                    <!-- Summary Stats -->
                    <div class="row">
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6 class="card-title mb-1 text-white-50">Total FDs</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($stats['total_count'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6 class="card-title mb-1 text-white-50">Active Corpus</h6>
                                    <h3 class="mb-0 fw-bold">₹<?php echo number_format($stats['active_amount'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h6 class="card-title mb-1 text-white-50">Pending Approvals</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($stats['pending_count'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6 mb-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h6 class="card-title mb-1 text-white-50">Matured FDs</h6>
                                    <h3 class="mb-0 fw-bold"><?php echo number_format($stats['matured_count'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Bar -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <form method="GET" action="all-fds.php" class="row g-3">
                                        <div class="col-md-4">
                                            <input type="text" name="search" class="form-control" placeholder="Search Customer, Phone, or FD #" value="<?php echo htmlspecialchars($search_query); ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <select name="status" class="form-select">
                                                <option value="all" <?php if($status_filter == 'all') echo 'selected'; ?>>All Statuses</option>
                                                <option value="pending" <?php if($status_filter == 'pending') echo 'selected'; ?>>Pending Approval</option>
                                                <option value="active" <?php if($status_filter == 'active') echo 'selected'; ?>>Active</option>
                                                <option value="matured" <?php if($status_filter == 'matured') echo 'selected'; ?>>Matured</option>
                                                <option value="closed" <?php if($status_filter == 'closed') echo 'selected'; ?>>Closed</option>
                                                <option value="rejected" <?php if($status_filter == 'rejected') echo 'selected'; ?>>Rejected</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="submit" class="btn btn-secondary w-100"><i class="ri-search-line me-1"></i> Filter</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FD Table -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>FD Number</th>
                                                    <th>Customer Name</th>
                                                    <th>Deposit Amount</th>
                                                    <th>Interest Rate</th>
                                                    <th>Tenure</th>
                                                    <th>Maturity Amount</th>
                                                    <th>Start Date</th>
                                                    <th>Maturity Date</th>
                                                    <th>Status</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($result->num_rows > 0): ?>
                                                    <?php while ($row = $result->fetch_assoc()): ?>
                                                        <tr>
                                                            <td class="fw-bold">
                                                                <a href="fd-details.php?id=<?php echo $row['id']; ?>" class="text-primary"><?php echo htmlspecialchars($row['fd_number']); ?></a>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['phone_number']); ?></small>
                                                            </td>
                                                            <td class="fw-bold text-dark">₹<?php echo number_format($row['deposit_amount'], 2); ?></td>
                                                            <td><span class="badge bg-light-primary text-primary"><?php echo $row['interest_rate']; ?>% p.a.</span></td>
                                                            <td><?php echo $row['tenure']; ?> Months</td>
                                                            <td class="fw-bold text-success">₹<?php echo number_format($row['maturity_amount'], 2); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($row['maturity_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                $st = $row['status'];
                                                                if ($st == 'active') echo '<span class="badge bg-success">Active</span>';
                                                                elseif ($st == 'pending') echo '<span class="badge bg-warning text-dark">Pending Approval</span>';
                                                                elseif ($st == 'matured') echo '<span class="badge bg-info">Matured</span>';
                                                                elseif ($st == 'closed') echo '<span class="badge bg-secondary">Closed</span>';
                                                                else echo '<span class="badge bg-danger">Rejected</span>';
                                                                ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <a href="fd-details.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                                                    <i class="ri-eye-line"></i> View
                                                                </a>
                                                                <?php if ($row['status'] == 'pending'): ?>
                                                                    <a href="edit-fd.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-warning ms-1" title="Edit Pending FD">
                                                                        <i class="ri-edit-line"></i>
                                                                    </a>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4 text-muted">No Fixed Deposit records found.</td>
                                                    </tr>
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
