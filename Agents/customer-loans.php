<?php
// Include the config file
include('config.php');

// 1. Authentication Check
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];

// 2. Get Customer ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customer.php");
    exit();
}
$customer_id = $_GET['id'];

// 3. Security Check & Fetch Customer Name
$customer = null;
$stmt = $conn->prepare("SELECT full_name FROM customers WHERE id = ? AND agent_id = ?");
$stmt->bind_param("ii", $customer_id, $agent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "<div class='alert alert-danger'>Access denied or customer not found.</div>";
    header("Location: all-customer.php");
    exit();
}
$stmt->close();

// 4. Fetch All Loans for this Customer (MODIFIED QUERY)
$loans = [];
// MODIFIED: Fetched new columns tenure and repayment_cycle instead of term_months
$stmt_loans = $conn->prepare("SELECT id, loan_amount, total_repayable_amount, tenure, repayment_cycle, status, application_date FROM loans WHERE customer_id = ? ORDER BY application_date DESC");
$stmt_loans->bind_param("i", $customer_id);
$stmt_loans->execute();
$loan_result = $stmt_loans->get_result();
if ($loan_result->num_rows > 0) {
    while ($row = $loan_result->fetch_assoc()) {
        $loans[] = $row;
    }
}
$stmt_loans->close();
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
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Loan History for <?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                        <a href="apply-loan.php?customer_id=<?php echo $customer_id; ?>" class="align-items-center btn btn-theme d-flex">
                                            <i data-feather="plus"></i>Apply for New Loan
                                        </a>
                                    </div>

                                    <div class="table-responsive table-product">
                                        <table class="table theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Loan Amount</th>
                                                    <th>Total Repayable</th>
                                                    <th>Tenure</th>
                                                    <th>Application Date</th>
                                                    <th>Status</th>
                                                    <th>View Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($loans)) : ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center text-muted">This customer has no loan history.</td>
                                                    </tr>
                                                <?php else : ?>
                                                    <?php foreach ($loans as $loan) : ?>
                                                        <tr>
                                                            <td>₹<?php echo number_format($loan['loan_amount']); ?></td>
                                                            <td>₹<?php echo number_format($loan['total_repayable_amount']); ?></td>
                                                            <td><?php echo $loan['tenure'] . ' ' . ucfirst($loan['repayment_cycle']) . ' Payments'; ?></td>
                                                            <td><?php echo date('d M, Y', strtotime($loan['application_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                    $status_color = 'secondary';
                                                                    switch ($loan['status']) {
                                                                        case 'approved': case 'active': case 'paid':
                                                                            $status_color = 'success'; break;
                                                                        case 'pending':
                                                                            $status_color = 'warning'; break;
                                                                        case 'rejected': case 'defaulted':
                                                                            $status_color = 'danger'; break;
                                                                    }
                                                                ?>
                                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucfirst($loan['status']); ?></span>
                                                            </td>
                                                            <td>
                                                                <a href="loan-details.php?id=<?php echo $loan['id']; ?>" title="View Loan Details">
                                                                    <i class="ri-eye-line"></i>
                                                                </a>
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