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
    }
    elseif ($customer_id_for_loan <= 0 || $loan_amount <= 0 || $tenure <= 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill in all required fields correctly.</div>";
    } else {
        $sql = "INSERT INTO loans (customer_id, agent_id, loan_amount, interest_rate, tenure, repayment_cycle, total_repayable_amount, monthly_installment, status, application_date, approval_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param("iiddssddss", $customer_id_for_loan, $agent_id, $loan_amount, $interest_rate, $tenure, $repayment_cycle, $total_repayable, $monthly_installment, $logged_time, $start_date);

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

                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="apply-loan.php<?php if($customer_id_from_url) echo '?customer_id='.$customer_id_from_url; ?>">
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
                                                <label class="form-label-title mb-2">Loan Amount (Principal) (₹)</label>
                                                <input class="form-control" type="number" step="100" id="loanAmount" name="loan_amount" placeholder="e.g., 10000" required>
                                                <div id="walletError" class="text-danger mt-1" style="display: none;"></div>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Loan Interest Rate (%)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" placeholder="e.g., 5.5" required>
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

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Total Repayable Amount (₹)</label>
                                                <input class="form-control" type="number" id="totalRepayable" name="total_repayable_amount" readonly>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Installment Amount (₹)</label>
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