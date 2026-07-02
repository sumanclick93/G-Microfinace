<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
$message = '';

// 2. Get RD ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-rds.php"); // Redirect if ID is missing
    exit();
}
$rd_id = $_GET['id'];

// Check for flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Handle Admin Actions (Approve/Reject)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Check if the RD is actually pending
    $status_check_stmt = $conn->prepare("SELECT status FROM recurring_deposits WHERE id = ?");
    $status_check_stmt->bind_param("i", $rd_id);
    $status_check_stmt->execute();
    $result = $status_check_stmt->get_result();
    $current_status = ($result->num_rows > 0) ? $result->fetch_assoc()['status'] : null;
    $status_check_stmt->close();

    if ($current_status == 'pending') {
        if ($action == 'approve') {
            // Update status to 'active' and set approval date
            $approve_stmt = $conn->prepare("UPDATE recurring_deposits SET status = 'active', approval_date = NOW() WHERE id = ?");
            $approve_stmt->bind_param("i", $rd_id);
            if ($approve_stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>RD application approved and activated.</div>";
            } else {
                 $_SESSION['message'] = "<div class='alert alert-danger'>Error approving RD.</div>";
            }
            $approve_stmt->close();

        } elseif ($action == 'reject') {
            // Update status to 'rejected' and add notes
            $reject_notes = $_POST['notes'] ?? 'RD application rejected by admin.';
            $reject_stmt = $conn->prepare("UPDATE recurring_deposits SET status = 'rejected', notes = ? WHERE id = ?");
            $reject_stmt->bind_param("si", $reject_notes, $rd_id);
             if ($reject_stmt->execute()) {
                $_SESSION['message'] = "<div class='alert alert-warning'>RD application has been rejected.</div>";
             } else {
                 $_SESSION['message'] = "<div class='alert alert-danger'>Error rejecting RD.</div>";
             }
             $reject_stmt->close();
        }
    } else if ($current_status) {
         $_SESSION['message'] = "<div class='alert alert-info'>This RD is no longer pending and cannot be actioned. Status: ".ucfirst($current_status)."</div>";
    } else {
         $_SESSION['message'] = "<div class='alert alert-danger'>RD record not found.</div>";
    }

    header("Location: admin-rd-details.php?id=" . $rd_id);
    exit();
}


// 4. Fetch all RD, Customer, and Agent Details
$rd = null;
$sql = "SELECT rd.*,
               c.full_name as customer_name, c.phone as customer_phone, c.avatar as customer_avatar,
               a.first_name as agent_first, a.last_name as agent_last
        FROM recurring_deposits rd
        JOIN customers c ON rd.customer_id = c.id
        JOIN agents a ON rd.agent_id = a.id
        WHERE rd.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $rd_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $rd = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "<div class='alert alert-danger'>Recurring Deposit not found.</div>";
    header("Location: all-rds.php"); // Redirect to admin's all RDs page
    exit();
}

