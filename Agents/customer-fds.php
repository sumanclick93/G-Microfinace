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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customer.php");
    exit();
}

$customer_id = (int)$_GET['id'];

// Fetch customer info
$stmt_cust = $conn->prepare("SELECT * FROM customers WHERE id = ? AND agent_id = ?");
$stmt_cust->bind_param("ii", $customer_id, $agent_id);
$stmt_cust->execute();
$customer_res = $stmt_cust->get_result();

if ($customer_res->num_rows == 0) {
    header("Location: all-customer.php");
    exit();
}
$customer = $customer_res->fetch_assoc();

// Fetch customer FDs
$stmt_fd = $conn->prepare("SELECT * FROM fixed_deposits WHERE customer_id = ? ORDER BY id DESC");
$stmt_fd->bind_param("i", $customer_id);
$stmt_fd->execute();
$fds_res = $stmt_fd->get_result();
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
                                <h4 class="mb-1"><i class="ri-bank-line me-2"></i>Fixed Deposits for <?php echo htmlspecialchars($customer['full_name']); ?></h4>
                                <p class="text-muted mb-0">Phone: <?php echo htmlspecialchars($customer['phone']); ?> | Customer ID: #<?php echo $customer['id']; ?></p>
                            </div>
                            <div>
                                <a href="apply-fd.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary"><i class="ri-add-line me-1"></i> Apply FD for Customer</a>
                                <a href="all-customer.php" class="btn btn-secondary ms-2"><i class="ri-arrow-left-line me-1"></i> Back to Customers</a>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>FD Number</th>
                                                    <th>Deposit Amount</th>
                                                    <th>Interest Rate</th>
                                                    <th>Tenure</th>
                                                    <th>Total Interest</th>
                                                    <th>Maturity Amount</th>
                                                    <th>Start Date</th>
                                                    <th>Maturity Date</th>
                                                    <th>Status</th>
                                                    <th class="text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($fds_res->num_rows > 0): ?>
                                                    <?php while ($fd = $fds_res->fetch_assoc()): ?>
                                                        <tr>
                                                            <td class="fw-bold">
                                                                <a href="fd-details.php?id=<?php echo $fd['id']; ?>" class="text-primary"><?php echo htmlspecialchars($fd['fd_number']); ?></a>
                                                            </td>
                                                            <td class="fw-bold text-dark">₹<?php echo number_format($fd['deposit_amount'], 2); ?></td>
                                                            <td><span class="badge bg-light-primary text-primary"><?php echo $fd['interest_rate']; ?>% p.a.</span></td>
                                                            <td><?php echo $fd['tenure']; ?> Months</td>
                                                            <td class="text-success">₹<?php echo number_format($fd['total_interest'], 2); ?></td>
                                                            <td class="fw-bold text-success">₹<?php echo number_format($fd['maturity_amount'], 2); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($fd['start_date'])); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($fd['maturity_date'])); ?></td>
                                                            <td>
                                                                <?php
                                                                $st = $fd['status'];
                                                                if ($st == 'active') echo '<span class="badge bg-success">Active</span>';
                                                                elseif ($st == 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                                                                elseif ($st == 'matured') echo '<span class="badge bg-info">Matured</span>';
                                                                elseif ($st == 'closed') echo '<span class="badge bg-secondary">Closed</span>';
                                                                else echo '<span class="badge bg-danger">Rejected</span>';
                                                                ?>
                                                            </td>
                                                            <td class="text-center">
                                                                <a href="fd-details.php?id=<?php echo $fd['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                    <i class="ri-eye-line"></i> View
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4 text-muted">No Fixed Deposit accounts found for this customer.</td>
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
