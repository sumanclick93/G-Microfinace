<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- Handle Delete Loan Action ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_loan' && isset($_POST['loan_id'])) {
    $loan_id = intval($_POST['loan_id']);
    
    // Check if status is eligible for delete
    $status_check_stmt = $conn->prepare("SELECT status FROM loans WHERE id = ?");
    $status_check_stmt->bind_param("i", $loan_id);
    $status_check_stmt->execute();
    $result = $status_check_stmt->get_result();
    $status = ($result->num_rows > 0) ? $result->fetch_assoc()['status'] : '';
    $status_check_stmt->close();

    if (in_array($status, ['rejected', 'paid', 'closed'])) {
        $conn->begin_transaction();
        try {
            // Delete associated payments
            $del_payments = $conn->prepare("DELETE FROM payments WHERE loan_id = ?");
            $del_payments->bind_param("i", $loan_id);
            $del_payments->execute();
            $del_payments->close();

            // Delete associated wallet transactions
            $del_tx = $conn->prepare("DELETE FROM wallet_transactions WHERE loan_id = ?");
            $del_tx->bind_param("i", $loan_id);
            $del_tx->execute();
            $del_tx->close();

            // Delete loan itself
            $del_loan = $conn->prepare("DELETE FROM loans WHERE id = ?");
            $del_loan->bind_param("i", $loan_id);
            $del_loan->execute();
            $del_loan->close();

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success'>Loan and all its records deleted successfully.</div>";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting loan. Transaction rolled back.</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Only rejected or completed loans can be deleted.</div>";
    }
    header("Location: all-loans.php");
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
            l.id, l.loan_amount, l.total_repayable_amount, l.status, l.application_date, l.tenure,
            c.full_name as customer_name, c.avatar as customer_avatar,
            a.first_name as agent_first_name, a.last_name as agent_last_name,
            IFNULL(p.paid_emis, 0) as paid_emis
        FROM loans l
        JOIN customers c ON l.customer_id = c.id
        JOIN agents a ON l.agent_id = a.id
        LEFT JOIN (
            SELECT loan_id, COUNT(*) as paid_emis 
            FROM payments 
            WHERE status = 'approved' 
            GROUP BY loan_id
        ) p ON l.id = p.loan_id
        WHERE 1=1"; // Start with a true condition to easily append AND clauses

$params = [];
$types = '';

if (!empty($filter_start_date) && !empty($filter_end_date)) {
    $sql .= " AND l.application_date BETWEEN ? AND ?";
    $params[] = $filter_start_date . " 00:00:00";
    $params[] = $filter_end_date . " 23:59:59";
    $types .= 'ss';
}
if (!empty($filter_status)) {
    $sql .= " AND l.status = ?";
    $params[] = $filter_status;
    $types .= 's';
}
if (!empty($filter_customer_id)) {
    $sql .= " AND l.customer_id = ?";
    $params[] = $filter_customer_id;
    $types .= 'i';
}
if (!empty($filter_agent_id)) {
    $sql .= " AND l.agent_id = ?";
    $params[] = $filter_agent_id;
    $types .= 'i';
}

$sql .= " ORDER BY l.application_date DESC";

// --- 5. Execute the query ---
$loans = [];
$stmt = $conn->prepare($sql);
if (!empty($types)) { // Bind parameters only if there are any
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
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
                                        <h5>All Loan Applications</h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Filter Loans</h5>
                                            <form class="row g-3" method="GET" action="all-loans.php">
                                                <div class="col-md-3"><label class="form-label">From Date</label><input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>"></div>
                                                <div class="col-md-3"><label class="form-label">To Date</label><input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>"></div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="">All Statuses</option>
                                                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                                        <option value="paid" <?php echo ($filter_status == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                                        <option value="rejected" <?php echo ($filter_status == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                                        <option value="defaulted" <?php echo ($filter_status == 'defaulted') ? 'selected' : ''; ?>>Defaulted</option>
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
                                                    <a href="all-loans.php" class="btn btn-secondary">Reset Filters</a>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                             <thead>
                                                 <tr><th>Photo</th><th>Customer Name</th><th>Agent Name</th><th>Loan Amount</th><th>EMIs (Paid/Total)</th><th>Application Date</th><th>Status</th><th>Details</th></tr>
                                             </thead>
                                             <tbody>
                                                 <?php if (empty($loans)) : ?>
                                                     <tr><td colspan="8" class="text-center text-muted">No loans found matching your criteria.</td></tr>
                                                 <?php else : ?>
                                                    <?php foreach ($loans as $loan) : ?>
                                                        <tr>
                                                            <td>
                                                                <div class="table-image">
                                                                    <?php $avatar_path = !empty($loan['customer_avatar']) ? '../Agents/upload/customers/avatars/' . $loan['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Avatar" style="max-width: 40px; border-radius: 5px;">
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($loan['customer_name']); ?></td>
                                                            <td><?php echo htmlspecialchars($loan['agent_first_name'] . ' ' . $loan['agent_last_name']); ?></td>
                                                             <td>₹<?php echo number_format($loan['loan_amount']); ?></td>
                                                             <td><strong><?php echo $loan['paid_emis'] . ' / ' . $loan['tenure']; ?></strong></td>
                                                             <td><?php echo date('d M, Y', strtotime($loan['application_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                    $status_color = 'secondary';
                                                                    switch ($loan['status']) {
                                                                        case 'approved': case 'active': case 'paid': $status_color = 'success'; break;
                                                                        case 'pending': $status_color = 'warning'; break;
                                                                        case 'rejected': case 'defaulted': $status_color = 'danger'; break;
                                                                    }
                                                                ?>
                                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($loan['status']); ?></span>
                                                            </td>
                                                             <td>
                                                                 <ul>
                                                                     <li><a href="admin-loan-details.php?id=<?php echo $loan['id']; ?>" title="View Loan Details"><i class="ri-eye-line"></i></a></li>
                                                                     <?php if (in_array($loan['status'], ['rejected', 'paid', 'closed'])): ?>
                                                                         <li><a href="javascript:void(0)" onclick="confirmDeleteLoan(<?php echo $loan['id']; ?>)" title="Delete Loan" class="text-danger"><i class="ri-delete-bin-line"></i></a></li>
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

    <div class="modal fade" id="deleteLoanModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="all-loans.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Loan Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger"><strong>Warning:</strong> This action is permanent and cannot be undone.</p>
                        <p>This will delete the loan record along with all associated payments and transaction histories.</p>
                        <input type="hidden" name="loan_id" id="delete_loan_id">
                        <input type="hidden" name="action" value="delete_loan">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function confirmDeleteLoan(id) {
        document.getElementById('delete_loan_id').value = id;
        var myModal = new bootstrap.Modal(document.getElementById('deleteLoanModal'));
        myModal.show();
    }
    </script>
</body>
</html>