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

// 2. Validate RD ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "<div class='alert alert-danger'>Invalid RD application ID.</div>";
    header("Location: all-rds.php");
    exit();
}
$rd_id = (int)$_GET['id'];

// 3. Fetch current RD details to verify existence, ownership, and pending status
$stmt_fetch = $conn->prepare("
    SELECT rd.*, c.full_name as customer_name 
    FROM recurring_deposits rd 
    JOIN customers c ON rd.customer_id = c.id 
    WHERE rd.id = ? AND rd.agent_id = ?
");
$stmt_fetch->bind_param("ii", $rd_id, $agent_id);
$stmt_fetch->execute();
$rd_result = $stmt_fetch->get_result();

if ($rd_result->num_rows === 0) {
    $_SESSION['message'] = "<div class='alert alert-danger'>RD application not found or access denied.</div>";
    header("Location: all-rds.php");
    exit();
}

$rd_data = $rd_result->fetch_assoc();
$stmt_fetch->close();

if ($rd_data['status'] !== 'pending') {
    $_SESSION['message'] = "<div class='alert alert-warning'>Only pending RD applications can be edited.</div>";
    header("Location: all-rds.php");
    exit();
}

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $deposit_amount = (float)$_POST['deposit_amount'];
    $repayment_cycle = $_POST['repayment_cycle'];
    $tenure = (int)$_POST['tenure'];
    $interest_rate = (float)$_POST['interest_rate'];
    $start_date = $_POST['start_date'];

    if ($deposit_amount <= 0 || $tenure <= 0 || $interest_rate < 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill all fields correctly.</div>";
    } else {
        // --- Calculate Maturity Amount (Simple Interest) ---
        $total_principal = $deposit_amount * $tenure;

        switch($repayment_cycle) {
            case 'daily': $cycles_per_year = 365; break;
            case 'weekly': $cycles_per_year = 52; break;
            case 'monthly': $cycles_per_year = 12; break;
            case 'quarterly': $cycles_per_year = 4; break;
            case 'half-yearly': $cycles_per_year = 2; break;
            case 'annually': $cycles_per_year = 1; break;
            default: $cycles_per_year = 12;
        }
        $time_in_years = $tenure / $cycles_per_year;

        // Simple Interest = P * R * T
        $total_interest = $total_principal * ($interest_rate / 100) * $time_in_years;
        $maturity_amount = $total_principal + $total_interest;

        // --- Calculate Maturity Date ---
        $start_date_obj = new DateTime($start_date);
        
        $interval_string = '';
        switch ($repayment_cycle) {
            case 'daily': 
                $interval_string = "P{$tenure}D"; 
                break; 
            case 'weekly': 
                $interval_string = "P{$tenure}W"; 
                break;
            case 'monthly': 
                $interval_string = "P{$tenure}M"; 
                break;
            case 'quarterly': 
                $interval_string = "P" . ($tenure * 3) . "M"; 
                break;
            case 'half-yearly': 
                $interval_string = "P" . ($tenure * 6) . "M"; 
                break;
            case 'annually': 
                $interval_string = "P{$tenure}Y"; 
                break;
            default: 
                $interval_string = "P{$tenure}M"; 
        }

        try {
             $maturity_date_obj = $start_date_obj->add(new DateInterval($interval_string));
             $maturity_date = $maturity_date_obj->format('Y-m-d');
        } catch (Exception $e) {
             $message = "<div class='alert alert-danger'>Error calculating maturity date. Check tenure and cycle.</div>";
             $maturity_date = null; 
        }

        if ($maturity_date) { 
            $stmt_update = $conn->prepare("
                UPDATE recurring_deposits 
                SET deposit_amount = ?, repayment_cycle = ?, tenure = ?, interest_rate = ?, maturity_amount = ?, start_date = ?, maturity_date = ? 
                WHERE id = ? AND agent_id = ? AND status = 'pending'
            ");
            $stmt_update->bind_param("dsiddssii", $deposit_amount, $repayment_cycle, $tenure, $interest_rate, $maturity_amount, $start_date, $maturity_date, $rd_id, $agent_id);

            if ($stmt_update->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>RD application updated successfully.</div>";
                header("Location: all-rds.php"); 
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Error updating RD application: " . $stmt_update->error . "</div>";
            }
            $stmt_update->close();
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
                                    <div class="title-header option-title">
                                        <h5>Edit Recurring Deposit Application: #<?php echo $rd_id; ?></h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    
                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="edit-rd.php?id=<?php echo $rd_id; ?>">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Customer</label>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($rd_data['customer_name']); ?>" readonly>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Installment Amount (₹)</label>
                                                <input class="form-control" type="number" step="10" id="depositAmount" name="deposit_amount" value="<?php echo htmlspecialchars($rd_data['deposit_amount']); ?>" placeholder="e.g., 500" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Interest Rate (% p.a.)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" value="<?php echo htmlspecialchars($rd_data['interest_rate']); ?>" placeholder="e.g., 6.5" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Deposit Cycle</label>
                                                <select class="form-select" id="repaymentCycle" name="repayment_cycle" required>
                                                    <option value="daily" <?php echo ($rd_data['repayment_cycle'] === 'daily') ? 'selected' : ''; ?>>Daily</option>
                                                    <option value="weekly" <?php echo ($rd_data['repayment_cycle'] === 'weekly') ? 'selected' : ''; ?>>Weekly</option>
                                                    <option value="monthly" <?php echo ($rd_data['repayment_cycle'] === 'monthly') ? 'selected' : ''; ?>>Monthly</option>
                                                    <option value="quarterly" <?php echo ($rd_data['repayment_cycle'] === 'quarterly') ? 'selected' : ''; ?>>Quarterly</option>
                                                    <option value="half-yearly" <?php echo ($rd_data['repayment_cycle'] === 'half-yearly') ? 'selected' : ''; ?>>Half-Yearly</option>
                                                    <option value="annually" <?php echo ($rd_data['repayment_cycle'] === 'annually') ? 'selected' : ''; ?>>Annually</option>
                                                </select>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Tenure (# of Installments)</label>
                                                <input class="form-control" type="number" id="tenure" name="tenure" value="<?php echo htmlspecialchars($rd_data['tenure']); ?>" placeholder="e.g., 12" required>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Start Date</label>
                                                <input class="form-control" type="date" id="startDate" name="start_date" value="<?php echo htmlspecialchars(date('Y-m-d', strtotime($rd_data['start_date']))); ?>" required>
                                            </div>

                                            <hr>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Estimated Maturity Amount (₹)</label>
                                                <input class="form-control" type="number" id="maturityAmount" readonly>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Estimated Maturity Date</label>
                                                <input class="form-control" type="date" id="maturityDate" readonly>
                                            </div>

                                            <div class="mt-3 d-flex gap-2">
                                                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
                                                <a href="all-rds.php" class="btn btn-secondary w-100 text-center">Cancel</a>
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
            const depositAmountInput = document.getElementById('depositAmount');
            const interestRateInput = document.getElementById('interestRate');
            const repaymentCycleInput = document.getElementById('repaymentCycle');
            const tenureInput = document.getElementById('tenure');
            const startDateInput = document.getElementById('startDate');
            const maturityAmountInput = document.getElementById('maturityAmount');
            const maturityDateInput = document.getElementById('maturityDate');

            function performCalculations() {
                const depositAmount = parseFloat(depositAmountInput.value);
                const interestRate = parseFloat(interestRateInput.value);
                const repaymentCycle = repaymentCycleInput.value;
                const tenure = parseInt(tenureInput.value);
                const startDateVal = startDateInput.value;

                if (!isNaN(depositAmount) && depositAmount > 0 && 
                    !isNaN(interestRate) && interestRate >= 0 && 
                    !isNaN(tenure) && tenure > 0) {
                    
                    // Maturity Amount calculation
                    const totalPrincipal = depositAmount * tenure;
                    let cyclesPerYear = 12;
                    switch(repaymentCycle) {
                        case 'daily': cyclesPerYear = 365; break;
                        case 'weekly': cyclesPerYear = 52; break;
                        case 'monthly': cyclesPerYear = 12; break;
                        case 'quarterly': cyclesPerYear = 4; break;
                        case 'half-yearly': cyclesPerYear = 2; break;
                        case 'annually': cyclesPerYear = 1; break;
                    }
                    const timeInYears = tenure / cyclesPerYear;
                    const totalInterest = totalPrincipal * (interestRate / 100) * timeInYears;
                    const maturityAmount = totalPrincipal + totalInterest;
                    maturityAmountInput.value = maturityAmount.toFixed(2);

                    // Maturity Date calculation
                    if (startDateVal) {
                        let start = new Date(startDateVal);
                        if (!isNaN(start.getTime())) {
                            switch (repaymentCycle) {
                                case 'daily':
                                    start.setDate(start.getDate() + tenure);
                                    break;
                                case 'weekly':
                                    start.setDate(start.getDate() + (tenure * 7));
                                    break;
                                case 'monthly':
                                    start.setMonth(start.getMonth() + tenure);
                                    break;
                                case 'quarterly':
                                    start.setMonth(start.getMonth() + (tenure * 3));
                                    break;
                                case 'half-yearly':
                                    start.setMonth(start.getMonth() + (tenure * 6));
                                    break;
                                case 'annually':
                                    start.setFullYear(start.getFullYear() + tenure);
                                    break;
                            }
                            const yyyy = start.getFullYear();
                            let mm = start.getMonth() + 1;
                            let dd = start.getDate();
                            if (mm < 10) mm = '0' + mm;
                            if (dd < 10) dd = '0' + dd;
                            maturityDateInput.value = `${yyyy}-${mm}-${dd}`;
                        }
                    }
                } else {
                    maturityAmountInput.value = '';
                    maturityDateInput.value = '';
                }
            }

            depositAmountInput.addEventListener('input', performCalculations);
            interestRateInput.addEventListener('input', performCalculations);
            repaymentCycleInput.addEventListener('change', performCalculations);
            tenureInput.addEventListener('input', performCalculations);
            startDateInput.addEventListener('input', performCalculations);

            // Run calculations once on load
            performCalculations();
        });
    </script>
</body>
</html>
