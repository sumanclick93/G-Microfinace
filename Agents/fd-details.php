<?php
include('config.php');
date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-fds.php");
    exit();
}

$fd_id = (int)$_GET['id'];

// Fetch FD Details with Customer and Agent info
$sql = "SELECT fd.*, 
        c.full_name as customer_name, c.phone_number as customer_phone, c.email as customer_email, c.address as customer_address,
        a.full_name as agent_name, a.agent_code
        FROM fixed_deposits fd
        JOIN customers c ON fd.customer_id = c.id
        LEFT JOIN agents a ON fd.agent_id = a.id
        WHERE fd.id = ? AND fd.agent_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $fd_id, $agent_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows == 0) {
    header("Location: all-fds.php");
    exit();
}

$fd = $res->fetch_assoc();

// Fetch Payouts log
$stmt_p = $conn->prepare("SELECT * FROM fd_payouts WHERE fd_id = ? ORDER BY id DESC");
$stmt_p->bind_param("i", $fd_id);
$stmt_p->execute();
$payouts_res = $stmt_p->get_result();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<?php include('head.php'); ?>
<head>
    <style>
        @media print {
            body * { visibility: hidden; }
            #printableFdCertificate, #printableFdCertificate * { visibility: visible; }
            #printableFdCertificate { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <?php include('header.php'); ?>
        <div class="page-body-wrapper">
            <?php include('sidebaar.php'); ?>
            <div class="page-body">
                <div class="container-fluid">
                    
                    <div class="row mb-4 no-print">
                        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h4 class="mb-1"><i class="ri-bank-line me-2"></i>Fixed Deposit Details (#<?php echo htmlspecialchars($fd['fd_number']); ?>)</h4>
                                <p class="text-muted mb-0">Customer: <?php echo htmlspecialchars($fd['customer_name']); ?> (<?php echo htmlspecialchars($fd['customer_phone']); ?>)</p>
                            </div>
                            <div class="d-flex gap-2">
                                <button onclick="window.print()" class="btn btn-outline-primary"><i class="ri-printer-line me-1"></i> Print Certificate</button>
                                <?php if ($fd['status'] == 'pending'): ?>
                                    <a href="edit-fd.php?id=<?php echo $fd['id']; ?>" class="btn btn-warning"><i class="ri-edit-line me-1"></i> Edit Application</a>
                                <?php endif; ?>
                                <a href="all-fds.php" class="btn btn-secondary"><i class="ri-arrow-left-line me-1"></i> Back to List</a>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($_SESSION['message'])) { echo $_SESSION['message']; unset($_SESSION['message']); } ?>

                    <!-- Certificate Card (Printable) -->
                    <div class="row" id="printableFdCertificate">
                        <div class="col-12">
                            <div class="card border border-2 border-primary shadow-sm">
                                <div class="card-header bg-primary text-white p-4">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                                        <div>
                                            <h3 class="mb-1 text-white text-uppercase font-weight-bold">Samajbandhan Foundation</h3>
                                            <p class="mb-0 text-white-50">Fixed Deposit Receipt & Certificate</p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-white text-primary fs-6 px-3 py-2">
                                                FD NO: <?php echo htmlspecialchars($fd['fd_number']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body p-4">
                                    <div class="row mb-4">
                                        <div class="col-md-6 border-end">
                                            <h6 class="text-primary text-uppercase mb-3 font-weight-bold"><i class="ri-user-3-line me-1"></i> Customer Information</h6>
                                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($fd['customer_name']); ?></p>
                                            <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($fd['customer_phone']); ?></p>
                                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($fd['customer_email'] ?? 'N/A'); ?></p>
                                            <p class="mb-0"><strong>Address:</strong> <?php echo htmlspecialchars($fd['customer_address'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="col-md-6 ps-md-4">
                                            <h6 class="text-primary text-uppercase mb-3 font-weight-bold"><i class="ri-information-line me-1"></i> Deposit Status & Info</h6>
                                            <p class="mb-1"><strong>Status:</strong> 
                                                <?php
                                                $st = $fd['status'];
                                                if ($st == 'active') echo '<span class="badge bg-success">Active</span>';
                                                elseif ($st == 'pending') echo '<span class="badge bg-warning text-dark">Pending Approval</span>';
                                                elseif ($st == 'matured') echo '<span class="badge bg-info">Matured</span>';
                                                elseif ($st == 'closed') echo '<span class="badge bg-secondary">Closed</span>';
                                                else echo '<span class="badge bg-danger">Rejected</span>';
                                                ?>
                                            </p>
                                            <p class="mb-1"><strong>Agent Name:</strong> <?php echo htmlspecialchars($fd['agent_name'] ?? 'Direct'); ?> (Code: <?php echo htmlspecialchars($fd['agent_code'] ?? 'N/A'); ?>)</p>
                                            <p class="mb-1"><strong>Application Date:</strong> <?php echo date('d M Y, h:i A', strtotime($fd['created_at'])); ?></p>
                                            <p class="mb-0"><strong>Approval Date:</strong> <?php echo $fd['approval_date'] ? date('d M Y', strtotime($fd['approval_date'])) : 'Pending'; ?></p>
                                        </div>
                                    </div>

                                    <hr>

                                    <h6 class="text-primary text-uppercase mb-3 font-weight-bold"><i class="ri-calculator-line me-1"></i> Fixed Deposit Terms</h6>
                                    <div class="row text-center g-3">
                                        <div class="col-md-3 col-6">
                                            <div class="p-3 bg-light rounded">
                                                <small class="text-muted d-block mb-1">Principal Deposit Amount</small>
                                                <h4 class="text-dark font-weight-bold mb-0">₹<?php echo number_format($fd['deposit_amount'], 2); ?></h4>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-3 bg-light rounded">
                                                <small class="text-muted d-block mb-1">Interest Rate</small>
                                                <h4 class="text-primary font-weight-bold mb-0"><?php echo $fd['interest_rate']; ?>% p.a.</h4>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-3 bg-light rounded">
                                                <small class="text-muted d-block mb-1">Tenure</small>
                                                <h4 class="text-dark font-weight-bold mb-0"><?php echo $fd['tenure']; ?> Months</h4>
                                            </div>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <div class="p-3 bg-light rounded">
                                                <small class="text-muted d-block mb-1">Estimated Maturity Amount</small>
                                                <h4 class="text-success font-weight-bold mb-0">₹<?php echo number_format($fd['maturity_amount'], 2); ?></h4>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-4">
                                        <div class="col-md-4">
                                            <div class="border p-3 rounded">
                                                <small class="text-muted d-block">Payout Frequency</small>
                                                <span class="fw-bold text-uppercase"><?php echo str_replace('_', ' ', $fd['payout_frequency']); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border p-3 rounded">
                                                <small class="text-muted d-block">Deposit Start Date</small>
                                                <span class="fw-bold"><?php echo date('d F Y', strtotime($fd['start_date'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border p-3 rounded">
                                                <small class="text-muted d-block">Maturity Date</small>
                                                <span class="fw-bold text-success"><?php echo date('d F Y', strtotime($fd['maturity_date'])); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payout Log History -->
                    <div class="row mt-4 no-print">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="ri-history-line me-2"></i>Payout & Interest Transaction Log</h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Transaction ID</th>
                                                    <th>Payout Type</th>
                                                    <th>Amount</th>
                                                    <th>Payout Date</th>
                                                    <th>Payment Mode</th>
                                                    <th>Remarks</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if ($payouts_res->num_rows > 0): ?>
                                                    <?php while ($p = $payouts_res->fetch_assoc()): ?>
                                                        <tr>
                                                            <td>#<?php echo $p['id']; ?></td>
                                                            <td><span class="badge bg-light-info text-info text-capitalize"><?php echo $p['payout_type']; ?></span></td>
                                                            <td class="fw-bold text-success">₹<?php echo number_format($p['payout_amount'], 2); ?></td>
                                                            <td><?php echo date('d M Y', strtotime($p['payout_date'])); ?></td>
                                                            <td class="text-uppercase"><?php echo htmlspecialchars($p['payment_mode']); ?></td>
                                                            <td><?php echo htmlspecialchars($p['remarks'] ?? '-'); ?></td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">No payouts or interest transactions recorded yet.</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
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
