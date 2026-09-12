<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

// 1. Authentication Check for Agent
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = intval($_SESSION['agent_id']);

// --- Fetch Filter Parameters ---
$filter_category   = isset($_GET['category']) ? trim($_GET['category']) : 'all'; // 'all', 'loan', 'rd'
$filter_loan_type  = isset($_GET['loan_type']) ? trim($_GET['loan_type']) : 'all'; // 'all', 'standard', 'interest_only', 'gold'
$filter_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$filter_status      = isset($_GET['status']) ? trim($_GET['status']) : 'all'; // 'all', 'approved', 'pending', 'rejected'
$filter_start_date  = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$filter_end_date    = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

// Validate Category
if (!in_array($filter_category, ['all', 'loan', 'rd'])) {
    $filter_category = 'all';
}
// Validate Loan Type
if (!in_array($filter_loan_type, ['all', 'standard', 'interest_only', 'gold'])) {
    $filter_loan_type = 'all';
}
// Validate Status
if (!in_array($filter_status, ['all', 'approved', 'pending', 'rejected'])) {
    $filter_status = 'all';
}

// --- Fetch Customers assigned to this Agent ---
$my_customers = [];
$cust_stmt = $conn->prepare("SELECT id, full_name, customer_id_string FROM customers WHERE agent_id = ? ORDER BY full_name ASC");
$cust_stmt->bind_param("i", $agent_id);
$cust_stmt->execute();
$cust_res = $cust_stmt->get_result();
if ($cust_res) {
    while ($row = $cust_res->fetch_assoc()) {
        $my_customers[] = $row;
    }
}
$cust_stmt->close();

// --- Build SQL Queries Scoped to this Agent ---
// 1. Loans Where Clause
$loan_where = ["(p.collected_by_agent_id = $agent_id OR (p.collected_by_agent_id IS NULL AND c.agent_id = $agent_id))"];
if ($filter_customer_id > 0) {
    $loan_where[] = "l.customer_id = " . intval($filter_customer_id);
}
if (!empty($filter_start_date)) {
    $loan_where[] = "DATE(p.payment_date) >= '" . $conn->real_escape_string($filter_start_date) . "'";
}
if (!empty($filter_end_date)) {
    $loan_where[] = "DATE(p.payment_date) <= '" . $conn->real_escape_string($filter_end_date) . "'";
}
if ($filter_loan_type !== 'all') {
    $loan_where[] = "l.loan_type = '" . $conn->real_escape_string($filter_loan_type) . "'";
}
if ($filter_status !== 'all') {
    $loan_where[] = "p.status = '" . $conn->real_escape_string($filter_status) . "'";
}
$loan_where_str = implode(" AND ", $loan_where);

$loan_sql = "SELECT 
    'loan' as record_type,
    p.id as payment_id,
    p.amount_paid,
    p.payment_date,
    p.status as payment_status,
    p.notes,
    p.proof_image,
    l.id as account_id,
    l.loan_type as sub_type,
    c.id as customer_id,
    c.full_name as customer_name,
    c.customer_id_string
FROM payments p
JOIN loans l ON p.loan_id = l.id
JOIN customers c ON l.customer_id = c.id
WHERE $loan_where_str";

// 2. RD Where Clause
$rd_where = ["(p.collected_by_agent_id = $agent_id OR (p.collected_by_agent_id IS NULL AND c.agent_id = $agent_id))"];
if ($filter_customer_id > 0) {
    $rd_where[] = "rd.customer_id = " . intval($filter_customer_id);
}
if (!empty($filter_start_date)) {
    $rd_where[] = "DATE(p.payment_date) >= '" . $conn->real_escape_string($filter_start_date) . "'";
}
if (!empty($filter_end_date)) {
    $rd_where[] = "DATE(p.payment_date) <= '" . $conn->real_escape_string($filter_end_date) . "'";
}
if ($filter_status !== 'all') {
    $rd_where[] = "p.status = '" . $conn->real_escape_string($filter_status) . "'";
}
$rd_where_str = implode(" AND ", $rd_where);

