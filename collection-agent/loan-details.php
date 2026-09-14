<?php
// Include the config file
include('config.php');
date_default_timezone_set('Asia/Kolkata');

$logged_time = date("Y-m-d H:i:s");

// 1. Authentication Check for Collection Agent
if (!isset($_SESSION['collection_agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = intval($_SESSION['collection_agent_id']);
$message = '';

// 2. Get Loan ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-loans.php");
    exit();
}
$loan_id = intval($_GET['id']);

// Check for a flash message from a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Verify Loan Ownership (Must belong to a customer assigned to this collection agent)
$check_owner = $conn->prepare("SELECT l.id FROM loans l JOIN customers c ON l.customer_id = c.id WHERE l.id = ? AND c.agent_id = ?");
$check_owner->bind_param("ii", $loan_id, $agent_id);
$check_owner->execute();
$res_owner = $check_owner->get_result();
if ($res_owner->num_rows === 0) {
    $check_owner->close();
    header("Location: all-loans.php");
    exit();
}
$check_owner->close();

// 4. Handle New Payment Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['amount_paid'])) {
    $amount_paid = floatval($_POST['amount_paid']);
    $payment_notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $payment_date = $logged_time;

    if ($amount_paid > 0) {
        $conn->begin_transaction();
        try {
            // Step A: Insert into payments table (status approved)
            $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
            $stmt_payment = $conn->prepare($sql_payment);
            $stmt_payment->bind_param("idsis", $loan_id, $amount_paid, $payment_date, $agent_id, $payment_notes);
            $stmt_payment->execute();
            $new_payment_id = $stmt_payment->insert_id;
            $stmt_payment->close();

            // Step B: Insert into loan_payments_collection table for collection history
            $sql_coll = "INSERT INTO loan_payments_collection (loan_id, amount_paid, collected_by_agent_id, payment_date) VALUES (?, ?, ?, ?)";
            $stmt_coll = $conn->prepare($sql_coll);
            $stmt_coll->bind_param("idss", $loan_id, $amount_paid, $agent_id, $payment_date);
            $stmt_coll->execute();
            $stmt_coll->close();

            // Step C: Log wallet transaction
            $sql_wallet = "INSERT INTO wallet_transactions (agent_id, loan_id, payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'emi-received', ?, ?)";
            $stmt_wallet = $conn->prepare($sql_wallet);
            $description = "EMI / Interest received for Loan ID: $loan_id";
            $stmt_wallet->bind_param("iiids", $agent_id, $loan_id, $new_payment_id, $amount_paid, $description);
            $stmt_wallet->execute();
            $stmt_wallet->close();

            // Step D: Update agent wallet balance
            $conn->query("UPDATE agent_wallets SET balance = balance + $amount_paid WHERE agent_id = $agent_id");

            // Step E: Check if loan is fully paid (for flat total loans)
            $total_paid_query = $conn->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments WHERE loan_id = $loan_id AND (status IS NULL OR status != 'rejected')");
            $total_paid = floatval($total_paid_query->fetch_assoc()['total']);
            
            $loan_meta_query = $conn->query("SELECT total_repayable_amount, interest_calculation_type FROM loans WHERE id = $loan_id");
            $loan_meta = $loan_meta_query->fetch_assoc();
            $total_repayable = floatval($loan_meta['total_repayable_amount']);
            $calc_type = $loan_meta['interest_calculation_type'] ?? 'flat_total';

            if ($calc_type === 'flat_total' && $total_repayable > 0 && $total_paid >= $total_repayable - 0.01) {
                $conn->query("UPDATE loans SET status = 'paid' WHERE id = $loan_id AND status NOT IN ('closed', 'paid')");
            }

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success'>Payment logged successfully! Wallet balance updated.</div>";

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error: Failed to log payment: " . htmlspecialchars($exception->getMessage()) . "</div>";
        }
        header("Location: loan-details.php?id=" . $loan_id);
        exit();
    }
}

// 5. Handle Upload / Update Gold Collateral Photo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_gold_photo') {
    if (isset($_FILES['gold_photo'])) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
        $target_dir = __DIR__ . '/uploads/gold_collateral/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
        @chmod($target_dir, 0755);

        // Fetch existing photos to append
        $stmt_curr = $conn->prepare("SELECT l.gold_photo_path FROM loans l JOIN customers c ON l.customer_id = c.id WHERE l.id = ? AND c.agent_id = ?");
        $stmt_curr->bind_param("ii", $loan_id, $agent_id);
        $stmt_curr->execute();
        $res_curr = $stmt_curr->get_result();
        $existing_str = ($res_curr->num_rows > 0) ? ($res_curr->fetch_assoc()['gold_photo_path'] ?? '') : '';
        $stmt_curr->close();

        $existing_photos = !empty($existing_str) ? array_filter(explode(',', $existing_str)) : [];

        $files_obj = $_FILES['gold_photo'];
        $file_items = [];
        if (is_array($files_obj['name'])) {
            foreach ($files_obj['name'] as $idx => $n) {
                if (!empty($n)) {
                    $file_items[] = [
                        'name' => $files_obj['name'][$idx],
                        'tmp_name' => $files_obj['tmp_name'][$idx],
                        'error' => $files_obj['error'][$idx],
                    ];
                }
            }
        } else {
            if (!empty($files_obj['name'])) {
                $file_items[] = [
                    'name' => $files_obj['name'],
                    'tmp_name' => $files_obj['tmp_name'],
                    'error' => $files_obj['error'],
                ];
            }
        }

        $new_uploaded = [];
        foreach ($file_items as $item) {
            if ($item['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_exts)) {
                    $new_filename = 'gold_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $dest = $target_dir . $new_filename;
                    if (move_uploaded_file($item['tmp_name'], $dest)) {
                        $new_uploaded[] = 'uploads/gold_collateral/' . $new_filename;
                    }
                }
            }
        }

        if (!empty($new_uploaded)) {
            $all_photos = array_merge($existing_photos, $new_uploaded);
            $combined_str = implode(',', $all_photos);

            $stmt_upd = $conn->prepare("UPDATE loans SET gold_photo_path = ? WHERE id = ?");
            $stmt_upd->bind_param("si", $combined_str, $loan_id);
            if ($stmt_upd->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>Gold collateral photo uploaded successfully!</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Failed to update database record for gold photo.</div>";
            }
            $stmt_upd->close();
        } else {
            $_SESSION['message'] = "<div class='alert alert-warning'>No valid image files uploaded. Allowed formats: JPG, PNG, WEBP, GIF, HEIC.</div>";
        }
    }
    header("Location: loan-details.php?id=" . $loan_id);
    exit();
}

