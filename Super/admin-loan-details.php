<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
$message = '';

// 2. Get Loan ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customers-loans.php");
    exit();
}
$loan_id = $_GET['id'];

// Check for a flash message from a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Handle Admin Actions (Approve/Reject/Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'delete') {
        // Fetch current status
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
                header("Location: all-loans.php");
                exit();
            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting loan. Transaction rolled back.</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Only rejected or completed loans can be deleted.</div>";
        }
        header("Location: admin-loan-details.php?id=" . $loan_id);
        exit();
    }

    // Fetch loan amount and agent_id for the transaction
    $loan_info_stmt = $conn->prepare("SELECT loan_amount, agent_id FROM loans WHERE id = ? AND status = 'pending'");
    $loan_info_stmt->bind_param("i", $loan_id);
    $loan_info_stmt->execute();
    $loan_info_result = $loan_info_stmt->get_result();

    if ($loan_info_result->num_rows > 0) {
        $loan_data = $loan_info_result->fetch_assoc();
        $loan_amount = $loan_data['loan_amount'];
        $agent_id_for_loan = $loan_data['agent_id'];

        if ($action == 'approve') {
            $conn->begin_transaction();
            try {
                // Step A: Update loan status to 'active'
                $update_loan_stmt = $conn->prepare("UPDATE loans SET status = 'active', approval_date = COALESCE(approval_date, NOW()) WHERE id = ?");
                $update_loan_stmt->bind_param("i", $loan_id);
                $update_loan_stmt->execute();

                // Step B: Debit the amount from the agent's wallet
                $update_wallet_stmt = $conn->prepare("UPDATE agent_wallets SET balance = balance - ? WHERE agent_id = ?");
                $update_wallet_stmt->bind_param("di", $loan_amount, $agent_id_for_loan);
                $update_wallet_stmt->execute();

                // Step C: Log the transaction in wallet_transactions
                $log_stmt = $conn->prepare("INSERT INTO wallet_transactions (agent_id, loan_id, transaction_type, amount, description) VALUES (?, ?, 'loan-debit', ?, ?)");
                $description = "Loan disbursement for Loan ID: $loan_id";
                $log_stmt->bind_param("iids", $agent_id_for_loan, $loan_id, $loan_amount, $description);
                $log_stmt->execute();
                
                $conn->commit();
                $_SESSION['message'] = "<div class='alert alert-success'>Loan approved and amount debited from agent's wallet.</div>";

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $_SESSION['message'] = "<div class='alert alert-danger'>Error processing approval. Transaction rolled back.</div>";
            }
        } elseif ($action == 'reject') {
            $reject_notes = $_POST['notes'] ?? 'Loan application rejected by admin.';
            $reject_stmt = $conn->prepare("UPDATE loans SET status = 'rejected', notes = ? WHERE id = ?");
            $reject_stmt->bind_param("si", $reject_notes, $loan_id);
            $reject_stmt->execute();
            $_SESSION['message'] = "<div class='alert alert-warning'>Loan has been rejected.</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>This loan is not pending and cannot be actioned.</div>";
    }

    header("Location: admin-loan-details.php?id=" . $loan_id);
    exit();
}


// 4. Fetch all Loan, Customer, and Agent Details (MODIFIED QUERY)
$loan = null;
$sql = "SELECT 
            l.*, 
            c.full_name, c.phone, c.email, c.avatar as customer_avatar, c.aadhar_photo, c.pan_photo,
            a.first_name as agent_first, a.last_name as agent_last
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id
        JOIN agents a ON l.agent_id = a.id
        WHERE l.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $loan = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "<div class='alert alert-danger'>Loan not found.</div>";
    header("Location: all-customers-loans.php");
    exit();
}

