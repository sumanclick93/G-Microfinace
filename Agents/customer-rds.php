<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];
$message = ''; // For potential future messages

// 2. Get Customer ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customer.php");
    exit();
}
$customer_id = $_GET['id'];

// Check for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Security Check & Fetch Customer Name
// Ensure the customer belongs to the logged-in agent
$customer = null;
$stmt_cust = $conn->prepare("SELECT full_name FROM customers WHERE id = ? AND agent_id = ?");
$stmt_cust->bind_param("ii", $customer_id, $agent_id);
$stmt_cust->execute();
$result_cust = $stmt_cust->get_result();
if ($result_cust->num_rows > 0) {
    $customer = $result_cust->fetch_assoc();
} else {
    // If customer not found or doesn't belong to the agent, redirect
    $_SESSION['message'] = "<div class='alert alert-danger'>Access denied or customer not found.</div>";
    header("Location: all-customer.php");
    exit();
}
$stmt_cust->close();

// 4. Fetch All Recurring Deposits for this Customer
$rds = [];
$stmt_rds = $conn->prepare("SELECT * FROM recurring_deposits WHERE customer_id = ? ORDER BY start_date DESC");
$stmt_rds->bind_param("i", $customer_id);
$stmt_rds->execute();
$rd_result = $stmt_rds->get_result();
if ($rd_result->num_rows > 0) {
    while ($row = $rd_result->fetch_assoc()) {
        $rds[] = $row;
    }
}
$stmt_rds->close();
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
                                        <h5>Recurring Deposits for <?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                        <a href="apply-rd.php?customer_id=<?php echo $customer_id; ?>" class="align-items-center btn btn-theme d-flex">
                                            <i data-feather="plus"></i>Create New RD
                                        </a>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>

                                    <div class="table-responsive table-product">
                                        <table class="table theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Installment Amount</th>
                                                    <th>Tenure</th>
                                                    <th>Interest Rate</th>
                                                    <th>Maturity Amount</th>
                                                    <th>Start Date</th>
                                                    <th>Maturity Date</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($rds)) : ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">This customer has no recurring deposit accounts.</td>
                                                    </tr>
                                                <?php else : ?>
                                                    <?php foreach ($rds as $rd) : ?>
                                                        <tr>
                                                            <td>₹<?php echo number_format($rd['deposit_amount']); ?> / <?php echo ucfirst($rd['repayment_cycle']);?></td>
                                                            <td><?php echo $rd['tenure'] . ' ' . (($rd['tenure'] > 1) ? ucfirst($rd['repayment_cycle']).'s' : ucfirst($rd['repayment_cycle'])); ?></td>
                                                            <td><?php echo $rd['interest_rate']; ?>%</td>
                                                            <td>₹<?php echo number_format($rd['maturity_amount']); ?></td>
                                                            <td><?php echo date('d M, Y', strtotime($rd['start_date'])); ?></td>
                                                            <td><?php echo date('d M, Y', strtotime($rd['maturity_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                    $status_color = 'primary'; // Default for active
                                                                    if ($rd['status'] == 'matured' || $rd['status'] == 'closed') {
                                                                        $status_color = 'success';
                                                                    } elseif ($rd['status'] == 'premature-closed') {
                                                                        $status_color = 'warning';
                                                                    }
                                                                ?>
                                                                <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucwords(str_replace('-', ' ', $rd['status'])); ?></span>
                                                            </td>
                                                            <td>
                                                                <a href="rd-details.php?id=<?php echo $rd['id']; ?>" title="View RD Details">
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