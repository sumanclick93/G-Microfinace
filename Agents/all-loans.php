<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];

// --- 2. Fetch data for filter dropdowns ---
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

// Check if a filter has been explicitly applied/submitted
$filter_applied = isset($_GET['apply_filter']) || !empty($filter_start_date) || !empty($filter_end_date) || !empty($filter_status) || !empty($filter_customer_id);

// Loan type target filter (can be preset by normal-loans.php, interest-loans.php, gold-loans.php, or passed via GET)
$target_loan_type = $target_loan_type ?? ($_GET['loan_type'] ?? '');
if (!in_array($target_loan_type, ['standard', 'interest_only', 'gold'])) {
    $target_loan_type = '';
}

$page_title = "My Loan Applications";
if ($target_loan_type === 'standard') {
    $page_title = "Normal Loan Applications";
} elseif ($target_loan_type === 'interest_only') {
    $page_title = "Interest Loan Applications";
} elseif ($target_loan_type === 'gold') {
    $page_title = "Gold Loan Applications";
}

$loans = [];
if ($filter_applied) {
    // --- 4. Build the dynamic SQL query ---
    $sql = "SELECT 
                l.id, l.loan_amount, l.status, l.application_date, l.loan_type, l.gold_weight_grams,
                c.full_name as customer_name, c.avatar as customer_avatar
            FROM loans l
            JOIN customers c ON l.customer_id = c.id
            WHERE l.agent_id = ?";

    $conditions = [];
    $params = [$agent_id];
    $types = 'i';

    if (!empty($target_loan_type)) {
        $conditions[] = "l.loan_type = ?";
        $params[] = $target_loan_type;
        $types .= 's';
    }

    if (!empty($filter_start_date) && !empty($filter_end_date)) {
        $conditions[] = "l.application_date BETWEEN ? AND ?";
        $params[] = $filter_start_date . " 00:00:00";
        $params[] = $filter_end_date . " 23:59:59";
        $types .= 'ss';
    }
    if (!empty($filter_status)) {
        $conditions[] = "l.status = ?";
        $params[] = $filter_status;
        $types .= 's';
    }
    if (!empty($filter_customer_id)) {
        $conditions[] = "l.customer_id = ?";
        $params[] = $filter_customer_id;
        $types .= 'i';
    }

    if (count($conditions) > 0) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    $sql .= " ORDER BY l.application_date DESC";

    // --- 5. Execute the query ---
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
    }
    $stmt->close();
}

