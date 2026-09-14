<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];
$message = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-fds.php");
    exit();
}

$fd_id = (int)$_GET['id'];

// Fetch existing FD
$stmt = $conn->prepare("SELECT fd.*, c.full_name as customer_name FROM fixed_deposits fd JOIN customers c ON fd.customer_id = c.id WHERE fd.id = ? AND fd.agent_id = ?");
$stmt->bind_param("ii", $fd_id, $agent_id);
$stmt->execute();
$fd_res = $stmt->get_result();

if ($fd_res->num_rows == 0) {
    header("Location: all-fds.php");
    exit();
}

$fd = $fd_res->fetch_assoc();

if ($fd['status'] !== 'pending') {
    $_SESSION['message'] = "<div class='alert alert-warning'>Only pending FD applications can be edited.</div>";
    header("Location: fd-details.php?id=" . $fd_id);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $deposit_amount = (float)$_POST['deposit_amount'];
    $interest_rate = (float)$_POST['interest_rate'];
    $tenure = (int)$_POST['tenure'];
    $payout_frequency = $_POST['payout_frequency'] ?? 'on_maturity';
    $start_date = $_POST['start_date'];

    if ($deposit_amount <= 0 || $tenure <= 0 || $interest_rate <= 0 || empty($start_date)) {
        $message = "<div class='alert alert-danger'>Please fill all required fields correctly.</div>";
    } else {
        $time_in_years = $tenure / 12.0;
        $total_interest = round($deposit_amount * ($interest_rate / 100) * $time_in_years, 2);
        $maturity_amount = round($deposit_amount + $total_interest, 2);

        $start_date_obj = new DateTime($start_date);
        $maturity_date_obj = clone $start_date_obj;
        $maturity_date_obj->add(new DateInterval("P{$tenure}M"));
        $maturity_date = $maturity_date_obj->format('Y-m-d');

        $update_sql = "UPDATE fixed_deposits SET 
                        deposit_amount = ?, 
                        interest_rate = ?, 
                        tenure = ?, 
                        payout_frequency = ?, 
                        total_interest = ?, 
                        maturity_amount = ?, 
                        start_date = ?, 
                        maturity_date = ? 
                       WHERE id = ? AND agent_id = ? AND status = 'pending'";
        
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("ddisddssii", $deposit_amount, $interest_rate, $tenure, $payout_frequency, $total_interest, $maturity_amount, $start_date, $maturity_date, $fd_id, $agent_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>FD application updated successfully.</div>";
            header("Location: fd-details.php?id=" . $fd_id);
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error updating FD application: " . $stmt_update->error . "</div>";
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
                                        <h5><i class="ri-edit-line me-2"></i>Edit Pending FD Application (<?php echo htmlspecialchars($fd['fd_number']); ?>)</h5>
                                        <a href="fd-details.php?id=<?php echo $fd_id; ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="edit-fd.php?id=<?php echo $fd_id; ?>">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Customer</label>
                                                <input class="form-control" type="text" value="<?php echo htmlspecialchars($fd['customer_name']); ?>" readonly>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Deposit Amount (₹)</label>
                                                <input class="form-control" type="number" step="500" id="depositAmount" name="deposit_amount" value="<?php echo $fd['deposit_amount']; ?>" required>
                                            </div>
                                            
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Interest Rate (% p.a.)</label>
                                                <input class="form-control" type="number" step="0.1" id="interestRate" name="interest_rate" value="<?php echo $fd['interest_rate']; ?>" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Tenure (in Months)</label>
                                                <input class="form-control" type="number" id="tenure" name="tenure" value="<?php echo $fd['tenure']; ?>" required>
                                            </div>

                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Payout Frequency</label>
                                                <select class="form-select" name="payout_frequency" required>
                                                    <option value="on_maturity" <?php if($fd['payout_frequency']=='on_maturity') echo 'selected'; ?>>On Maturity (Principal + Interest)</option>
                                                    <option value="monthly" <?php if($fd['payout_frequency']=='monthly') echo 'selected'; ?>>Monthly Interest Payout</option>
                                                    <option value="quarterly" <?php if($fd['payout_frequency']=='quarterly') echo 'selected'; ?>>Quarterly Interest Payout</option>
                                                    <option value="annually" <?php if($fd['payout_frequency']=='annually') echo 'selected'; ?>>Annual Interest Payout</option>
                                                </select>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Start Date</label>
                                                <input class="form-control" type="date" id="startDate" name="start_date" value="<?php echo $fd['start_date']; ?>" required>
                                            </div>

                                            <hr class="my-3">

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Estimated Interest (₹)</label>
                                                <input class="form-control bg-light fw-bold text-success" type="text" id="estimatedInterest" readonly value="₹<?php echo number_format($fd['total_interest'], 2); ?>">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Estimated Maturity Amount (₹)</label>
                                                <input class="form-control bg-light fw-bold text-primary" type="text" id="maturityAmount" readonly value="₹<?php echo number_format($fd['maturity_amount'], 2); ?>">
                                            </div>

                                            <div class="col-md-4 mb-3">
                                                <label class="form-label-title mb-1 text-muted">Maturity Date</label>
                                                <input class="form-control bg-light fw-bold" type="text" id="maturityDateDisplay" readonly value="<?php echo $fd['maturity_date']; ?>">
                                            </div>

                                            <div class="mt-4">
                                                <button type="submit" class="btn btn-primary w-100 btn-lg">Update FD Details</button>
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

                    estimatedInterest.value = '₹' + interest.toFixed(2);
                    maturityAmount.value = '₹' + matAmount.toFixed(2);

                    if (sDateVal) {
                        const sDate = new Date(sDateVal);
                        sDate.setMonth(sDate.getMonth() + T_months);
                        const yyyy = sDate.getFullYear();
                        const mm = String(sDate.getMonth() + 1).padStart(2, '0');
                        const dd = String(sDate.getDate()).padStart(2, '0');
                        maturityDateDisplay.value = `${yyyy}-${mm}-${dd}`;
                    }
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
