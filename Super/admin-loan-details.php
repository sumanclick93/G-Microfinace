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

        $status_clean_check = strtolower(trim($status));
        if (in_array($status_clean_check, ['rejected', 'paid', 'closed', 'premature-closed'])) {
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
    } elseif ($action == 'close' || $action == 'close_loan') {
        $notes = $_POST['notes'] ?? 'Closed by admin.';
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        
        $conn->begin_transaction();
        try {
            if ($amount_paid > 0) {
                $stmt_pay = $conn->prepare("INSERT INTO payments (loan_id, amount_paid, payment_date, notes, status) VALUES (?, ?, NOW(), ?, 'approved')");
                $stmt_pay->bind_param("ids", $loan_id, $amount_paid, $notes);
                $stmt_pay->execute();
                $stmt_pay->close();

                $stmt_ag = $conn->prepare("SELECT agent_id FROM loans WHERE id = ?");
                $stmt_ag->bind_param("i", $loan_id);
                $stmt_ag->execute();
                $res_ag = $stmt_ag->get_result();
                $ag_id = ($res_ag->num_rows > 0) ? intval($res_ag->fetch_assoc()['agent_id']) : 0;
                $stmt_ag->close();

                if ($ag_id > 0) {
                    $trans_desc = "Admin Closure Settlement: " . $notes;
                    $stmt_wallet = $conn->prepare("INSERT INTO wallet_transactions (agent_id, loan_id, transaction_type, amount, description) VALUES (?, ?, 'emi-received', ?, ?)");
                    $stmt_wallet->bind_param("iids", $ag_id, $loan_id, $amount_paid, $trans_desc);
                    $stmt_wallet->execute();
                    $stmt_wallet->close();

                    $conn->query("UPDATE agent_wallets SET balance = balance + $amount_paid WHERE agent_id = $ag_id");
                }
            }

            $stmt_close = $conn->prepare("UPDATE loans SET status = 'closed', notes = CONCAT(IFNULL(notes, ''), '\n[Admin Closed]: ', ?) WHERE id = ?");
            $stmt_close->bind_param("si", $notes, $loan_id);
            $stmt_close->execute();
            $stmt_close->close();

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success'>Loan account has been closed successfully. Status updated to 'Closed'.</div>";
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error closing loan: " . $exception->getMessage() . "</div>";
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
$paid_emis_count = 0;
// (rest of the PHP logic is unchanged)
$stmt_payments = $conn->prepare("SELECT * FROM payments WHERE loan_id = ? ORDER BY payment_date DESC");
$stmt_payments->bind_param("i", $loan_id);
$stmt_payments->execute();
$payments_result = $stmt_payments->get_result();
if ($payments_result->num_rows > 0) {
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
        if ($row['status'] !== 'rejected') {
            $total_paid += $row['amount_paid'];
        }
        if ($row['status'] === 'approved') {
            $paid_emis_count++;
        }
    }
}
$status_clean = strtolower(trim($loan['status']));
if ($total_paid >= (float)$loan['total_repayable_amount'] - 0.01 && !in_array($status_clean, ['closed', 'paid', 'rejected', 'premature-closed'])) {
    $conn->query("UPDATE loans SET status = 'paid' WHERE id = " . intval($loan_id));
    $loan['status'] = 'paid';
    $status_clean = 'paid';
}

if (in_array($status_clean, ['closed', 'paid'])) {
    $progress_percentage = 100;
    $total_paid = max($total_paid, (float)$loan['total_repayable_amount']);
    $paid_emis_count = (int)$loan['tenure'];
} else {
    $progress_percentage = ($loan['total_repayable_amount'] > 0) ? ($total_paid / $loan['total_repayable_amount']) * 100 : 0;
    if ($loan['monthly_installment'] > 0) {
        $calc_emis = (int)floor($total_paid / (float)$loan['monthly_installment']);
        $paid_emis_count = min((int)$loan['tenure'], max($paid_emis_count, $calc_emis));
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
                        <div class="col-12">
                             <div class="title-header option-title"><h5>Loan Details</h5></div>
                             <?php if (!empty($message)) echo $message; ?>
                        </div>

                        <div class="col-lg-5">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Loan Summary</h5>
                                    <ul class="list-group list-group-flush">
                                         <li class="list-group-item d-flex justify-content-between align-items-center">
                                             <strong>Loan Type:</strong>
                                             <?php
                                                 $l_type = $loan['loan_type'] ?? 'standard';
                                                 if ($l_type === 'gold') {
                                                     echo '<span class="badge bg-warning text-dark"><i class="ri-gold-line me-1"></i>Gold Loan</span>';
                                                 } elseif ($l_type === 'interest_only') {
                                                     echo '<span class="badge bg-primary">Interest-Only Loan</span>';
                                                 } else {
                                                     echo '<span class="badge bg-info">Standard Loan</span>';
                                                 }
                                             ?>
                                         </li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Status:</strong> <span class="badge bg-primary"><?php echo ucfirst($loan['status']); ?></span></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Principal Amount:</strong> ₹<?php echo number_format($loan['loan_amount'], 2); ?></li>
                                         <?php if (!empty($loan['processing_fee']) && floatval($loan['processing_fee']) > 0): ?>
                                             <li class="list-group-item d-flex justify-content-between"><strong>Processing Fee:</strong> ₹<?php echo number_format($loan['processing_fee'], 2); ?></li>
                                         <?php endif; ?>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Total Repayable:</strong> ₹<?php echo number_format($loan['total_repayable_amount'], 2); ?></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Installment:</strong> ₹<?php echo number_format($loan['monthly_installment'], 2); ?> <?php if(($loan['interest_calculation_type'] ?? '') === 'monthly_interest_only') echo '<small class="text-muted">(Interest Only)</small>'; ?></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Tenure (Total EMIs):</strong> <?php echo $loan['tenure'] . ' ' . ucfirst($loan['repayment_cycle']) . 's'; ?></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>EMIs Paid:</strong> <span><strong><?php echo $paid_emis_count; ?></strong> of <?php echo $loan['tenure']; ?></span></li>
                                    </ul>
                                </div>
                            </div>

                            <?php if (($loan['loan_type'] ?? '') === 'gold'): ?>
                             <div class="card border-warning">
                                 <div class="card-body">
                                     <h5 class="card-title mb-3 text-warning"><i class="ri-gold-line me-1"></i> Gold Collateral Details</h5>
                                     <ul class="list-group list-group-flush">
                                         <li class="list-group-item d-flex justify-content-between"><strong>Gold Weight:</strong> <span><?php echo floatval($loan['gold_weight_grams']); ?> Grams</span></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Admin Rate per Gram:</strong> <span>₹<?php echo number_format($loan['gold_rate_per_gram'], 2); ?></span></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Gold Valuation:</strong> <strong class="text-success">₹<?php echo number_format(floatval($loan['gold_weight_grams']) * floatval($loan['gold_rate_per_gram']), 2); ?></strong></li>
                                         <li class="list-group-item d-flex justify-content-between align-items-center">
                                             <strong>Gold Item Photo:</strong>
                                             <?php if (!empty($loan['gold_photo_path'])): ?>
                                                 <?php
                                                     $raw_g_path = $loan['gold_photo_path'];
                                                     if (strpos($raw_g_path, 'http') === 0) {
                                                         $gold_img_url = $raw_g_path;
                                                     } else {
                                                         $rel_path = (strpos($raw_g_path, 'Agents/') === 0) ? '../' . $raw_g_path : '../Agents/' . ltrim($raw_g_path, '/');
                                                         $gold_img_url = $rel_path;
                                                         if (!file_exists(__DIR__ . '/' . $rel_path)) {
                                                             if (strpos($rel_path, '/upload/') !== false) {
                                                                 $alt_path = str_replace('/upload/', '/uploads/', $rel_path);
                                                                 if (file_exists(__DIR__ . '/' . $alt_path)) $gold_img_url = $alt_path;
                                                             } elseif (strpos($rel_path, '/uploads/') !== false) {
                                                                 $alt_path = str_replace('/uploads/', '/upload/', $rel_path);
                                                                 if (file_exists(__DIR__ . '/' . $alt_path)) $gold_img_url = $alt_path;
                                                             }
                                                         }
                                                     }
                                                 ?>
                                                 <a href="<?php echo htmlspecialchars($gold_img_url); ?>" target="_blank" class="btn btn-sm btn-warning">View Photo</a>
                                             <?php else: ?>
                                                 <span class="text-muted">No Photo Uploaded</span>
                                             <?php endif; ?>
                                         </li>
                                     </ul>
                                     <?php if (!empty($loan['gold_photo_path'])): ?>
                                         <div class="mt-3 text-center">
                                             <img src="<?php echo htmlspecialchars($gold_img_url); ?>" alt="Gold Collateral" class="img-fluid rounded border" style="max-height: 180px;">
                                         </div>
                                     <?php endif; ?>
                                 </div>
                             </div>
                             <?php endif; ?>

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
                            
                            <?php if ($status_clean == 'pending'): ?>
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
                            <?php elseif (!in_array($status_clean, ['rejected', 'paid', 'closed', 'premature-closed']) && $total_paid < (float)$loan['total_repayable_amount'] - 0.01): ?>
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Account Management</h5>
                                    <button class="btn btn-warning w-100" data-bs-toggle="modal" data-bs-target="#closeLoanModal"><i class="ri-lock-2-line me-1"></i> Close Loan Account</button>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (in_array($status_clean, ['rejected', 'paid', 'closed', 'premature-closed']) || $total_paid >= (float)$loan['total_repayable_amount'] - 0.01): ?>
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

    <div class="modal fade" id="closeLoanModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="admin-loan-details.php?id=<?php echo $loan_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title">Close Loan Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>You are about to close this active loan. Once closed, the status will be updated to <strong>Closed</strong> across all views.</p>
                        <div class="mb-3">
                            <label class="form-label">Final Settlement / Amount Paid Today (Optional)</label>
                            <input type="number" step="0.01" name="amount_paid" class="form-control" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Closure Notes / Reason</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Enter settlement or closure notes..."></textarea>
                        </div>
                        <input type="hidden" name="action" value="close_loan">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Confirm Closure</button>
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