// 6. Fetch Comprehensive Loan and Customer Details
$sql_loan = "SELECT l.*, c.full_name as customer_name, c.email as customer_email, c.phone as customer_phone, c.avatar as customer_avatar
             FROM loans l
             JOIN customers c ON l.customer_id = c.id
             WHERE l.id = ? AND c.agent_id = ?";
$stmt = $conn->prepare($sql_loan);
$stmt->bind_param("ii", $loan_id, $agent_id);
$stmt->execute();
$loan = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$loan) {
    header("Location: all-loans.php");
    exit();
}

// Fetch payment history
$sql_payments = "SELECT p.*, a.first_name, a.last_name 
                FROM payments p
                LEFT JOIN agents a ON p.collected_by_agent_id = a.id
                WHERE p.loan_id = ?
                ORDER BY p.payment_date DESC";
$stmt_p = $conn->prepare($sql_payments);
$stmt_p->bind_param("i", $loan_id);
$stmt_p->execute();
$payments = $stmt_p->get_result();
$stmt_p->close();

// Calculate total paid & remaining balance
$sql_total_paid = "SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments WHERE loan_id = ? AND (status IS NULL OR status != 'rejected')";
$stmt_tp = $conn->prepare($sql_total_paid);
$stmt_tp->bind_param("i", $loan_id);
$stmt_tp->execute();
$total_paid = floatval($stmt_tp->get_result()->fetch_assoc()['total']);
$stmt_tp->close();

$loan_type = $loan['loan_type'] ?? 'standard';
$calc_type = $loan['interest_calculation_type'] ?? 'flat_total';

if ($calc_type === 'monthly_interest_only') {
    $remaining_balance = 0.00; // Ongoing monthly interest loan
} else {
    $remaining_balance = max(0.0, floatval($loan['total_repayable_amount']) - $total_paid);
}

