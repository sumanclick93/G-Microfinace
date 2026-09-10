<?php
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- Process Approval or Rejection ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['payment_id'])) {
    $payment_id = intval($_POST['payment_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        // First get the loan_id for this payment
        $get_loan = $conn->query("SELECT loan_id FROM payments WHERE id = " . intval($payment_id));
        $loan_id = ($get_loan && $row_l = $get_loan->fetch_assoc()) ? intval($row_l['loan_id']) : 0;

        $stmt = $conn->prepare("UPDATE payments SET status = 'approved' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            if ($loan_id > 0) {
                $check_loan = $conn->query("SELECT l.total_repayable_amount, COALESCE(SUM(p.amount_paid), 0) as paid FROM loans l LEFT JOIN payments p ON l.id = p.loan_id WHERE l.id = $loan_id AND p.status = 'approved'");
                if ($check_loan && $row_loan = $check_loan->fetch_assoc()) {
                    if (floatval($row_loan['paid']) >= floatval($row_loan['total_repayable_amount']) && floatval($row_loan['total_repayable_amount']) > 0) {
                        $conn->query("UPDATE loans SET status = 'paid' WHERE id = $loan_id AND status NOT IN ('closed', 'paid')");
                    }
                }
            }
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-check-line fs-4 me-2'></i> Loan payment approved and added to customer ledger.</div>";
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE payments SET status = 'rejected' WHERE id = ?");
        $stmt->bind_param("i", $payment_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-danger d-flex align-items-center'><i class='ri-close-circle-line fs-4 me-2'></i> Loan payment rejected. It will not affect the balance.</div>";
        }
        $stmt->close();
    }
    
    header("Location: pending-loan-payments.php");
    exit();
}

// --- Fetch Loan Payments with Status Filtering & Loan Category ---
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
$valid_statuses = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status_filter, $valid_statuses)) {
    $status_filter = 'pending';
}

$where_clause = "";
if ($status_filter === 'pending') {
    $where_clause = "WHERE p.status = 'pending'";
} elseif ($status_filter === 'approved') {
    $where_clause = "WHERE p.status = 'approved'";
} elseif ($status_filter === 'rejected') {
    $where_clause = "WHERE p.status = 'rejected'";
} else {
    $where_clause = "WHERE 1=1";
}