$rd_sql = "SELECT 
    'rd' as record_type,
    p.id as payment_id,
    p.amount_paid,
    p.payment_date,
    p.status as payment_status,
    p.notes,
    p.proof_image,
    rd.id as account_id,
    'rd' as sub_type,
    c.id as customer_id,
    c.full_name as customer_name,
    c.customer_id_string
FROM rd_payments p
JOIN recurring_deposits rd ON p.rd_id = rd.id
JOIN customers c ON rd.customer_id = c.id
WHERE $rd_where_str";

// Determine final combined SQL
if ($filter_category === 'loan') {
    $final_sql = "$loan_sql ORDER BY payment_date DESC";
} elseif ($filter_category === 'rd') {
    $final_sql = "$rd_sql ORDER BY payment_date DESC";
} else {
    if ($filter_loan_type !== 'all') {
        $final_sql = "$loan_sql ORDER BY payment_date DESC";
    } else {
        $final_sql = "($loan_sql) UNION ALL ($rd_sql) ORDER BY payment_date DESC";
    }
}

// Check if filter has been explicitly applied / submitted
$filter_applied = isset($_GET['apply_filter']) || (isset($_GET['category']) && $_GET['category'] !== 'all') || (isset($_GET['loan_type']) && $_GET['loan_type'] !== 'all') || ($filter_customer_id > 0) || ($filter_status !== 'all') || !empty($filter_start_date) || !empty($filter_end_date);

$payments = [];
$total_amount = 0.0;
$total_loan_amount = 0.0;
$total_rd_amount = 0.0;

