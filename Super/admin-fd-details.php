<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-fds.php");
    exit();
}

$fd_id = (int)$_GET['id'];

// Handle Actions (Approve, Reject, Mature, Close/Payout)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'approve') {
        $approval_date = date("Y-m-d H:i:s");
        $stmt_app = $conn->prepare("UPDATE fixed_deposits SET status = 'active', approval_date = ?, approved_by = ? WHERE id = ?");
        $stmt_app->bind_param("sii", $approval_date, $admin_id, $fd_id);
        if ($stmt_app->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Fixed Deposit approved and status set to Active.</div>";
        }
    } elseif ($action === 'reject') {
        $reason = trim($_POST['rejection_reason'] ?? 'Rejected by Admin');
        $stmt_rej = $conn->prepare("UPDATE fixed_deposits SET status = 'rejected', rejection_reason = ? WHERE id = ?");
        $stmt_rej->bind_param("si", $reason, $fd_id);
        if ($stmt_rej->execute()) {
            $_SESSION['message'] = "<div class='alert alert-warning'>Fixed Deposit application rejected.</div>";
        }
    } elseif ($action === 'mature') {
        $stmt_mat = $conn->prepare("UPDATE fixed_deposits SET status = 'matured' WHERE id = ?");
        $stmt_mat->bind_param("i", $fd_id);
        if ($stmt_mat->execute()) {
            $_SESSION['message'] = "<div class='alert alert-info'>Fixed Deposit status updated to Matured. Account is ready for final payout.</div>";
        }
    } elseif ($action === 'close_payout') {
        $payout_amount = (float)$_POST['payout_amount'];
        $payout_type = $_POST['payout_type'] ?? 'maturity';
        $payment_mode = $_POST['payment_mode'] ?? 'cash';
        $remarks = trim($_POST['remarks'] ?? '');
        $payout_date = date("Y-m-d");

        // Fetch customer_id & agent_id for the payout record
        $stmt_get = $conn->prepare("SELECT customer_id, agent_id FROM fixed_deposits WHERE id = ?");
        $stmt_get->bind_param("i", $fd_id);
        $stmt_get->execute();
        $fd_info = $stmt_get->get_result()->fetch_assoc();

        if ($fd_info) {
            $conn->begin_transaction();
            try {
                // Insert Payout Record
                $stmt_pay = $conn->prepare("INSERT INTO fd_payouts (fd_id, customer_id, agent_id, payout_amount, payout_type, payout_date, payment_mode, remarks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_pay->bind_param("iiidssssi", $fd_id, $fd_info['customer_id'], $fd_info['agent_id'], $payout_amount, $payout_type, $payout_date, $payment_mode, $remarks, $admin_id);
                $stmt_pay->execute();

                // If maturity payout, update status to closed
                if ($payout_type === 'maturity') {
                    $stmt_close = $conn->prepare("UPDATE fixed_deposits SET status = 'closed' WHERE id = ?");
                    $stmt_close->bind_param("i", $fd_id);
                    $stmt_close->execute();
                }

                $conn->commit();
                $_SESSION['message'] = "<div class='alert alert-success'>Payout of ₹" . number_format($payout_amount, 2) . " logged successfully! Account status updated.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "<div class='alert alert-danger'>Error processing payout: " . $e->getMessage() . "</div>";
            }
        }
    }

    header("Location: admin-fd-details.php?id=" . $fd_id);
    exit();
}

// Fetch FD Details
$sql = "SELECT fd.*, 
        c.full_name as customer_name, c.phone_number as customer_phone, c.email as customer_email, c.address as customer_address,
        a.full_name as agent_name, a.agent_code
        FROM fixed_deposits fd
        JOIN customers c ON fd.customer_id = c.id
        LEFT JOIN agents a ON fd.agent_id = a.id
        WHERE fd.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $fd_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    header("Location: all-fds.php");
    exit();
}

$fd = $res->fetch_assoc();

