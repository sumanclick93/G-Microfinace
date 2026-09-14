<?php
// Include the config file
include('config.php');
date_default_timezone_set('Asia/Kolkata');

$logged_time = date("Y-m-d H:i:s");
if (!isset($_SESSION['agent_id'])) { 
    header("Location: index.php"); 
    exit(); 
}
$agent_id = $_SESSION['agent_id'];
$message = '';

// --- Customer Selection Logic ---
$customer_id_from_url = null;
$customer_name_display = '';
$customer_list_for_dropdown = [];

if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customer_id_from_url = (int)$_GET['customer_id'];
    $stmt = $conn->prepare("SELECT full_name FROM customers WHERE id = ? AND agent_id = ?");
    $stmt->bind_param("ii", $customer_id_from_url, $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { 
        $customer_name_display = $result->fetch_assoc()['full_name']; 
    } else { 
        header("Location: all-customer.php"); 
        exit(); 
    }
} else {
    $stmt = $conn->prepare("SELECT id, full_name FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
    $stmt->bind_param("i", $agent_id); 
    $stmt->execute(); 
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { 
        $customer_list_for_dropdown[] = $row; 
    }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $deposit_amount = (float)$_POST['deposit_amount'];
    $interest_rate = (float)$_POST['interest_rate'];
    $tenure = (int)$_POST['tenure']; // Tenure in months
    $payout_frequency = $_POST['payout_frequency'] ?? 'on_maturity';
    $start_date = $_POST['start_date'];

    if ($customer_id <= 0 || $deposit_amount <= 0 || $tenure <= 0 || $interest_rate <= 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill all required fields correctly.</div>";
    } else {
        // Calculate Interest & Maturity Amount (Simple Interest: P * R * T_years)
        $time_in_years = $tenure / 12.0;
        $total_interest = round($deposit_amount * ($interest_rate / 100) * $time_in_years, 2);
        $maturity_amount = round($deposit_amount + $total_interest, 2);

        // Calculate Maturity Date
        $start_date_obj = new DateTime($start_date);
        $maturity_date_obj = clone $start_date_obj;
        $maturity_date_obj->add(new DateInterval("P{$tenure}M"));
        $maturity_date = $maturity_date_obj->format('Y-m-d');

        // Generate unique FD Number
        $fd_number = 'FD-' . date('Ymd') . '-' . rand(1000, 9999);

        // Insert FD Record
        $sql = "INSERT INTO fixed_deposits (
                    fd_number, customer_id, agent_id, deposit_amount, 
                    interest_rate, tenure, payout_frequency, total_interest, 
                    maturity_amount, start_date, maturity_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt_insert = $conn->prepare($sql);
        $stmt_insert->bind_param(
            "siiddissdss",
            $fd_number, $customer_id, $agent_id, $deposit_amount,
            $interest_rate, $tenure, $payout_frequency, $total_interest,
            $maturity_amount, $start_date, $maturity_date
        );

        if ($stmt_insert->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Fixed Deposit (FD) application #{$fd_number} submitted successfully! Awaiting admin approval.</div>";
            header("Location: customer-fds.php?id=" . $customer_id); 
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error creating FD account: " . $stmt_insert->error . "</div>";
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
                                        <h5><i class="ri-bank-line me-2"></i>New Fixed Deposit (FD) Application</h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="apply-fd.php<?php if($customer_id_from_url) echo '?customer_id='.$customer_id_from_url; ?>">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Customer</label>
                                                <?php if ($customer_id_from_url): ?>
                                                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($customer_name_display); ?>" readonly>
                                                    <input type="hidden" name="customer_id" value="<?php echo $customer_id_from_url; ?>">
                                                <?php else: ?>
                                                    <select class="form-select" name="customer_id" required>
                                                        <option value="">-- Select Customer --</option>
                                                        <?php foreach ($customer_list_for_dropdown as $c): ?>
                                                            <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Lump-sum Deposit Amount (₹)</label>
                                                <input class="form-control" type="number" step="500" id="depositAmount" name="deposit_amount" placeholder="e.g., 50000" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Interest Rate (% p.a.)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" placeholder="e.g., 8.5" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Tenure (in Months)</label>
                                                <input class="form-control" type="number" id="tenure" name="tenure" placeholder="e.g., 12" min="1" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Payout Frequency</label>
                                                <select class="form-select" name="payout_frequency" required>
                                                    <option value="on_maturity" selected>On Maturity (Principal + Interest)</option>
                                                    <option value="monthly">Monthly Interest Payout</option>
                                                    <option value="quarterly">Quarterly Interest Payout</option>
                                                    <option value="annually">Annual Interest Payout</option>
                                                </select>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Deposit Start Date</label>
                                                <input class="form-control" type="date" id="startDate" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                                            </div>

                                            <hr class="my-3">

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Estimated Interest (₹)</label>
                                                <input class="form-control bg-light fw-bold text-success" type="text" id="estimatedInterest" readonly placeholder="₹0.00">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Estimated Maturity Amount (₹)</label>
                                                <input class="form-control bg-light fw-bold text-primary" type="text" id="maturityAmount" readonly placeholder="₹0.00">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Maturity Date</label>
                                                <input class="form-control bg-light fw-bold" type="text" id="maturityDateDisplay" readonly placeholder="YYYY-MM-DD">
                                            </div>

                                            <div class="mt-4">
                                                <button type="submit" class="btn btn-primary w-100 btn-lg"><i class="ri-check-line me-1"></i> Submit FD Application</button>
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
            const depositAmount = document.getElementById('depositAmount');
            const interestRate = document.getElementById('interestRate');
            const tenure = document.getElementById('tenure');
            const startDate = document.getElementById('startDate');
            
            const estimatedInterest = document.getElementById('estimatedInterest');
            const maturityAmount = document.getElementById('maturityAmount');
            const maturityDateDisplay = document.getElementById('maturityDateDisplay');

            function calculateFD() {
                const P = parseFloat(depositAmount.value);
                const R = parseFloat(interestRate.value);
                const T_months = parseInt(tenure.value);
                const sDateVal = startDate.value;

                if (!isNaN(P) && P > 0 && !isNaN(R) && R >= 0 && !isNaN(T_months) && T_months > 0) {
                    const timeYears = T_months / 12.0;
                    const interest = P * (R / 100.0) * timeYears;
                    const matAmount = P + interest;

                    estimatedInterest.value = '₹' + interest.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    maturityAmount.value = '₹' + matAmount.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                    if (sDateVal) {
                        const sDate = new Date(sDateVal);
                        sDate.setMonth(sDate.getMonth() + T_months);
                        const yyyy = sDate.getFullYear();
                        const mm = String(sDate.getMonth() + 1).padStart(2, '0');
                        const dd = String(sDate.getDate()).padStart(2, '0');
                        maturityDateDisplay.value = `${yyyy}-${mm}-${dd}`;
                    } else {
                        maturityDateDisplay.value = '';
                    }
                } else {
                    estimatedInterest.value = '₹0.00';
                    maturityAmount.value = '₹0.00';
                    maturityDateDisplay.value = '';
                }
            }

            depositAmount.addEventListener('input', calculateFD);
            interestRate.addEventListener('input', calculateFD);
            tenure.addEventListener('input', calculateFD);
            startDate.addEventListener('change', calculateFD);
        });
    </script>
</body>
</html>
