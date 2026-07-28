<?php
// Include the config file
include('config.php');
date_default_timezone_set('Asia/Kolkata');

$logged_time = date("Y-m-d H:i:s");
// 1. Authentication Check
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];
$message = '';

// 2. Get Loan ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customer.php");
    exit();
}
$loan_id = $_GET['id'];

// Check for a flash message from a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Handle New Payment Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount_paid'])) {
    $amount_paid = (float)$_POST['amount_paid'];
    $payment_notes = $conn->real_escape_string($_POST['notes']);
    $payment_date = $logged_time;

    if ($amount_paid > 0) {
        $conn->begin_transaction();
        try {
            // Step A: Insert into the payments table
            $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bind_param("idsis", $loan_id, $amount_paid, $payment_date, $agent_id, $payment_notes);
            $stmt_payment->execute();
            $new_payment_id = $stmt_payment->insert_id;

            // Step B: Log this as a wallet transaction
            $sql_wallet = "INSERT INTO wallet_transactions (agent_id, loan_id, payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'emi-received', ?, ?)";
            $stmt_wallet = $conn->prepare($sql_wallet);
            $description = "EMI received for Loan ID: $loan_id";
            $stmt_wallet->bind_param("iiids", $agent_id, $loan_id, $new_payment_id, $amount_paid, $description);
            $stmt_wallet->execute();
            
            // Step C: Check if the loan is now fully paid
            $total_paid_query = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE loan_id = $loan_id");
            $total_paid = $total_paid_query->fetch_assoc()['total'];
            $loan_details_query = $conn->query("SELECT total_repayable_amount FROM loans WHERE id = $loan_id");
            $total_repayable = $loan_details_query->fetch_assoc()['total_repayable_amount'];

            if ($total_paid >= $total_repayable) {
                $conn->query("UPDATE loans SET status = 'paid' WHERE id = $loan_id");
            }

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success'>Payment logged successfully!</div>";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error: Failed to log payment.</div>";
        }
        header("Location: loan-details.php?id=" . $loan_id);
        exit();
    }
}

// 4. Fetch all Loan and Customer Details (MODIFIED QUERY)
$loan = null;
$sql = "SELECT 
            l.*, 
            c.full_name, c.phone, c.email, c.avatar as customer_avatar, c.aadhar_photo, c.pan_photo 
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        WHERE l.id = ? AND l.agent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $loan_id, $agent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $loan = $result->fetch_assoc();
} else {
    // Security: Redirect if loan not found or doesn't belong to this agent
    $_SESSION['message'] = "<div class='alert alert-danger'>Loan not found or access denied.</div>";
    header("Location: all-customer.php");
    exit();
}

// 5. Fetch Payment History
$payments = [];
$total_paid = 0;
// (rest of PHP is unchanged)
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
$status_clean = strtolower(trim($loan['status']));
if ($total_paid >= (float)$loan['total_repayable_amount'] - 0.01 && !in_array($status_clean, ['closed', 'paid', 'rejected', 'premature-closed'])) {
    $conn->query("UPDATE loans SET status = 'paid' WHERE id = " . intval($loan_id));
    $loan['status'] = 'paid';
    $status_clean = 'paid';
}

$paid_emis_count = 0;
foreach ($payments as $p) {
    if ($p['status'] === 'approved') $paid_emis_count++;
}

if (in_array($status_clean, ['closed', 'paid'])) {
    $progress_percentage = 100;
    $total_paid = max($total_paid, (float)$loan['total_repayable_amount']);
    $remaining_balance = 0;
    $paid_emis_count = (int)$loan['tenure'];
} else {
    $remaining_balance = max(0, $loan['total_repayable_amount'] - $total_paid);
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
                                        <li class="list-group-item d-flex justify-content-between"><strong>Status:</strong> <span class="badge bg-primary"><?php echo ucfirst($loan['status']); ?></span></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Customer:</strong> <?php echo htmlspecialchars($loan['full_name']); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Principal Amount:</strong> ₹<?php echo number_format($loan['loan_amount'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Total Repayable:</strong> ₹<?php echo number_format($loan['total_repayable_amount'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Installment (EMI):</strong> ₹<?php echo number_format($loan['monthly_installment'], 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Tenure (Total EMIs):</strong> <?php echo $loan['tenure'] . ' ' . ucfirst($loan['repayment_cycle']) . 's'; ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>EMIs Paid:</strong> <span><strong><?php echo $paid_emis_count; ?></strong> of <?php echo $loan['tenure']; ?></span></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Customer Documents</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Profile Photo</span>
                                            <?php if (!empty($loan['customer_avatar'])): ?>
                                                <a href="upload/customers/avatars/<?php echo $loan['customer_avatar']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Photo</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Aadhar Card</span>
                                            <?php if (!empty($loan['aadhar_photo'])): ?>
                                                <a href="upload/customers/aadhar_cards/<?php echo $loan['aadhar_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Aadhar</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>PAN Card</span>
                                            <?php if (!empty($loan['pan_photo'])): ?>
                                                <a href="upload/customers/pan_cards/<?php echo $loan['pan_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View PAN</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Log New Payment</h5>
                                    <?php if ($loan['status'] == 'approved' || $loan['status'] == 'active'): ?>
                                        <form method="POST" action="loan-details.php?id=<?php echo $loan_id; ?>">
                                            <div class="mb-3"><label for="amount_paid" class="form-label">Amount Paid (₹)</label><input type="number" step="0.01" class="form-control" name="amount_paid" id="amount_paid" value="<?php echo number_format($loan['monthly_installment'], 2, '.', ''); ?>" required></div>
                                            <div class="mb-3"><label for="notes" class="form-label">Notes (Receipt #, etc.)</label><input type="text" class="form-control" name="notes" id="notes" placeholder="Optional notes"></div>
                                            <button type="submit" class="btn btn-primary w-100">Log Payment</button>
                                        </form>
                                    <?php elseif ($loan['status'] == 'paid'): ?>
                                        <div class="alert alert-success text-center" role="alert">This loan has been fully paid.</div>
                                    <?php else: ?>
                                        <div class="alert alert-warning text-center" role="alert">Payments can only be logged for 'Approved' or 'Active' loans.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-body">
                                     <h5 class="card-title">Payment Progress</h5>
                                     <div class="progress mb-3" style="height: 25px;"><div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%;" aria-valuenow="<?php echo $progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($progress_percentage); ?>%</div></div>
                                     <ul class="list-group list-group-flush mb-3">
                                        <li class="list-group-item d-flex justify-content-between text-success"><strong>Total Paid:</strong> ₹<?php echo number_format($total_paid, 2); ?></li>
                                        <li class="list-group-item d-flex justify-content-between text-danger"><strong>Remaining Balance:</strong> ₹<?php echo number_format($remaining_balance, 2); ?></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card card-table">
                                <div class="card-body">
                                    <h5 class="card-title">Payment History</h5>
                                    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Amount Paid (₹)</th><th>Notes</th></tr></thead><tbody><?php if (empty($payments)): ?><tr><td colspan="3" class="text-center text-muted">No payments have been made yet.</td></tr><?php else: ?><?php foreach ($payments as $payment): ?><tr><td><?php echo date('d M Y, h:i A', strtotime($payment['payment_date'])); ?></td><td><?php echo number_format($payment['amount_paid'], 2); ?></td><td><?php echo htmlspecialchars($payment['notes']); ?></td></tr><?php endforeach; ?><?php endif; ?></tbody></table></div>
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