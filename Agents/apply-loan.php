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

// Fetch system settings
$gold_rate_per_gram = (float)get_system_setting($conn, 'gold_rate_per_gram', '5500.00');
$processing_fee_percent = (float)get_system_setting($conn, 'gold_loan_processing_fee_percent', '1.50');

// --- Fetch Agent's Current Wallet Balance ---
$agent_wallet_balance = 0;
$wallet_stmt = $conn->prepare("SELECT balance FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->bind_param("i", $agent_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
if ($wallet_result->num_rows > 0) {
    $agent_wallet_balance = (float)$wallet_result->fetch_assoc()['balance'];
}
$wallet_stmt->close();


// 2. CONDITIONAL LOGIC for customer selection
$customer_id_from_url = null;
$customer_name_display = '';
$customer_list_for_dropdown = [];

if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customer_id_from_url = $_GET['customer_id'];
    $stmt = $conn->prepare("SELECT full_name FROM customers WHERE id = ? AND agent_id = ?");
    $stmt->bind_param("ii", $customer_id_from_url, $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $customer_name_display = $result->fetch_assoc()['full_name'];
    } else {
        header("Location: all-customer.php"); exit();
    }
} else {
    $stmt = $conn->prepare("SELECT id, full_name FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
    $stmt->bind_param("i", $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $customer_list_for_dropdown[] = $row;
        }
    }
}

