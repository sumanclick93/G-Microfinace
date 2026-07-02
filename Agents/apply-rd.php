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

// --- Conditional Customer Selection ---
$customer_id_from_url = null;
$customer_name_display = '';
$customer_list_for_dropdown = [];

if (isset($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
    $customer_id_from_url = $_GET['customer_id'];
    $stmt = $conn->prepare("SELECT full_name FROM customers WHERE id = ? AND agent_id = ?");
    $stmt->bind_param("ii", $customer_id_from_url, $agent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) { $customer_name_display = $result->fetch_assoc()['full_name']; }
    else { header("Location: all-customer.php"); exit(); }
} else {
    $stmt = $conn->prepare("SELECT id, full_name FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
    $stmt->bind_param("i", $agent_id); $stmt->execute(); $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $customer_list_for_dropdown[] = $row; }
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = (int)$_POST['customer_id'];
    $deposit_amount = (float)$_POST['deposit_amount'];
    $repayment_cycle = $_POST['repayment_cycle'];
    $tenure = (int)$_POST['tenure'];
    $interest_rate = (float)$_POST['interest_rate'];
    $start_date = $_POST['start_date'];

    if ($customer_id <= 0 || $deposit_amount <= 0 || $tenure <= 0 || $interest_rate < 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill all fields correctly.</div>";
    } else {
        // --- Calculate Maturity Amount (Simple Interest) ---
        $total_principal = $deposit_amount * $tenure;

        // Using switch for compatibility
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
        
        // --- THIS IS THE SECOND FIX: Using switch for compatibility ---
        $interval_string = '';
        switch ($repayment_cycle) {
            case 'daily': 
                $interval_string = "P{$tenure}D"; // Period 10 Days
                break; 
            case 'weekly': 
                $interval_string = "P{$tenure}W"; // Period 10 Weeks
                break;
            case 'monthly': 
                $interval_string = "P{$tenure}M"; // Period 10 Months
                break;
            case 'quarterly': 
                $interval_string = "P" . ($tenure * 3) . "M"; // Period 30 Months (10 Quarters)
                break;
            case 'half-yearly': 
                $interval_string = "P" . ($tenure * 6) . "M"; // Period 60 Months (10 Half-years)
                break;
            case 'annually': 
                $interval_string = "P{$tenure}Y"; // Period 10 Years
                break;
            default: 
                $interval_string = "P{$tenure}M"; // Default to Months
        }
        // --- END OF SECOND FIX ---

        try {
             $maturity_date_obj = $start_date_obj->add(new DateInterval($interval_string));
             $maturity_date = $maturity_date_obj->format('Y-m-d');
        } catch (Exception $e) {
             $message = "<div class='alert alert-danger'>Error calculating maturity date. Check tenure and cycle.</div>";
             $maturity_date = null; 
        }

        // --- Insert into Database ---
        if ($maturity_date) { 
            
            $sql = "INSERT INTO recurring_deposits (customer_id, agent_id, deposit_amount, repayment_cycle, tenure, interest_rate, maturity_amount, start_date, maturity_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt_insert = $conn->prepare($sql);
            // Corrected type string: i(int), i(int), d(decimal), s(string), i(int), d(decimal), d(decimal), s(string), s(string)
            $stmt_insert->bind_param("iidsiddss", $customer_id, $agent_id, $deposit_amount, $repayment_cycle, $tenure, $interest_rate, $maturity_amount, $start_date, $maturity_date);

            if ($stmt_insert->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success'>Recurring Deposit application submitted successfully and is now pending approval.</div>";
                header("Location: customer-rds.php?id=" . $customer_id); 
                exit();
            } else {
                $message = "<div class='alert alert-danger'>Error creating RD account: " . $stmt_insert->error . "</div>";
            }
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
                                    <div class="title-header option-title"><h5>New Recurring Deposit Application</h5></div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="apply-rd.php<?php if($customer_id_from_url) echo '?customer_id='.$customer_id_from_url; ?>">
                                        <div class="row">
                                            <div class="mb-4"><label class="form-label-title mb-2">Customer</label>
                                                <?php if ($customer_id_from_url): ?>
                                                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($customer_name_display); ?>" readonly>
                                                    <input type="hidden" name="customer_id" value="<?php echo $customer_id_from_url; ?>">
                                                <?php else: ?>
                                                    <select class="form-select" name="customer_id" required><option value="">-- Select --</option><?php foreach ($customer_list_for_dropdown as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['full_name']); ?></option><?php endforeach; ?></select>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6 mb-4"><label class="form-label-title mb-2">Installment Amount (₹)</label><input class="form-control" type="number" step="10" name="deposit_amount" placeholder="e.g., 500" required></div>
                                            <div class="col-md-6 mb-4"><label class="form-label-title mb-2">Interest Rate (% p.a.)</label><input class="form-control" type="number" step="0.1" name="interest_rate" placeholder="e.g., 6.5" required></div>
                                            <div class="col-md-6 mb-4"><label class="form-label-title mb-2">Deposit Cycle</label><select class="form-select" name="repayment_cycle" required><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly" selected>Monthly</option><option value="quarterly">Quarterly</option><option value="half-yearly">Half-Yearly</option><option value="annually">Annually</option></select></div>
                                            <div class="col-md-6 mb-4"><label class="form-label-title mb-2">Tenure (# of Installments)</label><input class="form-control" type="number" name="tenure" placeholder="e.g., 12" required></div>
                                            <div class="mb-4"><label class="form-label-title mb-2">Start Date</label><input class="form-control" type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                                            <div class="mt-3"><button type="submit" class="btn btn-primary w-100">Create RD Account</button></div>
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
</body>
</html>