// Parse gold photo paths if gold loan
$gold_photos = [];
if (!empty($loan['gold_photo_path'])) {
    $raw_paths = explode(',', $loan['gold_photo_path']);
    foreach ($raw_paths as $p) {
        $p = trim($p);
        if (!empty($p)) {
            $gold_photos[] = $p;
        }
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
                            <?php echo $message; ?>
                            
                            <!-- Header / Title -->
                            <div class="card mb-3">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <?php $avatar_path = !empty($loan['customer_avatar']) ? 'upload/customers/avatars/' . $loan['customer_avatar'] : 'assets/images/users/default-avatar.png'; ?>
                                        <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Customer" style="width: 55px; height: 55px; border-radius: 8px; object-fit: cover;">
                                        <div>
                                            <h4 class="mb-1"><?php echo htmlspecialchars($loan['customer_name']); ?></h4>
                                            <span class="text-muted">Loan ID: #<?php echo $loan['id']; ?> | Phone: <?php echo htmlspecialchars($loan['customer_phone'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($loan_type === 'gold'): ?>
                                            <span class="badge bg-warning text-dark fs-6 px-3 py-2"><i class="ri-gold-line me-1"></i>Gold Loan</span>
                                        <?php elseif ($loan_type === 'interest_only'): ?>
                                            <span class="badge bg-primary fs-6 px-3 py-2"><i class="ri-percent-line me-1"></i>Interest Loan</span>
                                        <?php else: ?>
                                            <span class="badge bg-info fs-6 px-3 py-2"><i class="ri-file-list-line me-1"></i>Normal Loan</span>
                                        <?php endif; ?>
                                        
                                        <?php
                                            $st_clean = strtolower(trim($loan['status']));
                                            $st_badge = 'secondary';
                                            if (in_array($st_clean, ['active', 'approved', 'paid'])) $st_badge = 'success';
                                            if ($st_clean === 'pending') $st_badge = 'warning';
                                            if (in_array($st_clean, ['rejected', 'defaulted'])) $st_badge = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $st_badge; ?> fs-6 px-3 py-2 ms-2"><?php echo ucfirst($st_clean); ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Summary Cards -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-3">
                                    <div class="card card-body text-center h-100">
                                        <span class="text-muted small">Principal Loan Amount</span>
                                        <h3 class="text-dark mt-1">₹<?php echo number_format($loan['loan_amount'], 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-body text-center h-100">
                                        <span class="text-muted small">
                                            <?php echo ($calc_type === 'monthly_interest_only') ? 'Monthly Interest Installment' : 'Total Repayable Amount'; ?>
                                        </span>
                                        <h3 class="text-primary mt-1">
                                            <?php echo ($calc_type === 'monthly_interest_only') ? '₹' . number_format($loan['monthly_installment'], 2) . ' / mo' : '₹' . number_format($loan['total_repayable_amount'], 2); ?>
                                        </h3>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-body text-center h-100">
                                        <span class="text-muted small">Total Amount Paid</span>
                                        <h3 class="text-success mt-1">₹<?php echo number_format($total_paid, 2); ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card card-body text-center h-100">
                                        <span class="text-muted small">
                                            <?php echo ($calc_type === 'monthly_interest_only') ? 'Status' : 'Remaining Balance'; ?>
                                        </span>
                                        <h3 class="<?php echo ($calc_type === 'monthly_interest_only') ? 'text-info' : 'text-danger'; ?> mt-1">
                                            <?php echo ($calc_type === 'monthly_interest_only') ? 'Monthly Active' : '₹' . number_format($remaining_balance, 2); ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>

                            <!-- Loan Details & Gold Collateral Section -->
                            <div class="row g-3 mb-4">
                                <div class="<?php echo ($loan_type === 'gold') ? 'col-md-7' : 'col-md-12'; ?>">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title border-bottom pb-2">Loan Structure Details</h5>
                                            <div class="row g-3">
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Interest Rate</p>
                                                    <h6><?php echo floatval($loan['interest_rate']); ?>% per annum</h6>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Repayment Cycle</p>
                                                    <h6><?php echo ucfirst($loan['repayment_cycle'] ?? 'Monthly'); ?></h6>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Tenure</p>
                                                    <h6>
                                                        <?php 
                                                            if ($calc_type === 'monthly_interest_only') {
                                                                echo 'Monthly (Open-ended until closed)';
                                                            } else {
                                                                echo intval($loan['tenure']) . ' Payments';
                                                            }
                                                        ?>
                                                    </h6>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Installment Amount</p>
                                                    <h6>₹<?php echo number_format($loan['monthly_installment'], 2); ?></h6>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Application Date</p>
                                                    <h6><?php echo !empty($loan['application_date']) ? date('d M Y', strtotime($loan['application_date'])) : 'N/A'; ?></h6>
                                                </div>
                                                <div class="col-sm-6">
                                                    <p class="mb-1 text-muted">Approval / Start Date</p>
                                                    <h6><?php echo !empty($loan['approval_date']) ? date('d M Y', strtotime($loan['approval_date'])) : 'Pending Approval'; ?></h6>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($loan_type === 'gold'): ?>
                                    <div class="col-md-5">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h5 class="card-title border-bottom pb-2"><i class="ri-gold-line me-1 text-warning"></i>Gold Collateral Info</h5>
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <span class="text-muted small">Net Weight</span>
                                                        <h5 class="fw-bold"><?php echo floatval($loan['gold_weight_grams']); ?> Grams</h5>
                                                    </div>
                                                    <div class="col-6">
                                                        <span class="text-muted small">Gold Valuation</span>
                                                        <h5 class="fw-bold text-success">
                                                            <?php 
                                                                $g_rate = floatval($loan['gold_rate_per_gram'] ?? 0);
                                                                $g_weight = floatval($loan['gold_weight_grams'] ?? 0);
                                                                echo ($g_rate > 0 && $g_weight > 0) ? '₹' . number_format($g_weight * $g_rate, 2) : 'N/A';
                                                            ?>
                                                        </h5>
                                                    </div>
                                                </div>

                                                <!-- Existing Photos -->
                                                <h6 class="text-muted small mb-2">Collateral Photos</h6>
                                                <div class="d-flex flex-wrap gap-2 mb-3">
                                                    <?php if (!empty($gold_photos)): ?>
                                                        <?php foreach ($gold_photos as $g_photo): ?>
                                                            <a href="<?php echo htmlspecialchars($g_photo); ?>" target="_blank">
                                                                <img src="<?php echo htmlspecialchars($g_photo); ?>" style="width: 70px; height: 70px; object-fit: cover; border-radius: 6px; border: 1px solid #ccc;">
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No gold collateral photos uploaded yet.</span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Upload Photo Form -->
                                                <form method="POST" action="loan-details.php?id=<?php echo $loan['id']; ?>" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="upload_gold_photo">
                                                    <div class="mb-2">
                                                        <label class="form-label small fw-bold">Upload New Gold Collateral Photo</label>
                                                        <input type="file" name="gold_photo[]" class="form-control form-control-sm" accept="image/*" multiple required>
                                                    </div>
                                                    <button type="submit" class="btn btn-warning btn-sm w-100"><i class="ri-upload-cloud-line me-1"></i>Upload Photo</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Log Payment Form -->
                            <?php if (in_array(strtolower(trim($loan['status'])), ['active', 'approved'])): ?>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title border-bottom pb-2">Log New Loan / Interest Collection</h5>
                                        <form method="POST" action="loan-details.php?id=<?php echo $loan['id']; ?>" class="row g-3">
                                            <div class="col-md-4">
                                                <label for="amount_paid" class="form-label">Collection Amount (₹)</label>
                                                <input type="number" step="0.01" min="1" class="form-control" id="amount_paid" name="amount_paid" value="<?php echo floatval($loan['monthly_installment']); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="notes" class="form-label">Notes / Remarks</label>
                                                <input type="text" class="form-control" id="notes" name="notes" placeholder="Optional notes for this collection">
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary w-100">Submit Collection</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Payment History -->
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title border-bottom pb-2">Collection & Payment History</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Amount Paid</th>
                                                    <th>Collected By Agent</th>
                                                    <th>Notes</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($payments && $payments->num_rows > 0): ?>
                                                    <?php while ($p = $payments->fetch_assoc()): ?>
                                                        <tr>
                                                            <td><?php echo date('d M Y, h:i A', strtotime($p['payment_date'])); ?></td>
                                                            <td><strong class="text-success">₹<?php echo number_format($p['amount_paid'], 2); ?></strong></td>
                                                            <td>
                                                                <?php 
                                                                    $ag_name = trim(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? ''));
                                                                    echo !empty($ag_name) ? htmlspecialchars($ag_name) : 'Self / Online';
                                                                ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($p['notes'] ?? '-'); ?></td>
                                                            <td>
                                                                <?php 
                                                                    $p_st = strtolower(trim($p['status'] ?? 'approved'));
                                                                    $p_color = ($p_st === 'approved') ? 'success' : (($p_st === 'rejected') ? 'danger' : 'warning');
                                                                ?>
                                                                <span class="badge bg-<?php echo $p_color; ?>"><?php echo ucfirst($p_st); ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center text-muted">No collections recorded for this loan yet.</td>
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
<?php $conn->close(); ?>
