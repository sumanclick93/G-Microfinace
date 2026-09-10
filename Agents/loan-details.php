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
            // Step A: Insert into the payments table as approved by default for agent collections
            $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
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

// --- Handle Upload / Update Gold Collateral Photo ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_gold_photo') {
    if (isset($_FILES['gold_photo'])) {
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
        $target_dir = __DIR__ . '/upload/gold_collateral/';
        if (!is_dir($target_dir)) @mkdir($target_dir, 0755, true);
        @chmod($target_dir, 0755);
        $target_dir_alt = __DIR__ . '/uploads/gold_collateral/';
        if (!is_dir($target_dir_alt)) @mkdir($target_dir_alt, 0755, true);
        @chmod($target_dir_alt, 0755);

        // Fetch existing photos to append
        $stmt_curr = $conn->prepare("SELECT gold_photo_path FROM loans WHERE id = ? AND agent_id = ?");
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
        $upload_errors = [];

        $candidate_dirs = [
            __DIR__ . '/upload/gold_collateral/',
            __DIR__ . '/uploads/gold_collateral/',
            __DIR__ . '/upload/',
            __DIR__ . '/uploads/'
        ];

        foreach ($file_items as $item) {
            if ($item['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed_exts)) {
                    $new_filename = 'gold_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $saved_rel = null;
                    $saved_abs = null;

                    foreach ($candidate_dirs as $cdir) {
                        if (!is_dir($cdir)) @mkdir($cdir, 0755, true);
                        @chmod($cdir, 0755);
                        $dest = $cdir . $new_filename;
                        if (move_uploaded_file($item['tmp_name'], $dest)) {
                            $saved_abs = $dest;
                            if (strpos($cdir, 'upload/gold_collateral') !== false) {
                                $saved_rel = 'upload/gold_collateral/' . $new_filename;
                            } elseif (strpos($cdir, 'uploads/gold_collateral') !== false) {
                                $saved_rel = 'uploads/gold_collateral/' . $new_filename;
                            } elseif (strpos($cdir, 'upload/') !== false) {
                                $saved_rel = 'upload/' . $new_filename;
                            } else {
                                $saved_rel = 'uploads/' . $new_filename;
                            }
                            break;
                        }
                    }

                    if ($saved_abs && $saved_rel) {
                        foreach ($candidate_dirs as $alt_cdir) {
                            if (!is_dir($alt_cdir)) @mkdir($alt_cdir, 0755, true);
                            @chmod($alt_cdir, 0755);
                            $alt_dest = $alt_cdir . $new_filename;
                            if (!file_exists($alt_dest)) @copy($saved_abs, $alt_dest);
                        }
                        $new_uploaded[] = $saved_rel;
                    } else {
                        $upload_errors[] = htmlspecialchars($item['name']) . ': Server folder permission issue.';
                    }
                } else {
                    $upload_errors[] = htmlspecialchars($item['name']) . ": Invalid image format (.$ext).";
                }
            } else {
                $err_code = $item['error'];
                $err_desc = ($err_code == 1 || $err_code == 2) ? 'Exceeds max file size limit' : "Error code $err_code";
                $upload_errors[] = htmlspecialchars($item['name']) . ": $err_desc.";
            }
        }

        if (!empty($new_uploaded)) {
            $all_photos = array_merge($existing_photos, $new_uploaded);
            $final_path_str = implode(',', array_unique(array_filter($all_photos)));

            $stmt_up = $conn->prepare("UPDATE loans SET gold_photo_path = ? WHERE id = ? AND agent_id = ?");
            $stmt_up->bind_param("sii", $final_path_str, $loan_id, $agent_id);
            $stmt_up->execute();
            $stmt_up->close();

            $_SESSION['message'] = "<div class='alert alert-success'>" . count($new_uploaded) . " gold collateral photo(s) uploaded successfully!</div>";
        } else {
            $err_txt = !empty($upload_errors) ? implode('<br>', $upload_errors) : 'No valid file selected or file size exceeded server limit.';
            $_SESSION['message'] = "<div class='alert alert-danger'>Photo upload failed:<br>" . $err_txt . "</div>";
        }
    }
    header("Location: loan-details.php?id=" . $loan_id);
    exit();
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
                                         <li class="list-group-item d-flex justify-content-between align-items-center">
                                             <strong>Loan Category:</strong>
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
                                         <li class="list-group-item d-flex justify-content-between"><strong>Customer:</strong> <?php echo htmlspecialchars($loan['full_name']); ?></li>
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
                                     <ul class="list-group list-group-flush mb-3">
                                         <li class="list-group-item d-flex justify-content-between"><strong>Gold Weight:</strong> <span><?php echo floatval($loan['gold_weight_grams']); ?> Grams</span></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Applied Gold Rate:</strong> <span>₹<?php echo number_format($loan['gold_rate_per_gram'], 2); ?>/g</span></li>
                                         <li class="list-group-item d-flex justify-content-between"><strong>Gold Valuation:</strong> <strong class="text-success">₹<?php echo number_format(floatval($loan['gold_weight_grams']) * floatval($loan['gold_rate_per_gram']), 2); ?></strong></li>
                                     </ul>

                                     <?php
                                         $raw_photos = !empty($loan['gold_photo_path']) ? array_filter(explode(',', $loan['gold_photo_path'])) : [];
                                         $resolved_photos = [];
                                         foreach ($raw_photos as $p_item) {
                                             $p_item = trim($p_item);
                                             if (empty($p_item)) continue;
                                             if (strpos($p_item, 'Agents/') === 0) $p_item = substr($p_item, 7);
                                             if (!file_exists(__DIR__ . '/' . $p_item)) {
                                                 if (strpos($p_item, 'upload/') === 0) {
                                                     $alt_p = 'uploads/' . substr($p_item, 7);
                                                     if (file_exists(__DIR__ . '/' . $alt_p)) $p_item = $alt_p;
                                                 } elseif (strpos($p_item, 'uploads/') === 0) {
                                                     $alt_p = 'upload/' . substr($p_item, 8);
                                                     if (file_exists(__DIR__ . '/' . $alt_p)) $p_item = $alt_p;
                                                 }
                                             }
                                             $resolved_photos[] = $p_item;
                                         }
                                     ?>

                                     <h6 class="font-weight-bold text-dark mb-2">Gold Collateral Photos (<?php echo count($resolved_photos); ?>)</h6>
                                     <?php if (!empty($resolved_photos)): ?>
                                         <div class="d-flex flex-wrap gap-2 mb-3">
                                             <?php foreach ($resolved_photos as $idx => $p_url): ?>
                                                 <div class="text-center p-1 border rounded bg-white" style="width: 110px;">
                                                     <a href="<?php echo htmlspecialchars($p_url); ?>" target="_blank">
                                                         <img src="<?php echo htmlspecialchars($p_url); ?>" alt="Photo <?php echo $idx+1; ?>" class="img-thumbnail" style="height: 80px; object-fit: cover; width: 100%;">
                                                     </a>
                                                     <small class="d-block text-muted mt-1">Photo <?php echo $idx + 1; ?></small>
                                                 </div>
                                             <?php endforeach; ?>
                                         </div>
                                     <?php else: ?>
                                         <p class="text-muted small mb-3">No photo uploaded yet.</p>
                                     <?php endif; ?>

                                     <div class="p-3 bg-light rounded border">
                                         <label class="form-label font-weight-bold text-dark mb-2"><?php echo empty($resolved_photos) ? 'Upload Gold Collateral Photo(s)' : 'Add More Gold Collateral Photo(s)'; ?></label>
                                         <form method="POST" enctype="multipart/form-data" action="loan-details.php?id=<?php echo $loan_id; ?>">
                                             <input type="hidden" name="action" value="upload_gold_photo">
                                             <div class="input-group">
                                                 <input type="file" name="gold_photo[]" multiple accept="image/*" class="form-control form-control-sm" required>
                                                 <button type="submit" class="btn btn-sm btn-warning"><i class="ri-upload-2-line me-1"></i> Upload</button>
                                             </div>
                                             <small class="form-text text-muted">You can select single or multiple photos.</small>
                                         </form>
                                     </div>
                                 </div>
                             </div>
                             <?php endif; ?>
                            
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