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

// 2. Get RD ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-rds.php"); // Redirect if ID is missing or invalid
    exit();
}
$rd_id = $_GET['id'];

// Check for a flash message from a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Handle New RD Payment Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount_paid'])) {
    $amount_paid = (float)$_POST['amount_paid'];
    $payment_notes = $conn->real_escape_string($_POST['notes']);
    $payment_date = $logged_time;

    // Fetch RD details needed for validation and logging
    $rd_check_stmt = $conn->prepare("SELECT deposit_amount, tenure, status FROM recurring_deposits WHERE id = ? AND agent_id = ?");
    $rd_check_stmt->bind_param("ii", $rd_id, $agent_id);
    $rd_check_stmt->execute();
    $rd_check_result = $rd_check_stmt->get_result();

    if ($rd_check_result->num_rows > 0) {
        $rd_data = $rd_check_result->fetch_assoc();

        // Server-side validation: Only allow payments if RD is 'active'
        if ($rd_data['status'] !== 'active') {
            $_SESSION['message'] = "<div class='alert alert-warning'>Payments can only be logged for active RDs.</div>";
            header("Location: rd-details.php?id=" . $rd_id);
            exit();
        }

        // Validate amount (e.g., must match installment amount - adjust if needed)
        if ($amount_paid <= 0 /* || $amount_paid != $rd_data['deposit_amount'] */) {
             $_SESSION['message'] = "<div class='alert alert-warning'>Invalid payment amount entered.</div>";
             // Optional: You might enforce the amount matches the installment exactly
             // $message = "<div class='alert alert-warning'>Payment amount must be exactly ₹" . number_format($rd_data['deposit_amount'], 2) . ".</div>";
             header("Location: rd-details.php?id=" . $rd_id);
             exit();
        }

        $conn->begin_transaction();
        try {
            // Step A: Insert into the rd_payments table
            $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, collected_by_agent_id, notes) VALUES (?, ?, ?, ?, ?)";
            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bind_param("idsis", $rd_id, $amount_paid, $payment_date, $agent_id, $payment_notes);
            $stmt_payment->execute();
            $new_rd_payment_id = $stmt_payment->insert_id;

            // Step B: Log this as a wallet transaction
            $sql_wallet = "INSERT INTO wallet_transactions (agent_id, rd_id, rd_payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'rd-received', ?, ?)";
            $stmt_wallet = $conn->prepare($sql_wallet);
            $description = "RD installment received for RD ID: $rd_id";
            $stmt_wallet->bind_param("iiids", $agent_id, $rd_id, $new_rd_payment_id, $amount_paid, $description);
            $stmt_wallet->execute();

            // Step C: Check if the RD has reached maturity based on number of payments
            $payment_count_query = $conn->query("SELECT COUNT(id) as count FROM rd_payments WHERE rd_id = $rd_id");
            $payment_count = $payment_count_query->fetch_assoc()['count'];

            if ($payment_count >= $rd_data['tenure']) {
                $conn->query("UPDATE recurring_deposits SET status = 'matured' WHERE id = $rd_id");
            }

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success'>RD payment logged successfully!</div>";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error: Failed to log RD payment. Transaction rolled back.</div>";
        }
        header("Location: rd-details.php?id=" . $rd_id);
        exit();

    } else {
        // RD not found or doesn't belong to agent
        $_SESSION['message'] = "<div class='alert alert-danger'>Invalid RD account.</div>";
        header("Location: all-rds.php");
        exit();
    }
    $rd_check_stmt->close();
}