// 5. Fetch Payment History for this RD
$rd_payments = [];
$total_principal_paid = 0;
$installments_paid_count = 0;
$stmt_payments = $conn->prepare("SELECT * FROM rd_payments WHERE rd_id = ? ORDER BY payment_date DESC");
$stmt_payments->bind_param("i", $rd_id);
$stmt_payments->execute();
$payments_result = $stmt_payments->get_result();
if ($payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $rd_payments[] = $row;
        $total_principal_paid += $row['amount_paid'];
    }
    $installments_paid_count = $payments_result->num_rows;
}
$progress_percentage = ($rd['tenure'] > 0) ? ($installments_paid_count / $rd['tenure']) * 100 : 0;

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
                             <div class="title-header option-title"><h5>Recurring Deposit Details</h5></div>
                             <?php if (!empty($message)) echo $message; ?>
                        </div>

                         <div class="col-lg-5">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">RD Summary</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between"><strong>Status:</strong>
                                            <?php
                                                $status_color = 'primary'; // active
                                                if ($rd['status'] == 'pending') $status_color = 'warning';
                                                elseif ($rd['status'] == 'matured' || $rd['status'] == 'closed') $status_color = 'success';
                                                elseif ($rd['status'] == 'premature-closed') $status_color = 'info';
                                                elseif ($rd['status'] == 'rejected') $status_color = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucwords(str_replace('-', ' ', $rd['status'])); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Installment:</strong> ₹<?php echo number_format($rd['deposit_amount'], 2); ?> / <?php echo ucfirst($rd['repayment_cycle']); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Tenure:</strong> <?php echo $rd['tenure']; ?> Installments</li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Interest Rate:</strong> <?php echo $rd['interest_rate']; ?>% p.a.</li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Maturity Amount:</strong> ₹<?php echo number_format($rd['maturity_amount'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Start Date:</strong> <?php echo date('d M, Y', strtotime($rd['start_date'])); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Maturity Date:</strong> <?php echo date('d M, Y', strtotime($rd['maturity_date'])); ?></li>
                                        <?php if ($rd['approval_date']): ?>
                                            <li class="list-group-item d-flex justify-content-between"><strong>Approval Date:</strong> <?php echo date('d M, Y H:i', strtotime($rd['approval_date'])); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                             <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">Customer & Agent</h5>
                                    <div class="d-flex align-items-center mb-2">
                                         <?php $avatar_path = !empty($rd['customer_avatar']) ? '/Agents/upload/customers/avatars/' . $rd['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                         <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid rounded-circle me-2" alt="Avatar" style="width: 40px; height: 40px;">
                                         <div>
                                            <p class="mb-0"><strong>Customer:</strong> <?php echo htmlspecialchars($rd['customer_name']); ?></p>
                                            <p class="mb-0"><i class="ri-phone-line"></i> <?php echo htmlspecialchars($rd['customer_phone']); ?></p>
                                         </div>
                                    </div>
                                    <hr>
                                    <p class="mb-0"><strong>Created By Agent:</strong> <?php echo htmlspecialchars($rd['agent_first'] . ' ' . $rd['agent_last']); ?></p>
                                </div>
                            </div>

                            <?php if ($rd['status'] == 'pending'): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Take Action</h5>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="admin-rd-details.php?id=<?php echo $rd_id; ?>" class="w-100">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success w-100">Approve RD</button>
                                        </form>
                                        <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject RD</button>
                                    </div>
                                </div>
                            </div>
                            <?php elseif (!empty($rd['notes'])): ?>
                             <div class="card">
                                <div class="card-body">
                                     <h5 class="card-title mb-2">Admin Notes</h5>
                                     <p class="text-muted fst-italic"><?php echo nl2br(htmlspecialchars($rd['notes'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>

                        </div>

                        <div class="col-lg-7">
                             <div class="card">
                                <div class="card-body">
                                     <h5 class="card-title">Deposit Progress</h5>
                                     <div class="progress mb-3" style="height: 25px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%;"><?php echo round($progress_percentage); ?>%</div>
                                     </div>
                                     <ul class="list-group list-group-flush mb-3">
                                         <li class="list-group-item d-flex justify-content-between"><strong>Installments Paid:</strong> <?php echo $installments_paid_count; ?> / <?php echo $rd['tenure']; ?></li>
                                         <li class="list-group-item d-flex justify-content-between text-success"><strong>Total Principal Deposited:</strong> ₹<?php echo number_format($total_principal_paid, 2); ?></li>
                                     </ul>
                                </div>
                            </div>
                            <div class="card card-table">
                                <div class="card-body">
                                    <h5 class="card-title">Deposit History</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead><tr><th>Date</th><th>Amount Deposited (₹)</th><th>Notes</th></tr></thead>
                                            <tbody>
                                                 <?php if (empty($rd_payments)): ?>
                                                    <tr><td colspan="3" class="text-center text-muted">No deposits have been made yet.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($rd_payments as $payment): ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td>
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

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="admin-rd-details.php?id=<?php echo $rd_id; ?>">
                    <div class="modal-header"><h5 class="modal-title">Reject RD Application</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <p>Are you sure? Add an optional reason below.</p>
                        <input type="hidden" name="action" value="reject">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Reason for rejection (optional)"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>