$pending_payments = [];
$sql = "SELECT 
            p.id as payment_id, p.amount_paid, p.payment_date, p.proof_image, p.notes, p.status as payment_status,
            l.id as loan_id, l.loan_type,
            c.full_name as customer_name, c.customer_id_string,
            a.first_name as agent_first, a.last_name as agent_last
        FROM payments p
        JOIN loans l ON p.loan_id = l.id
        JOIN customers c ON l.customer_id = c.id
        LEFT JOIN agents a ON c.agent_id = a.id
        $where_clause
        ORDER BY p.payment_date DESC";

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pending_payments[] = $row;
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
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title d-flex flex-wrap justify-content-between align-items-center mb-3">
                                        <div>
                                            <h5>Loan Payments Review</h5>
                                            <p class="text-muted mb-0">Review payments date-wise & loan category-wise.</p>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
                                            <label class="mb-0 font-weight-bold text-dark me-1">Filter:</label>
                                            <select class="form-select form-select-sm w-auto" onchange="location.href='pending-loan-payments.php?status=' + this.value;">
                                                <option value="pending" <?php if ($status_filter === 'pending') echo 'selected'; ?>>Pending Approval</option>
                                                <option value="approved" <?php if ($status_filter === 'approved') echo 'selected'; ?>>Approved</option>
                                                <option value="rejected" <?php if ($status_filter === 'rejected') echo 'selected'; ?>>Rejected</option>
                                                <option value="all" <?php if ($status_filter === 'all') echo 'selected'; ?>>All Payments</option>
                                            </select>
                                        </div>
                                    </div>

                                    <?php if (!empty($message)) echo $message; ?>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Customer</th>
                                                    <th>Loan Category</th>
                                                    <th>Amount</th>
                                                    <th>Proof Image</th>
                                                    <th>Action / Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_payments as $payment) : ?>
                                                    <tr>
                                                        <td style="font-size: 13px;">
                                                            <?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?><br>
                                                            <small class="text-muted"><?php echo htmlspecialchars($payment['notes']); ?></small>
                                                        </td>
                                                        <td>
                                                            <div class="user-name">
                                                                <span style="font-weight: 600;"><?php echo htmlspecialchars($payment['customer_name']); ?></span>
                                                                <span class="text-muted">(ID: <?php echo htmlspecialchars($payment['customer_id_string']); ?>)</span><br>
                                                                <small class="text-primary">Agent: <?php echo htmlspecialchars(($payment['agent_first'] ?? 'Direct') . ' ' . ($payment['agent_last'] ?? '')); ?></small>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <?php
                                                                $l_type = $payment['loan_type'] ?? 'standard';
                                                                if ($l_type === 'gold') {
                                                                    echo '<span class="badge bg-warning text-dark"><i class="ri-gold-line me-1"></i>Gold Loan</span>';
                                                                } elseif ($l_type === 'interest_only') {
                                                                    echo '<span class="badge bg-primary">Interest Loan</span>';
                                                                } else {
                                                                    echo '<span class="badge bg-info">Standard Loan</span>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <td style="font-weight: bold; color: #28a745; font-size: 15px;">
                                                            ₹<?php echo number_format($payment['amount_paid'], 2); ?>
                                                        </td>
                                                        <td>
                                                            <?php if (!empty($payment['proof_image'])): ?>
                                                                <?php $proof_path = '../api/uploads/proofs/' . $payment['proof_image']; ?>
                                                                <button class="btn btn-sm btn-info text-white view-proof-btn" 
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#proofModal" 
                                                                        data-img="<?php echo htmlspecialchars($proof_path); ?>">
                                                                    <i class="ri-image-line"></i> View Receipt
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="text-muted small">No Receipt Uploaded</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($payment['payment_status'] === 'pending'): ?>
                                                                <ul style="display: flex; gap: 10px; align-items: center; list-style: none; padding: 0; margin: 0;">
                                                                    <li>
                                                                        <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#approveModal" data-id="<?php echo $payment['payment_id']; ?>" title="Approve Payment">
                                                                            <i class="ri-check-line" style="color: #28a745; font-size: 22px; font-weight: bold;"></i>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $payment['payment_id']; ?>" title="Reject Payment">
                                                                            <i class="ri-close-line" style="color: #dc3545; font-size: 22px; font-weight: bold;"></i>
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            <?php elseif ($payment['payment_status'] === 'approved'): ?>
                                                                <span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>Approved</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger"><i class="ri-close-circle-line me-1"></i>Rejected</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
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

    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="previewImage" src="" alt="Payment Proof" style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade theme-modal" id="approveModal" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Payment?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This will officially mark the payment as <strong>Approved</strong> and deduct it from the customer's remaining loan balance.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="pending-loan-payments.php">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="payment_id" id="approveId" value="">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Yes, Approve</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade theme-modal" id="rejectModal" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Payment?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>This will mark the payment as <strong>Rejected</strong> (e.g., fake screenshot, mismatched amount). It will not affect the customer's balance.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="pending-loan-payments.php">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="payment_id" id="rejectId" value="">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Yes, Reject</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Pass Payment ID to Approve/Reject Modals
        $('.action-btn').on('click', function() {
            var paymentId = $(this).data('id');
            var targetModal = $(this).data('bs-target');
            if (targetModal === '#approveModal') {
                $('#approveId').val(paymentId);
            } else if (targetModal === '#rejectModal') {
                $('#rejectId').val(paymentId);
            }
        });

        // Pass Image Source to Preview Modal
        $('.view-proof-btn').on('click', function() {
            var imgSrc = $(this).data('img');
            $('#previewImage').attr('src', imgSrc);
        });
    });
    </script>
</body>
</html>