// Fetch Payouts log
$stmt_p = $conn->prepare("SELECT * FROM fd_payouts WHERE fd_id = ? ORDER BY id DESC");
$stmt_p->bind_param("i", $fd_id);
$stmt_p->execute();
$payouts_res = $stmt_p->get_result();
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
                                <h4 class="mb-1"><i class="ri-bank-line me-2"></i>Admin FD Management (#<?php echo htmlspecialchars($fd['fd_number']); ?>)</h4>
                                <p class="text-muted mb-0">Customer: <?php echo htmlspecialchars($fd['customer_name']); ?> | Agent: <?php echo htmlspecialchars($fd['agent_name'] ?? 'Direct'); ?></p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="all-fds.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to All FDs</a>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

                    <!-- Action Controls Bar for Admin -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm bg-light">
                                <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3">
                                    <div>
                                        <span class="text-muted d-block">Current Status</span>
                                        <?php
                                        $st = $fd['status'];
                                        if ($st == 'active') echo '<span class="badge bg-success fs-6">Active</span>';
                                        elseif ($st == 'pending') echo '<span class="badge bg-warning text-dark fs-6">Pending Approval</span>';
                                        elseif ($st == 'matured') echo '<span class="badge bg-info fs-6">Matured</span>';
                                        elseif ($st == 'closed') echo '<span class="badge bg-secondary fs-6">Closed</span>';
                                        else echo '<span class="badge bg-danger fs-6">Rejected</span>';
                                        ?>
                                    </div>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <?php if ($fd['status'] == 'pending'): ?>
                                            <form method="POST" action="admin-fd-details.php?id=<?php echo $fd_id; ?>" onsubmit="return confirm('Approve this FD application?');">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success"><i class="ri-check-line me-1"></i> Approve FD</button>
                                            </form>
                                            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal"><i class="ri-close-line me-1"></i> Reject FD</button>
                                        <?php endif; ?>

                                        <?php if ($fd['status'] == 'active'): ?>
                                            <form method="POST" action="admin-fd-details.php?id=<?php echo $fd_id; ?>" onsubmit="return confirm('Mark this FD as Matured?');">
                                                <input type="hidden" name="action" value="mature">
                                                <button type="submit" class="btn btn-info text-white"><i class="ri-flag-line me-1"></i> Mark Matured</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($fd['status'] == 'active' || $fd['status'] == 'matured'): ?>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#payoutModal"><i class="ri-hand-coin-line me-1"></i> Record Payout / Close</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Details Card -->
                    <div class="row">
                        <div class="col-md-8 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0 text-white"><i class="ri-information-line me-2"></i>FD Overview</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Deposit Amount</label>
                                            <h4 class="fw-bold text-dark">₹<?php echo number_format($fd['deposit_amount'], 2); ?></h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Interest Rate</label>
                                            <h4 class="fw-bold text-primary"><?php echo $fd['interest_rate']; ?>% p.a.</h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Tenure</label>
                                            <h5 class="fw-bold"><?php echo $fd['tenure']; ?> Months</h5>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Total Interest Accrued</label>
                                            <h5 class="fw-bold text-success">₹<?php echo number_format($fd['total_interest'], 2); ?></h5>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Maturity Amount</label>
                                            <h4 class="fw-bold text-success">₹<?php echo number_format($fd['maturity_amount'], 2); ?></h4>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Payout Frequency</label>
                                            <h5 class="fw-bold text-capitalize"><?php echo str_replace('_', ' ', $fd['payout_frequency']); ?></h5>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Start Date</label>
                                            <h5><?php echo date('d M Y', strtotime($fd['start_date'])); ?></h5>
                                        </div>
                                        <div class="col-sm-6">
                                            <label class="text-muted d-block">Maturity Date</label>
                                            <h5 class="text-success"><?php echo date('d M Y', strtotime($fd['maturity_date'])); ?></h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="ri-user-3-line me-2"></i>Customer & Agent Info</h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($fd['customer_name']); ?></h6>
                                    <p class="text-muted mb-2"><i class="ri-phone-line me-1"></i><?php echo htmlspecialchars($fd['customer_phone']); ?></p>
                                    <p class="text-muted mb-3"><i class="ri-mail-line me-1"></i><?php echo htmlspecialchars($fd['customer_email'] ?? 'No email'); ?></p>
                                    
                                    <hr>
                                    
                                    <small class="text-muted d-block mb-1">Managed By Agent</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($fd['agent_name'] ?? 'Direct Admin'); ?></div>
                                    <small class="text-muted">Code: <?php echo htmlspecialchars($fd['agent_code'] ?? 'N/A'); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payout Log History -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="ri-history-line me-2"></i>Payout & Interest Transaction History</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Transaction ID</th>
                                                    <th>Payout Type</th>
                                                    <th>Amount</th>
                                                    <th>Payout Date</th>
                                                    <th>Payment Mode</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($payouts_res->num_rows > 0): ?>
                                                    <?php while ($p = $payouts_res->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>#<?php echo $p['id']; ?></td>
                                                            <td><span class="badge bg-light-info text-info text-capitalize"><?php echo $p['payout_type']; ?></span></td>
                                                            <td class="fw-bold text-success">₹<?php echo number_format($p['payout_amount'], 2); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($p['payout_date'])); ?></td>
                                                            <td class="text-uppercase"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                                            <td><?php echo htmlspecialchars($p['remarks'] ?? '-'); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">No payouts or interest transactions recorded yet.</td>
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

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin-fd-details.php?id=<?php echo $fd_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject FD Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason</label>
                            <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Reason for rejecting FD..."></textarea>
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

    <!-- Payout Modal -->
    <div class="modal fade" id="payoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="admin-fd-details.php?id=<?php echo $fd_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="ri-hand-coin-line me-1"></i> Record FD Payout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="close_payout">
                        
                        <div class="mb-3">
                            <label class="form-label">Payout Type</label>
                            <select name="payout_type" class="form-select" required>
                                <option value="maturity" selected>Full Maturity Payout (Closes FD)</option>
                                <option value="interest">Periodic Interest Payout</option>
                                <option value="partial">Partial Withdrawal</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payout Amount (₹)</label>
                            <input type="number" step="0.01" name="payout_amount" class="form-control" value="<?php echo $fd['maturity_amount']; ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Payment Mode</label>
                            <select name="payment_mode" class="form-select" required>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer" selected>Bank Transfer / NEFT</option>
                                <option value="cheque">Cheque</option>
                                <option value="wallet">Agent Wallet</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks / Txn Ref</label>
                            <textarea name="remarks" class="form-control" rows="2" placeholder="e.g., NEFT Txn ID 982348234"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Payout & Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