// 4. Fetch all RD and Customer Details
$rd = null;
$sql = "SELECT rd.*, c.full_name as customer_name, c.phone as customer_phone
        FROM recurring_deposits rd
        JOIN customers c ON rd.customer_id = c.id
        WHERE rd.id = ? AND rd.agent_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $rd_id, $agent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $rd = $result->fetch_assoc();
} else {
    // Security: Redirect if RD not found or doesn't belong to this agent's customer
    $_SESSION['message'] = "<div class='alert alert-danger'>RD account not found or access denied.</div>";
    header("Location: all-rds.php");
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

// Calculate progress
$total_principal_due = (float)$rd['deposit_amount'] * (int)$rd['tenure'];
$status_clean = strtolower(trim($rd['status']));
if ($total_principal_paid >= $total_principal_due - 0.01 && !in_array($status_clean, ['matured', 'closed', 'premature-closed', 'rejected'])) {
    $conn->query("UPDATE recurring_deposits SET status = 'matured' WHERE id = " . intval($rd_id));
    $rd['status'] = 'matured';
    $status_clean = 'matured';
}

$installments_paid_count = 0;
foreach ($rd_payments as $rp) {
    if ($rp['status'] === 'approved') $installments_paid_count++;
}

if (in_array($status_clean, ['matured', 'closed'])) {
    $installments_paid_count = (int)$rd['tenure'];
    $progress_percentage = 100;
    $remaining_installments = 0;
    $total_principal_paid = max($total_principal_paid, $total_principal_due);
} elseif ($status_clean === 'premature-closed') {
    $progress_percentage = 100;
    $remaining_installments = 0;
} else {
    if ($rd['deposit_amount'] > 0) {
        $calc_inst = (int)floor($total_principal_paid / (float)$rd['deposit_amount']);
        $installments_paid_count = min((int)$rd['tenure'], max($installments_paid_count, $calc_inst));
    }
    $remaining_installments = max(0, (int)$rd['tenure'] - $installments_paid_count);
    $progress_percentage = ($rd['tenure'] > 0) ? ($installments_paid_count / $rd['tenure']) * 100 : 0;
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
                                                if ($status_clean == 'pending') $status_color = 'warning';
                                                elseif ($status_clean == 'matured' || $status_clean == 'closed') $status_color = 'success';
                                                elseif ($status_clean == 'premature-closed') $status_color = 'info';
                                                elseif ($status_clean == 'rejected') $status_color = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $status_color; ?>"><?php echo ucwords(str_replace('-', ' ', $status_clean)); ?></span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Customer:</strong> <?php echo htmlspecialchars($rd['customer_name']); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Installment:</strong> ₹<?php echo number_format($rd['deposit_amount'], 2); ?> / <?php echo ucfirst($rd['repayment_cycle']); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Tenure:</strong> <?php echo $rd['tenure']; ?> Installments</li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Interest Rate:</strong> <?php echo $rd['interest_rate']; ?>% p.a.</li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Start Date:</strong> <?php echo date('d M, Y', strtotime($rd['start_date'])); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Maturity Date:</strong> <?php echo date('d M, Y', strtotime($rd['maturity_date'])); ?></li>
                                        <li class="list-group-item d-flex justify-content-between"><strong>Maturity Amount:</strong> ₹<?php echo number_format($rd['maturity_amount'], 2); ?></li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Log New Deposit</h5>
                                    <?php if ($rd['status'] == 'active'): ?>
                                        <form method="POST" action="rd-details.php?id=<?php echo $rd_id; ?>">
                                            <div class="mb-3">
                                                <label for="amount_paid" class="form-label">Amount Deposited (₹)</label>
                                                <input type="number" step="0.01" class="form-control" name="amount_paid" id="amount_paid" value="<?php echo number_format($rd['deposit_amount'], 2, '.', ''); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="notes" class="form-label">Notes (Receipt #, etc.)</label>
                                                <input type="text" class="form-control" name="notes" id="notes" placeholder="Optional notes">
                                            </div>
                                            <button type="submit" class="btn btn-primary w-100">Log Deposit</button>
                                        </form>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center" role="alert">
                                            This RD account is currently <strong><?php echo ucwords(str_replace('-', ' ', $rd['status'])); ?></strong>. New deposits cannot be logged.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-body">
                                     <h5 class="card-title">Deposit Progress</h5>
                                     <div class="progress mb-3" style="height: 25px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?php echo $progress_percentage; ?>%;" aria-valuenow="<?php echo $progress_percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo round($progress_percentage); ?>%</div>
                                     </div>
                                     <ul class="list-group list-group-flush mb-3">
                                         <li class="list-group-item d-flex justify-content-between"><strong>Installments Paid:</strong> <?php echo $installments_paid_count; ?> / <?php echo $rd['tenure']; ?></li>
                                         <li class="list-group-item d-flex justify-content-between text-success"><strong>Total Principal Deposited:</strong> ₹<?php echo number_format($total_principal_paid, 2); ?></li>
                                         <li class="list-group-item d-flex justify-content-between text-info"><strong>Total Principal Due:</strong> ₹<?php echo number_format($total_principal_due, 2); ?></li>
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
</body>
</html>