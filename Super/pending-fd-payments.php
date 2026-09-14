<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

// Handle Approval / Rejection Post Action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $fd_id = (int)$_POST['fd_id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $approval_date = date("Y-m-d H:i:s");
        $stmt_app = $conn->prepare("UPDATE fixed_deposits SET status = 'active', approval_date = ?, approved_by = ? WHERE id = ? AND status = 'pending'");
        $stmt_app->bind_param("sii", $approval_date, $admin_id, $fd_id);
        if ($stmt_app->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Fixed Deposit application #{$fd_id} approved successfully! Account is now Active.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error approving application: " . $stmt_app->error . "</div>";
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? 'Rejected by Admin');
        $stmt_rej = $conn->prepare("UPDATE fixed_deposits SET status = 'rejected', rejection_reason = ? WHERE id = ? AND status = 'pending'");
        $stmt_rej->bind_param("si", $reason, $fd_id);
        if ($stmt_rej->execute()) {
            $_SESSION['message'] = "<div class='alert alert-warning'>Fixed Deposit application #{$fd_id} has been rejected.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error rejecting application: " . $stmt_rej->error . "</div>";
        }
    }
    header("Location: pending-fd-payments.php");
    exit();
}

// Fetch Pending FDs
$sql = "SELECT fd.*, IFNULL(c.full_name, 'N/A') as customer_name, IFNULL(c.phone, '') as customer_phone, CONCAT(a.first_name, ' ', IFNULL(a.last_name, '')) as agent_name 
        FROM fixed_deposits fd 
        LEFT JOIN customers c ON fd.customer_id = c.id 
        LEFT JOIN agents a ON fd.agent_id = a.id 
        WHERE fd.status = 'pending' 
        ORDER BY fd.id DESC";

$pending_res = $conn->query($sql);
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
                                <h4 class="mb-1"><i class="ri-time-line me-2 text-warning"></i>Pending Fixed Deposit Applications</h4>
                                <p class="text-muted mb-0">Review and approve customer lump-sum fixed deposit applications</p>
                            </div>
                            <a href="all-fds.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> All FDs</a>
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
                                                    <th>Customer Name</th>
                                                    <th>Agent</th>
                                                    <th>Deposit Amount</th>
                                                    <th>Interest Rate</th>
                                                    <th>Tenure</th>
                                                    <th>Maturity Amount</th>
                                                    <th>Start Date</th>
                                                    <th>Maturity Date</th>
                                                    <th class="text-center" style="width: 220px;">Approval Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($pending_res && $pending_res->num_rows > 0): ?>
                                                    <?php while ($row = $pending_res->fetch_assoc()): ?>
                                                        <tr>
                                                            <td class="fw-bold">
                                                                <a href="admin-fd-details.php?id=<?php echo $row['id']; ?>" class="text-primary"><?php echo htmlspecialchars($row['fd_number']); ?></a>
                                                            </td>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($row['customer_name']); ?></div>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['customer_phone']); ?></small>
                                                            </td>
                                                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['agent_name'] ?? 'Direct'); ?></span></td>
                                                            <td class="fw-bold text-dark">₹<?php echo number_format($row['deposit_amount'], 2); ?></td>
                                                            <td><span class="badge bg-light-primary text-primary"><?php echo $row['interest_rate']; ?>% p.a.</span></td>
                                                            <td><?php echo $row['tenure']; ?> Months</td>
                                                            <td class="fw-bold text-success">₹<?php echo number_format($row['maturity_amount'], 2); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($row['start_date'])); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($row['maturity_date'])); ?></td>
                                                            <td class="text-center">
                                                                <div class="d-flex gap-1 justify-content-center">
                                                                    <form method="POST" action="pending-fd-payments.php" onsubmit="return confirm('Approve this FD application?');">
                                                                        <input type="hidden" name="fd_id" value="<?php echo $row['id']; ?>">
                                                                        <input type="hidden" name="action" value="approve">
                                                                        <button type="submit" class="btn btn-sm btn-success"><i class="ri-check-line"></i> Approve</button>
                                                                    </form>
                                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $row['id']; ?>">
                                                                        <i class="ri-close-line"></i> Reject
                                                                    </button>
                                                                </div>

                                                                <!-- Reject Modal -->
                                                                <div class="modal fade" id="rejectModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                                                    <div class="modal-dialog">
                                                                        <div class="modal-content">
                                                                            <form method="POST" action="pending-fd-payments.php">
                                                                                <div class="modal-header">
                                                                                    <h5 class="modal-title">Reject FD #<?php echo htmlspecialchars($row['fd_number']); ?></h5>
                                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                                </div>
                                                                                <div class="modal-body text-start">
                                                                                    <input type="hidden" name="fd_id" value="<?php echo $row['id']; ?>">
                                                                                    <input type="hidden" name="action" value="reject">
                                                                                    <div class="mb-3">
                                                                                        <label class="form-label">Rejection Reason</label>
                                                                                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Specify reason for rejection..." required></textarea>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="modal-footer">
                                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                                                                                </div>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="10" class="text-center py-4 text-muted">No pending Fixed Deposit applications found.</td>
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