// 5. Fetch Payment History
$payments = [];
$total_paid = 0;
// (rest of the PHP logic is unchanged)
$stmt_payments = $conn->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
$stmt_payments->bind_param("i", $loan_id);
$stmt_payments->execute();
$payments_result = $stmt_payments->get_result();
if ($payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
        $total_paid += $row['amount_paid'];
    }
}
$progress_percentage = ($loan['total_repayable_amount'] > 0) ? ($total_paid / $loan['total_repayable_amount']) * 100 : 0;
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
                             <div class="title-header option-title"><h5>Loan Details</h5></div>
                             <?php if (!empty($message)) echo $message; ?>
                        </div>

                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Loan Summary</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between"><strong>Status:</strong> <span class="badge bg-primary"><?php echo ucfirst($loan['status']); ?></span></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Principal Amount:</strong> ₹<?php echo number_format($loan['loan_amount'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Total Repayable:</strong> ₹<?php echo number_format($loan['total_repayable_amount'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Installment:</strong> ₹<?php echo number_format($loan['monthly_installment'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Tenure:</strong> <?php echo $loan['tenure'] . ' ' . ucfirst($loan['repayment_cycle']) . 's'; ?></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-2">Customer & Agent</h5>
                                    <p class="mb-0"><strong>Customer:</strong> <?php echo htmlspecialchars($loan['full_name']); ?></p>
                                    <p class="mb-0"><i class="ri-phone-line"></i> <?php echo htmlspecialchars($loan['phone']); ?></p>
                                    <hr>
                                    <p class="mb-0"><strong>Applied By Agent:</strong> <?php echo htmlspecialchars($loan['agent_first'] . ' ' . $loan['agent_last']); ?></p>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Customer Documents</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Profile Photo</span>
                                            <?php if (!empty($loan['customer_avatar'])): ?>
                                                <a href="../Agents/upload/customers/avatars/<?php echo $loan['customer_avatar']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Photo</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Aadhar Card</span>
                                            <?php if (!empty($loan['aadhar_photo'])): ?>
                                                <a href="../Agents/upload/customers/aadhar_cards/<?php echo $loan['aadhar_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Aadhar</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>PAN Card</span>
                                            <?php if (!empty($loan['pan_photo'])): ?>
                                                <a href="../Agents/upload/customers/pan_cards/<?php echo $loan['pan_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View PAN</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($loan['status'] == 'pending'): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Take Action</h5>
                                    <div class="d-flex gap-2">
                                        <form method="POST" action="admin-loan-details.php?id=<?php echo $loan_id; ?>" class="w-100">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success w-100">Approve</button>
                                        </form>
                                        <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($loan['status'], ['rejected', 'paid', 'closed'])): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Admin Actions</h5>
                                    <button class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="ri-delete-bin-line me-1"></i> Delete Loan Record</button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-lg-7">
                           <div class="card">
                                <div class="card-body">
                                     <h5 class="card-title">Payment Progress</h5>
                                     <div class="progress mb-3" style="height: 25px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progress_percentage; ?>%;"><?php echo round($progress_percentage); ?>%</div>
                                     </div>
                                     <div class="d-flex justify-content-between">
                                        <span><strong>Paid:</strong> ₹<?php echo number_format($total_paid, 2); ?></span>
                                        <span><strong>Total:</strong> ₹<?php echo number_format($loan['total_repayable_amount'], 2); ?></span>
                                     </div>
                                </div>
                            </div>
                            <div class="card card-table">
                                <div class="card-body">
                                    <h5 class="card-title">Payment History</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead><tr><th>Date</th><th>Amount Paid (₹)</th><th>Notes</th></tr></thead>
                                            <tbody>
                                                <?php if (empty($payments)): ?>
                                                    <tr><td colspan="3" class="text-center text-muted">No payments have been made.</td></tr>
                                                <?php else: ?>
                                                    <?php foreach ($payments as $payment): ?>
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
                <form method="POST" action="admin-loan-details.php?id=<?php echo $loan_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Loan Application</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to reject this loan? You can add an optional reason below.</p>
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

    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="admin-loan-details.php?id=<?php echo $loan_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Loan Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-danger"><strong>Warning:</strong> This action is permanent and cannot be undone.</p>
                        <p>This will delete the loan record along with all associated payments and transaction histories.</p>
                        <input type="hidden" name="action" value="delete">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>