$current_page_file = basename($_SERVER['PHP_SELF']);
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
                                        <h5><?php echo htmlspecialchars($page_title); ?></h5>
                                        <a href="apply-loan.php" class="btn btn-theme">Create New Application</a>
                                    </div>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Filter Loans</h5>
                                            <form class="row g-3" method="GET" action="<?php echo htmlspecialchars($current_page_file); ?>">
                                                <input type="hidden" name="apply_filter" value="1">
                                                <?php if (!empty($target_loan_type)): ?>
                                                    <input type="hidden" name="loan_type" value="<?php echo htmlspecialchars($target_loan_type); ?>">
                                                <?php endif; ?>
                                                <div class="col-md-3">
                                                    <label for="start_date" class="form-label">From Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                                </div>
                                                <div class="col-md-3">
                                                    <label for="end_date" class="form-label">To Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                                </div>
                                                <div class="col-md-2">
                                                    <label for="status" class="form-label">Status</label>
                                                    <select id="status" name="status" class="form-select">
                                                        <option value="">All</option>
                                                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="paid" <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="customer_id" class="form-label">Customer</label>
                                                    <select id="customer_id" name="customer_id" class="form-select">
                                                        <option value="">All Customers</option>
                                                        <?php foreach ($customers_for_filter as $customer): ?>
                                                            <option value="<?php echo $customer['id']; ?>" <?php echo ($filter_customer_id == $customer['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($customer['full_name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <button type="submit" class="btn btn-primary">Filter</button>
                                                    <a href="<?php echo htmlspecialchars($current_page_file); ?>" class="btn btn-secondary">Reset</a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <?php if (!$filter_applied): ?>
                                        <div class="card my-3 border-0 shadow-sm" style="background: #f8fafc; border-radius: 12px;">
                                            <div class="card-body text-center p-5">
                                                <div class="mb-3">
                                                    <i class="ri-filter-3-line text-primary" style="font-size: 48px; opacity: 0.7;"></i>
                                                </div>
                                                <h5 class="text-dark fw-bold">Select Filters To Display Data</h5>
                                                <p class="text-secondary mb-0">Please select your desired filter criteria above and click <strong>"Filter"</strong> to view loan records.</p>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive table-product">
                                            <table class="table all-package theme-table" id="table_id">
                                                <thead>
                                                    <tr>
                                                        <th>Photo</th>
                                                        <th>Customer Name</th>
                                                        <?php if (empty($target_loan_type)): ?>
                                                            <th>Type</th>
                                                        <?php endif; ?>
                                                        <th>Loan Amount</th>
                                                        <?php if ($target_loan_type === 'gold'): ?>
                                                            <th>Gold Weight</th>
                                                        <?php endif; ?>
                                                        <th>Application Date</th>
                                                        <th>Status</th>
                                                        <th>View Details</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($loans)) : ?>
                                                        <tr><td colspan="<?php echo (empty($target_loan_type) || $target_loan_type === 'gold') ? 7 : 6; ?>" class="text-center text-muted">No loans found matching your criteria.</td></tr>
                                                    <?php else : ?>
                                                        <?php foreach ($loans as $loan) : ?>
                                                            <tr>
                                                                <td>
                                                                    <div class="table-image">
                                                                        <?php $avatar_path = !empty($loan['customer_avatar']) ? 'upload/customers/avatars/' . $loan['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                                                        <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Avatar" style="max-width: 40px; border-radius: 5px;">
                                                                    </div>
                                                                </td>
                                                                <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                                                <?php if (empty($target_loan_type)): ?>
                                                                    <td>
                                                                        <?php
                                                                            $l_type = $loan['loan_type'] ?? 'standard';
                                                                            if ($l_type === 'gold') {
                                                                                echo '<span class="badge bg-warning text-dark">Gold</span>';
                                                                            } elseif ($l_type === 'interest_only') {
                                                                                echo '<span class="badge bg-primary">Interest</span>';
                                                                            } else {
                                                                                echo '<span class="badge bg-info">Standard</span>';
                                                                            }
                                                                        ?>
                                                                    </td>
                                                                <?php endif; ?>
                                                                <td>₹<?php echo number_format($loan['loan_amount']); ?></td>
                                                                <?php if ($target_loan_type === 'gold'): ?>
                                                                    <td><span class="badge bg-warning text-dark"><i class="ri-gold-line me-1"></i><?php echo floatval($loan['gold_weight_grams']); ?>g</span></td>
                                                                <?php endif; ?>
                                                                <td><?php echo date('d M, Y', strtotime($loan['application_date'])); ?></td>
                                                                <td>
                                                                    <?php
                                                                        $status_clean = strtolower(trim($loan['status']));
                                                                        $status_color = 'secondary';
                                                                        switch ($status_clean) {
                                                                            case 'approved': case 'active': case 'paid': $status_color = 'success'; break;
                                                                            case 'pending': $status_color = 'warning'; break;
                                                                            case 'rejected': case 'defaulted': $status_color = 'danger'; break;
                                                                            case 'closed': $status_color = 'dark'; break;
                                                                        }
                                                                    ?>
                                                                    <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($status_clean); ?></span>
                                                                </td>
                                                                <td>
                                                                    <ul>
                                                                        <li><a href="loan-details.php?id=<?php echo $loan['id']; ?>" title="View Loan Details"><i class="ri-eye-line"></i></a></li>
                                                                        <?php if ($loan['status'] === 'pending') : ?>
                                                                            <li><a href="edit-loan.php?id=<?php echo $loan['id']; ?>" title="Edit Loan"><i class="ri-pencil-line" style="color: var(--theme-color);"></i></a></li>
                                                                        <?php endif; ?>
                                                                    </ul>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
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