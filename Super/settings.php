<?php
include('config.php');

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gold_rate = isset($_POST['gold_rate_per_gram']) ? floatval($_POST['gold_rate_per_gram']) : 0;
    $processing_fee_percent = isset($_POST['gold_loan_processing_fee_percent']) ? floatval($_POST['gold_loan_processing_fee_percent']) : 0;

    if ($gold_rate <= 0) {
        $message = "<div class='alert alert-danger'>Gold rate must be a valid positive number.</div>";
    } elseif ($processing_fee_percent < 0) {
        $message = "<div class='alert alert-danger'>Processing fee percentage cannot be negative.</div>";
    } else {
        $u1 = set_system_setting($conn, 'gold_rate_per_gram', number_format($gold_rate, 2, '.', ''));
        $u2 = set_system_setting($conn, 'gold_loan_processing_fee_percent', number_format($processing_fee_percent, 2, '.', ''));

        if ($u1 && $u2) {
            $message = "<div class='alert alert-success'>System settings updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Failed to update settings. Please check system_settings table.</div>";
        }
    }
}

// Fetch current values
$current_gold_rate = get_system_setting($conn, 'gold_rate_per_gram', '5500.00');
$current_processing_fee = get_system_setting($conn, 'gold_loan_processing_fee_percent', '1.50');
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
                            <div class="card">
                                <div class="card-header">
                                    <h5>System & Loan Configuration</h5>
                                    <span>Manage global rates, gold valuation benchmarks, and dynamic loan parameters</span>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form" method="POST" action="settings.php">
                                        <div class="row">
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Gold Rate per Gram (₹)</label>
                                                <input class="form-control" type="number" step="0.01" name="gold_rate_per_gram" value="<?php echo htmlspecialchars($current_gold_rate); ?>" required>
                                                <small class="form-text text-muted">Used to calculate collateral valuation when field agents apply for Gold Loans.</small>
                                            </div>
                                            <div class="col-md-6 mb-4">
                                                <label class="form-label-title mb-2">Gold Loan Processing Fee (%)</label>
                                                <input class="form-control" type="number" step="0.01" name="gold_loan_processing_fee_percent" value="<?php echo htmlspecialchars($current_processing_fee); ?>" required>
                                                <small class="form-text text-muted">Percentage automatically calculated on the approved principal amount for Gold Loans.</small>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">Save Settings</button>
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
