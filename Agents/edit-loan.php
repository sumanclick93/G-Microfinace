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

// --- Fetch Agent's Current Wallet Balance ---
$agent_wallet_balance = 0;
$wallet_stmt = $conn->prepare("SELECT balance FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->bind_param("i", $agent_id);
$wallet_stmt->execute();
$wallet_result = $wallet_stmt->get_result();
if ($wallet_result->num_rows > 0) {
    $agent_wallet_balance = $wallet_result->fetch_assoc()['balance'];
}
$wallet_stmt->close();

// 2. Validate Loan ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "<div class='alert alert-danger'>Invalid Loan application ID.</div>";
    header("Location: all-loans.php");
    exit();
}
$loan_id = (int)$_GET['id'];

// 3. Fetch current Loan details to verify existence, ownership, and pending status
$stmt_fetch = $conn->prepare("
    SELECT l.*, c.full_name as customer_name 
    FROM loans l 
    JOIN customers c ON l.customer_id = c.id 
    WHERE l.id = ? AND l.agent_id = ?
");
$stmt_fetch->bind_param("ii", $loan_id, $agent_id);
$stmt_fetch->execute();
$loan_result = $stmt_fetch->get_result();

if ($loan_result->num_rows === 0) {
    $_SESSION['message'] = "<div class='alert alert-danger'>Loan application not found or access denied.</div>";
    header("Location: all-loans.php");
    exit();
}

$loan_data = $loan_result->fetch_assoc();
$stmt_fetch->close();

if ($loan_data['status'] !== 'pending') {
    $_SESSION['message'] = "<div class='alert alert-warning'>Only pending loan applications can be edited.</div>";
    header("Location: all-loans.php");
    exit();
}

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan_amount = (float)$_POST['loan_amount'];
    $interest_rate = (float)$_POST['interest_rate'];
    $tenure = (int)$_POST['tenure'];
    $repayment_cycle = $_POST['repayment_cycle'];
    $total_repayable = (float)$_POST['total_repayable_amount'];
    $monthly_installment = (float)$_POST['monthly_installment'];
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d');

    // Server-Side Wallet Balance Validation
    if ($loan_amount > $agent_wallet_balance) {
        $message = "<div class='alert alert-danger'>Loan amount cannot exceed your wallet balance of ₹" . number_format($agent_wallet_balance, 2) . ".</div>";
    } elseif ($loan_amount <= 0 || $tenure <= 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill in all required fields correctly.</div>";
    } else {
        // Calculate server-side to prevent tampering/rounding errors
        $total_repayable = $loan_amount * (1 + ($interest_rate / 100));
        $monthly_installment = $total_repayable / $tenure;

        $stmt_update = $conn->prepare("
            UPDATE loans 
            SET loan_amount = ?, interest_rate = ?, tenure = ?, repayment_cycle = ?, total_repayable_amount = ?, monthly_installment = ?, approval_date = ? 
            WHERE id = ? AND agent_id = ? AND status = 'pending'
        ");
        $stmt_update->bind_param("ddisddsii", $loan_amount, $interest_rate, $tenure, $repayment_cycle, $total_repayable, $monthly_installment, $start_date, $loan_id, $agent_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Loan application updated successfully.</div>";
            header("Location: all-loans.php");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error updating application: " . $stmt_update->error . "</div>";
        }
        $stmt_update->close();
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
                                        <h5>Edit Loan Application: #<?php echo $loan_id; ?></h5>
                                        <div class="text-end">
                                            <small class="text-muted d-block">Available Balance</small>
                                            <h6 class="text-success mb-0">₹<?php echo number_format($agent_wallet_balance, 2); ?></h6>
                                        </div>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>

                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="edit-loan.php?id=<?php echo $loan_id; ?>">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Customer</label>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($loan_data['customer_name']); ?>" readonly>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Amount (Principal) (₹)</label>
                                                <input class="form-control" type="number" step="100" id="loanAmount" name="loan_amount" value="<?php echo htmlspecialchars($loan_data['loan_amount']); ?>" required>
                                                <div id="walletError" class="text-danger mt-1" style="display: none;"></div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Interest Rate (%)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" value="<?php echo htmlspecialchars($loan_data['interest_rate']); ?>" required>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Start Date</label>
                                                <input class="form-control" type="date" name="start_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($loan_data['approval_date']))); ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Repayment Cycle</label>
                                                <select class="form-select" id="repaymentCycle" name="repayment_cycle" required>
                                                    <option value="daily" <?php echo ($loan_data['repayment_cycle'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                    <option value="weekly" <?php echo ($loan_data['repayment_cycle'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                    <option value="monthly" <?php echo ($loan_data['repayment_cycle'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                    <option value="quarterly" <?php echo ($loan_data['repayment_cycle'] === 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                                    <option value="half-yearly" <?php echo ($loan_data['repayment_cycle'] === 'half-yearly') ? 'selected' : ''; ?>>Half-Yearly</option>
                                                    <option value="annually" <?php echo ($loan_data['repayment_cycle'] === 'annually') ? 'selected' : ''; ?>>Annually</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Loan Tenure</label>
                                                <input class="form-control" type="number" id="tenure" name="tenure" value="<?php echo htmlspecialchars($loan_data['tenure']); ?>" required>
                                                <small class="form-text text-muted">Enter number of payments (e.g., for 12 months, enter 12).</small>
                                            </div>

                                            <hr>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Total Repayable Amount (₹)</label>
                                                <input class="form-control" type="number" id="totalRepayable" name="total_repayable_amount" value="<?php echo htmlspecialchars($loan_data['total_repayable_amount']); ?>" readonly>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Installment Amount (₹)</label>
                                                <input class="form-control" type="number" id="installmentAmount" name="monthly_installment" value="<?php echo htmlspecialchars($loan_data['monthly_installment']); ?>" readonly>
                                            </div>

                                            <div class="mt-3 d-flex gap-2">
                                                <button type="submit" id="submitButton" class="btn btn-primary w-100">Save Changes</button>
                                                <a href="all-loans.php" class="btn btn-secondary w-100 text-center">Cancel</a>
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
            const loanAmountInput = document.getElementById('loanAmount');
            const interestRateInput = document.getElementById('interestRate');
            const tenureInput = document.getElementById('tenure');
            const totalRepayableInput = document.getElementById('totalRepayable');
            const installmentAmountInput = document.getElementById('installmentAmount');
            const walletBalance = <?php echo $agent_wallet_balance; ?>;
            const walletErrorDiv = document.getElementById('walletError');
            const submitButton = document.getElementById('submitButton');

            function performCalculations() {
                const principal = parseFloat(loanAmountInput.value);
                const interest = parseFloat(interestRateInput.value);
                const tenure = parseInt(tenureInput.value);

                if (!isNaN(principal) && principal > walletBalance) {
                    walletErrorDiv.textContent = 'Loan amount exceeds your available wallet balance.';
                    walletErrorDiv.style.display = 'block';
                    submitButton.disabled = true;
                } else {
                    walletErrorDiv.style.display = 'none';
                    submitButton.disabled = false;
                }

                if (!isNaN(principal) && principal > 0 && !isNaN(interest) && interest >= 0 && !isNaN(tenure) && tenure > 0) {
                    const totalRepayable = principal * (1 + (interest / 100));
                    const installment = totalRepayable / tenure;

                    totalRepayableInput.value = totalRepayable.toFixed(2);
                    installmentAmountInput.value = installment.toFixed(2);
                } else {
                    totalRepayableInput.value = '';
                    installmentAmountInput.value = '';
                }
            }

            loanAmountInput.addEventListener('input', performCalculations);
            interestRateInput.addEventListener('input', performCalculations);
            tenureInput.addEventListener('input', performCalculations);
        });
    </script>
</body>
</html>