// 3. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id_for_loan = (int)$_POST['customer_id'];
    $loan_type = $_POST['loan_type'] ?? 'standard';
    $loan_amount = (float)$_POST['loan_amount'];
    $interest_rate = (float)$_POST['interest_rate'];
    $tenure = (int)$_POST['tenure'];
    $repayment_cycle = $_POST['repayment_cycle'] ?? 'monthly';
    $total_repayable = (float)$_POST['total_repayable_amount'];
    $monthly_installment = (float)$_POST['monthly_installment'];
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');

    $interest_calculation_type = ($loan_type === 'interest_only') ? 'monthly_interest_only' : 'flat_total';
    
    $gold_weight_grams = null;
    $gold_photo_path = null;
    $gold_rate_applied = null;
    $processing_fee = 0.00;

    if ($loan_type === 'gold') {
        $gold_weight_grams = isset($_POST['gold_weight_grams']) ? (float)$_POST['gold_weight_grams'] : 0;
        $gold_rate_applied = $gold_rate_per_gram;
        $processing_fee = round(($loan_amount * $processing_fee_percent) / 100, 2);

        // Extract any submitted files across all field names
        $file_items = [];
        foreach (['gold_photo', 'gold_photo_path', 'gold_photo_file'] as $f_key) {
            if (isset($_FILES[$f_key])) {
                $f_obj = $_FILES[$f_key];
                if (is_array($f_obj['name'])) {
                    foreach ($f_obj['name'] as $idx => $fname) {
                        if (!empty($fname)) {
                            $file_items[] = [
                                'name' => $f_obj['name'][$idx],
                                'tmp_name' => $f_obj['tmp_name'][$idx],
                                'error' => $f_obj['error'][$idx],
                            ];
                        }
                    }
                } elseif (!empty($f_obj['name'])) {
                    $file_items[] = [
                        'name' => $f_obj['name'],
                        'tmp_name' => $f_obj['tmp_name'],
                        'error' => $f_obj['error'],
                    ];
                }
            }
        }

        $uploaded_photos = [];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'];
        
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
                        if (!is_dir($cdir)) {
                            @mkdir($cdir, 0755, true);
                        }
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
                        // Dual save copy
                        foreach ($candidate_dirs as $alt_cdir) {
                            if (!is_dir($alt_cdir)) @mkdir($alt_cdir, 0755, true);
                            @chmod($alt_cdir, 0755);
                            $alt_dest = $alt_cdir . $new_filename;
                            if (!file_exists($alt_dest)) {
                                @copy($saved_abs, $alt_dest);
                            }
                        }
                        $uploaded_photos[] = $saved_rel;
                    } else {
                        $message .= "<div class='alert alert-danger'>Failed to save uploaded file " . htmlspecialchars($item['name']) . ". Directory permissions issue on server.</div>";
                    }
                } else {
                    $message .= "<div class='alert alert-danger'>Invalid image format for " . htmlspecialchars($item['name']) . " (.$ext).</div>";
                }
            } elseif ($item['error'] !== UPLOAD_ERR_NO_FILE) {
                $err_code = $item['error'];
                $err_desc = ($err_code == 1 || $err_code == 2) ? 'File exceeds maximum upload size limit' : "Upload error code $err_code";
                $message .= "<div class='alert alert-danger'>Upload failed for " . htmlspecialchars($item['name']) . " ($err_desc).</div>";
            }
        }

        if (!empty($uploaded_photos)) {
            $gold_photo_path = implode(',', $uploaded_photos);
        }
    }

    // Server-Side Wallet Balance Validation
    if ($loan_amount > $agent_wallet_balance) {
        $message = "<div class='alert alert-danger'>Loan amount cannot exceed your wallet balance of ₹" . number_format($agent_wallet_balance, 2) . ".</div>";
    } elseif ($customer_id_for_loan <= 0 || $loan_amount <= 0 || $tenure <= 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill in all required fields correctly.</div>";
    } elseif ($loan_type === 'gold' && $gold_weight_grams <= 0) {
        $message = "<div class='alert alert-danger'>Please enter a valid Gold Weight (in Grams) for Gold Loan.</div>";
    } else {
        $sql = "INSERT INTO loans (
                    customer_id, agent_id, loan_type, interest_calculation_type, 
                    loan_amount, interest_rate, tenure, repayment_cycle, 
                    total_repayable_amount, monthly_installment, gold_weight_grams, 
                    gold_photo_path, gold_rate_per_gram, processing_fee, 
                    status, application_date, approval_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
        
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param(
            "iissddissddsddss",
            $customer_id_for_loan, $agent_id, $loan_type, $interest_calculation_type,
            $loan_amount, $interest_rate, $tenure, $repayment_cycle,
            $total_repayable, $monthly_installment, $gold_weight_grams,
            $gold_photo_path, $gold_rate_applied, $processing_fee,
            $logged_time, $start_date
        );

        if ($stmt_insert->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Loan application submitted successfully.</div>";
            header("Location: customer-loans.php?id=" . $customer_id_for_loan);
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error submitting application: " . $stmt_insert->error . "</div>";
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
                        <div class="col-sm-10 m-auto">
                            <div class="card">
                                <div class="card-body">
                                    <div class="title-header option-title d-flex justify-content-between align-items-center">
                                        <h5>New Loan Application</h5>
                                        <div class="text-end">
                                            <small class="text-muted d-block">Available Balance</small>
                                            <h6 class="text-success mb-0">₹<?php echo number_format($agent_wallet_balance, 2); ?></h6>
                                        </div>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>

                                    <form class="theme-form theme-form-2 mega-form" method="POST" enctype="multipart/form-data" action="apply-loan.php<?php if($customer_id_from_url) echo '?customer_id='.$customer_id_from_url; ?>">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Customer</label>
                                                <?php if ($customer_id_from_url): ?>
                                                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($customer_name_display); ?>" readonly>
                                                    <input type="hidden" name="customer_id" value="<?php echo $customer_id_from_url; ?>">
                                                <?php else: ?>
                                                    <select class="form-select" name="customer_id" required>
                                                        <option value="">-- Select a Customer --</option>
                                                        <?php foreach ($customer_list_for_dropdown as $customer): ?>
                                                            <option value="<?php echo $customer['id']; ?>"><?php echo htmlspecialchars($customer['full_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Category / Type</label>
                                                <select class="form-select" id="loanTypeSelect" name="loan_type" required>
                                                    <option value="standard" selected>Standard Loan (Principal + Flat Interest)</option>
                                                    <option value="interest_only">Interest Loan (Monthly Interest Only, Principal at End)</option>
                                                    <option value="gold">Gold Loan (Collateral Backed)</option>
                                                </select>
                                            </div>

                                            <!-- Gold Loan Specific Section -->
                                            <div id="goldLoanSection" class="p-3 mb-4 border border-warning rounded bg-light-warning" style="display: none;">
                                                <h6 class="text-warning mb-3"><i class="ri-gold-line me-1"></i> Gold Collateral Information</h6>
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label-title mb-2">Gold Weight (Grams)</label>
                                                        <input class="form-control" type="number" step="0.001" id="goldWeight" name="gold_weight_grams" placeholder="e.g., 10.5">
                                                        <small class="form-text text-muted">Admin Gold Rate: ₹<?php echo number_format($gold_rate_per_gram, 2); ?>/g</small>
                                                    </div>
                                                    <div class="col-md-6 mb-3">
                                                        <label class="form-label-title mb-2">Gold Valuation (₹)</label>
                                                        <input class="form-control" type="text" id="goldValuation" readonly placeholder="Calculated valuation">
                                                    </div>
                                                    <div class="col-md-12 mb-3">
                                                        <label class="form-label-title mb-2">Gold Item Photo(s)</label>
                                                        <input class="form-control" type="file" id="goldPhoto" name="gold_photo[]" multiple accept="image/*">
                                                        <small class="form-text text-muted">You can select single or multiple photos of gold collateral items.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Amount (Principal) (₹)</label>
                                                <input class="form-control" type="number" step="100" id="loanAmount" name="loan_amount" placeholder="e.g., 10000" required>
                                                <div id="walletError" class="text-danger mt-1" style="display: none;"></div>
                                                <div id="goldValuationError" class="text-danger mt-1" style="display: none;"></div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Interest Rate (%)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" placeholder="e.g., 5.5" required>
                                                <small id="interestHelpText" class="form-text text-muted"></small>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Start Date</label>
                                                <input class="form-control" type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Repayment Cycle</label>
                                                <select class="form-select" id="repaymentCycle" name="repayment_cycle" required>
                                                    <option value="daily">Daily</option>
                                                    <option value="weekly">Weekly</option>
                                                    <option value="monthly" selected>Monthly</option>
                                                    <option value="quarterly">Quarterly</option>
                                                    <option value="half-yearly">Half-Yearly</option>
                                                    <option value="annually">Annually</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Loan Tenure</label>
                                                <input class="form-control" type="number" id="tenure" name="tenure" placeholder="e.g., 12" required>
                                                <small class="form-text text-muted">Enter number of payments (e.g., for 12 months, enter 12).</small>
                                            </div>

                                            <hr>

                                            <div class="col-md-4 mb-4" id="processingFeeCol" style="display: none;">
                                                <label class="form-label-title mb-2">Processing Fee (<?php echo $processing_fee_percent; ?>%) (₹)</label>
                                                <input class="form-control" type="number" id="processingFee" readonly>
                                            </div>

                                            <div class="col-md-4 mb-4">
                                                <label class="form-label-title mb-2">Total Repayable Amount (₹)</label>
                                                <input class="form-control" type="number" id="totalRepayable" name="total_repayable_amount" readonly>
                                            </div>

                                            <div class="col-md-4 mb-4">
                                                <label class="form-label-title mb-2" id="installmentLabel">Installment Amount (₹)</label>
                                                <input class="form-control" type="number" id="installmentAmount" name="monthly_installment" readonly>
                                            </div>

                                            <div class="mt-3">
                                                <button type="submit" id="submitButton" class="btn btn-primary w-100">Submit Application</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loanTypeSelect = document.getElementById('loanTypeSelect');
            const goldLoanSection = document.getElementById('goldLoanSection');
            const goldWeightInput = document.getElementById('goldWeight');
            const goldValuationInput = document.getElementById('goldValuation');
            const goldPhotoInput = document.getElementById('goldPhoto');
            
            const loanAmountInput = document.getElementById('loanAmount');
            const interestRateInput = document.getElementById('interestRate');
            const tenureInput = document.getElementById('tenure');
            const totalRepayableInput = document.getElementById('totalRepayable');
            const installmentAmountInput = document.getElementById('installmentAmount');
            const installmentLabel = document.getElementById('installmentLabel');
            const interestHelpText = document.getElementById('interestHelpText');
            
            const processingFeeCol = document.getElementById('processingFeeCol');
            const processingFeeInput = document.getElementById('processingFee');

            const walletBalance = <?php echo $agent_wallet_balance; ?>;
            const adminGoldRate = <?php echo $gold_rate_per_gram; ?>;
            const feePercent = <?php echo $processing_fee_percent; ?>;
            
            const walletErrorDiv = document.getElementById('walletError');
            const goldValuationErrorDiv = document.getElementById('goldValuationError');
            const submitButton = document.getElementById('submitButton');

            function toggleLoanTypeView() {
                const selectedType = loanTypeSelect.value;
                if (selectedType === 'gold') {
                    goldLoanSection.style.display = 'block';
                    processingFeeCol.style.display = 'block';
                    goldPhotoInput.required = true;
                    goldWeightInput.required = true;
                    installmentLabel.textContent = 'Installment Amount (₹)';
                    interestHelpText.textContent = 'Gold loan interest calculated on flat/amortized basis.';
                } else if (selectedType === 'interest_only') {
                    goldLoanSection.style.display = 'none';
                    processingFeeCol.style.display = 'none';
                    goldPhotoInput.required = false;
                    goldWeightInput.required = false;
                    installmentLabel.textContent = 'Monthly Interest Payment (₹)';
                    interestHelpText.textContent = 'Interest Loan: Customer pays monthly interest only. Principal is repayable at tenure end.';
                } else {
                    goldLoanSection.style.display = 'none';
                    processingFeeCol.style.display = 'none';
                    goldPhotoInput.required = false;
                    goldWeightInput.required = false;
                    installmentLabel.textContent = 'Installment Amount (₹)';
                    interestHelpText.textContent = 'Standard loan amortization with flat total interest.';
                }
                performCalculations();
            }

            function performCalculations() {
                const selectedType = loanTypeSelect.value;
                const principal = parseFloat(loanAmountInput.value);
                const interest = parseFloat(interestRateInput.value);
                const tenure = parseInt(tenureInput.value);
                const weight = parseFloat(goldWeightInput.value);

                // 1. Gold Valuation
                let valuation = 0;
                if (selectedType === 'gold' && !isNaN(weight) && weight > 0) {
                    valuation = weight * adminGoldRate;
                    goldValuationInput.value = '₹' + valuation.toFixed(2);
                } else {
                    goldValuationInput.value = '';
                }

                // 2. Validation Checks
                let hasError = false;

                if (!isNaN(principal) && principal > walletBalance) {
                    walletErrorDiv.textContent = 'Loan amount exceeds your available wallet balance (₹' + walletBalance.toFixed(2) + ').';
                    walletErrorDiv.style.display = 'block';
                    hasError = true;
                } else {
                    walletErrorDiv.style.display = 'none';
                }

                if (selectedType === 'gold' && !isNaN(principal) && valuation > 0 && principal > valuation) {
                    goldValuationErrorDiv.textContent = 'Loan amount cannot exceed gold valuation of ₹' + valuation.toFixed(2) + '.';
                    goldValuationErrorDiv.style.display = 'block';
                    hasError = true;
                } else {
                    goldValuationErrorDiv.style.display = 'none';
                }

                submitButton.disabled = hasError;

                // 3. Financial Calculations
                if (!isNaN(principal) && principal > 0 && !isNaN(interest) && interest >= 0 && !isNaN(tenure) && tenure > 0) {
                    if (selectedType === 'interest_only') {
                        // Monthly interest payment calculation
                        const monthlyInterest = principal * (interest / 100);
                        const totalRepayable = principal + (monthlyInterest * tenure);
                        
                        installmentAmountInput.value = monthlyInterest.toFixed(2);
                        totalRepayableInput.value = totalRepayable.toFixed(2);
                    } else {
                        // Standard / Gold Loan Flat Total calculation
                        const totalRepayable = principal * (1 + (interest / 100));
                        const installment = totalRepayable / tenure;
                        
                        totalRepayableInput.value = totalRepayable.toFixed(2);
                        installmentAmountInput.value = installment.toFixed(2);

                        if (selectedType === 'gold') {
                            const fee = (principal * feePercent) / 100;
                            processingFeeInput.value = fee.toFixed(2);
                        }
                    }
                } else {
                    totalRepayableInput.value = '';
                    installmentAmountInput.value = '';
                    processingFeeInput.value = '';
                }
            }

            loanTypeSelect.addEventListener('change', toggleLoanTypeView);
            goldWeightInput.addEventListener('input', performCalculations);
            loanAmountInput.addEventListener('input', performCalculations);
            interestRateInput.addEventListener('input', performCalculations);
            tenureInput.addEventListener('input', performCalculations);

            toggleLoanTypeView();
        });
    </script>
</body>
</html>