if ($filter_applied) {
    $result = $conn->query($final_sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
            $amt = floatval($row['amount_paid']);
            $total_amount += $amt;
            if ($row['record_type'] === 'loan') {
                $total_loan_amount += $amt;
            } else {
                $total_rd_amount += $amt;
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

                    <!-- Title Header -->
                    <div class="row mb-3 align-items-center">
                        <div class="col-md-6">
                            <h3 class="fw-bold text-dark mb-1">My Collection Records</h3>
                            <p class="text-muted mb-0">Complete history of collections made by you (Loans & RD Deposits)</p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-xl-3 col-sm-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold">My Total Collections</span>
                                        <h4 class="mb-0 fw-bold text-success mt-1">₹<?php echo number_format($total_amount, 2); ?></h4>
                                    </div>
                                    <div class="bg-light-success p-3 rounded-circle text-success">
                                        <i class="ri-money-dollar-circle-line fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold">Loan Collections</span>
                                        <h4 class="mb-0 fw-bold text-primary mt-1">₹<?php echo number_format($total_loan_amount, 2); ?></h4>
                                    </div>
                                    <div class="bg-light-primary p-3 rounded-circle text-primary">
                                        <i class="ri-bank-card-line fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold">RD Collections</span>
                                        <h4 class="mb-0 fw-bold text-warning mt-1">₹<?php echo number_format($total_rd_amount, 2); ?></h4>
                                    </div>
                                    <div class="bg-light-warning p-3 rounded-circle text-warning">
                                        <i class="ri-safe-2-line fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-sm-6">
                            <div class="card border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="text-muted small fw-semibold">Total Collections Count</span>
                                        <h4 class="mb-0 fw-bold text-dark mt-1"><?php echo count($payments); ?></h4>
                                    </div>
                                    <div class="bg-light-secondary p-3 rounded-circle text-dark">
                                        <i class="ri-file-list-3-line fs-3"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filter Bar Form -->
                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h6 class="mb-0 fw-bold text-dark"><i class="ri-filter-3-line me-2"></i>Filter My Collections</h6>
                        </div>
                        <div class="card-body p-3">
                            <form method="GET" action="all-payments.php" class="row g-3">
                                <input type="hidden" name="apply_filter" value="1">
                                <!-- Category Filter -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label small fw-bold">Payment Category</label>
                                    <select name="category" class="form-select form-select-sm" onchange="toggleLoanTypeFilter(this.value)">
                                        <option value="all" <?php if ($filter_category === 'all') echo 'selected'; ?>>All (Loan & RD)</option>
                                        <option value="loan" <?php if ($filter_category === 'loan') echo 'selected'; ?>>Loan Payments</option>
                                        <option value="rd" <?php if ($filter_category === 'rd') echo 'selected'; ?>>RD Deposits</option>
                                    </select>
                                </div>

                                <!-- Loan Sub-Type Filter -->
                                <div class="col-md-2 col-sm-6" id="loan_type_wrapper">
                                    <label class="form-label small fw-bold">Loan Type</label>
                                    <select name="loan_type" class="form-select form-select-sm">
                                        <option value="all" <?php if ($filter_loan_type === 'all') echo 'selected'; ?>>All Loan Types</option>
                                        <option value="standard" <?php if ($filter_loan_type === 'standard') echo 'selected'; ?>>Standard Loan</option>
                                        <option value="interest_only" <?php if ($filter_loan_type === 'interest_only') echo 'selected'; ?>>Interest Loan</option>
                                        <option value="gold" <?php if ($filter_loan_type === 'gold') echo 'selected'; ?>>Gold Loan</option>
                                    </select>
                                </div>

                                <!-- Customer Filter -->
                                <div class="col-md-3 col-sm-6">
                                    <label class="form-label small fw-bold">My Customer</label>
                                    <select name="customer_id" class="form-select form-select-sm">
                                        <option value="0">All My Customers</option>
                                        <?php foreach ($my_customers as $c): ?>
                                            <option value="<?php echo $c['id']; ?>" <?php if ($filter_customer_id == $c['id']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($c['full_name'] . ' (' . $c['customer_id_string'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Start Date -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label small fw-bold">From Date</label>
                                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                </div>

                                <!-- End Date -->
                                <div class="col-md-2 col-sm-6">
                                    <label class="form-label small fw-bold">To Date</label>
                                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                </div>

                                <!-- Status Filter -->
                                <div class="col-md-1 col-sm-6">
                                    <label class="form-label small fw-bold">Status</label>
                                    <select name="status" class="form-select form-select-sm">
                                        <option value="all" <?php if ($filter_status === 'all') echo 'selected'; ?>>All</option>
                                        <option value="approved" <?php if ($filter_status === 'approved') echo 'selected'; ?>>Approved</option>
                                        <option value="pending" <?php if ($filter_status === 'pending') echo 'selected'; ?>>Pending</option>
                                        <option value="rejected" <?php if ($filter_status === 'rejected') echo 'selected'; ?>>Rejected</option>
                                    </select>
                                </div>

                                <!-- Action Buttons -->
                                <div class="col-md-12 col-sm-12 d-flex align-items-end justify-content-end gap-2">
                                    <button type="submit" class="btn btn-sm btn-primary px-4"><i class="ri-search-line me-1"></i>Apply Filters</button>
                                    <a href="all-payments.php" class="btn btn-sm btn-light border"><i class="ri-refresh-line me-1"></i>Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (!$filter_applied): ?>
                        <div class="card my-3 border-0 shadow-sm" style="background: #f8fafc; border-radius: 12px;">
                            <div class="card-body text-center p-5">
                                <div class="mb-3">
                                    <i class="ri-filter-3-line text-primary" style="font-size: 48px; opacity: 0.7;"></i>
                                </div>
                                <h5 class="text-dark fw-bold">Select Filters To Display Data</h5>
                                <p class="text-secondary mb-0">Please select your desired filter options above and click <strong>"Apply Filters"</strong> to view collection records.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Payments Table Card -->
                        <div class="row">
                            <div class="col-sm-12">
                                <div class="card card-table shadow-sm border-0">
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table all-package theme-table" id="table_id">
                                                <thead>
                                                    <tr>
                                                        <th>Date & Time</th>
                                                        <th>Type / Category</th>
                                                        <th>Customer</th>
                                                        <th>Amount Paid</th>
                                                        <th>Status</th>
                                                        <th>Receipt</th>
                                                        <th>Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (empty($payments)): ?>
                                                        <tr>
                                                            <td colspan="7" class="text-center text-muted py-4">No collection records found for the selected filter criteria.</td>
                                                        </tr>
                                                    <?php else: ?>
                                                        <?php foreach ($payments as $pay): ?>
                                                            <tr>
                                                                <!-- Date & Time -->
                                                                <td style="font-size: 13px; font-weight: 500;">
                                                                    <?php echo date('d M Y, h:i A', strtotime($pay['payment_date'])); ?>
                                                                </td>

                                                                <!-- Type / Category -->
                                                                <td>
                                                                    <?php 
                                                                    if ($pay['record_type'] === 'rd') {
                                                                        echo '<span class="badge bg-success text-white"><i class="ri-safe-2-line me-1"></i>RD Deposit</span>';
                                                                    } else {
                                                                        $st = $pay['sub_type'] ?? 'standard';
                                                                        if ($st === 'gold') {
                                                                            echo '<span class="badge bg-warning text-dark"><i class="ri-gold-line me-1"></i>Gold Loan</span>';
                                                                        } elseif ($st === 'interest_only') {
                                                                            echo '<span class="badge bg-primary text-white"><i class="ri-percent-line me-1"></i>Interest Loan</span>';
                                                                        } else {
                                                                            echo '<span class="badge bg-info text-white"><i class="ri-bank-card-line me-1"></i>Standard Loan</span>';
                                                                        }
                                                                    }
                                                                    ?>
                                                                </td>

                                                                <!-- Customer -->
                                                                <td>
                                                                    <div class="user-name">
                                                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($pay['customer_name']); ?></span><br>
                                                                        <small class="text-muted">(ID: <?php echo htmlspecialchars($pay['customer_id_string']); ?>)</small>
                                                                    </div>
                                                                </td>

                                                                <!-- Amount Paid -->
                                                                <td style="font-weight: bold; color: #28a745; font-size: 15px;">
                                                                    ₹<?php echo number_format($pay['amount_paid'], 2); ?>
                                                                </td>

                                                                <!-- Status -->
                                                                <td>
                                                                    <?php 
                                                                    $st_val = strtolower($pay['payment_status'] ?? 'approved');
                                                                    if ($st_val === 'approved') {
                                                                        echo '<span class="badge bg-success"><i class="ri-checkbox-circle-line me-1"></i>Approved</span>';
                                                                    } elseif ($st_val === 'pending') {
                                                                        echo '<span class="badge bg-warning text-dark"><i class="ri-time-line me-1"></i>Pending</span>';
                                                                    } else {
                                                                        echo '<span class="badge bg-danger"><i class="ri-close-circle-line me-1"></i>Rejected</span>';
                                                                    }
                                                                    ?>
                                                                </td>

                                                                <!-- Proof Image -->
                                                                <td>
                                                                    <?php if (!empty($pay['proof_image'])): ?>
                                                                        <?php $proof_path = 'upload/payments/' . $pay['proof_image']; ?>
                                                                        <button class="btn btn-sm btn-info text-white view-proof-btn py-1 px-2" 
                                                                                data-bs-toggle="modal" 
                                                                                data-bs-target="#proofModal" 
                                                                                data-img="<?php echo htmlspecialchars($proof_path); ?>">
                                                                            <i class="ri-image-line me-1"></i> View Receipt
                                                                        </button>
                                                                    <?php else: ?>
                                                                        <span class="text-muted small">No Receipt</span>
                                                                    <?php endif; ?>
                                                                </td>

                                                                <!-- Notes -->
                                                                <td style="font-size: 12px;" class="text-muted">
                                                                    <?php echo htmlspecialchars($pay['notes'] ?? '-'); ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>

    <!-- Receipt Preview Modal -->
    <div class="modal fade" id="proofModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center p-3">
                    <img id="previewImage" src="" alt="Payment Proof" style="max-width: 100%; max-height: 500px; object-fit: contain; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleLoanTypeFilter(val) {
        var wrapper = document.getElementById('loan_type_wrapper');
        if (val === 'rd') {
            wrapper.style.opacity = '0.5';
        } else {
            wrapper.style.opacity = '1';
        }
    }

    $(document).ready(function() {
        // Pass Image Source to Preview Modal
        $('.view-proof-btn').on('click', function() {
            var imgSrc = $(this).data('img');
            $('#previewImage').attr('src', imgSrc);
        });
    });
    </script>
</body>
</html>
