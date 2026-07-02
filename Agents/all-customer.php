<?php
// Include the config file from the Super admin directory
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = intval($_SESSION['agent_id']);
$message = '';

// Check for any flash messages
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- ACTIVE BLOCK: Handle Customer Soft Deletion & Safety Check ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $customer_id_to_delete = intval($_POST['delete_id']);
    
    // 1. SAFETY CHECK: Count Active Loans (Any status other than paid, rejected, or closed)
    $stmt_loans = $conn->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ? AND status NOT IN ('paid', 'rejected', 'closed')");
    $stmt_loans->bind_param("i", $customer_id_to_delete);
    $stmt_loans->execute();
    $active_loans = $stmt_loans->get_result()->fetch_row()[0];
    $stmt_loans->close();

    // 2. SAFETY CHECK: Count Active RDs (Any status other than matured, rejected, or closed)
    $stmt_rds = $conn->prepare("SELECT COUNT(*) FROM recurring_deposits WHERE customer_id = ? AND status NOT IN ('matured', 'rejected', 'closed')");
    $stmt_rds->bind_param("i", $customer_id_to_delete);
    $stmt_rds->execute();
    $active_rds = $stmt_rds->get_result()->fetch_row()[0];
    $stmt_rds->close();

    // 3. DECISION: Block or Permanent Delete
    if ($active_loans > 0 || $active_rds > 0) {
        // Block Deletion
        $_SESSION['message'] = "<div class='alert alert-danger d-flex align-items-center'><i class='ri-error-warning-line fs-4 me-2'></i> <strong>Cannot Delete:</strong> This customer has active Loans or RDs. They must be Fully Paid or Matured before deletion.</div>";
    } else {
        // 1. Fetch file names to delete them from server
        $stmt_files = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM customers WHERE id = ? AND agent_id = ?");
        $stmt_files->bind_param("ii", $customer_id_to_delete, $agent_id);
        $stmt_files->execute();
        $files = $stmt_files->get_result()->fetch_assoc();
        $stmt_files->close();
        
        // Unlink files from the Agents upload folder
        if ($files) {
            if (!empty($files['avatar']) && file_exists('upload/customers/avatars/' . $files['avatar'])) {
                unlink('upload/customers/avatars/' . $files['avatar']);
            }
            if (!empty($files['aadhar_photo']) && file_exists('upload/customers/aadhar_cards/' . $files['aadhar_photo'])) {
                unlink('upload/customers/aadhar_cards/' . $files['aadhar_photo']);
            }
            if (!empty($files['pan_photo']) && file_exists('upload/customers/pan_cards/' . $files['pan_photo'])) {
                unlink('upload/customers/pan_cards/' . $files['pan_photo']);
            }
        }

        // 2. Permanently Delete from Database
        $stmt_del = $conn->prepare("DELETE FROM customers WHERE id = ? AND agent_id = ?");
        $stmt_del->bind_param("ii", $customer_id_to_delete, $agent_id);
        if ($stmt_del->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-check-line fs-4 me-2'></i> Customer permanently deleted.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting customer from database.</div>";
        }
        $stmt_del->close();
    }
    
    header("Location: all-customer.php");
    exit();
}

// --- Handle Loan / RD Account Closure ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'close_loan' && isset($_POST['loan_id'])) {
        $loan_id = intval($_POST['loan_id']);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        $payment_date = date("Y-m-d H:i:s");

        $conn->begin_transaction();
        try {
            // 1. Insert payment if amount_paid > 0
            if ($amount_paid > 0) {
                $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
                $stmt_payment = $conn->prepare($sql_payment);
                $stmt_payment->bind_param("idsis", $loan_id, $amount_paid, $payment_date, $agent_id, $notes);
                $stmt_payment->execute();
                $new_payment_id = $stmt_payment->insert_id;
                $stmt_payment->close();

                // Log wallet transaction
                $sql_wallet = "INSERT INTO wallet_transactions (agent_id, loan_id, payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'emi-received', ?, ?)";
                $stmt_wallet = $conn->prepare($sql_wallet);
                $description = "[Closure Settle] Loan closed. Notes: $notes";
                $stmt_wallet->bind_param("iiids", $agent_id, $loan_id, $new_payment_id, $amount_paid, $description);
                $stmt_wallet->execute();
                $stmt_wallet->close();
            }

            // 2. Set loan status to 'closed'
            $stmt_close = $conn->prepare("UPDATE loans SET status = 'closed' WHERE id = ? AND agent_id = ?");
            $stmt_close->bind_param("ii", $loan_id, $agent_id);
            $stmt_close->execute();
            $stmt_close->close();

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-checkbox-circle-line fs-4 me-2'></i> Loan account closed successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error closing loan: " . $e->getMessage() . "</div>";
        }
        header("Location: all-customer.php");
        exit();
    }

    if ($_POST['action'] === 'close_rd' && isset($_POST['rd_id'])) {
        $rd_id = intval($_POST['rd_id']);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        $notes = $conn->real_escape_string($_POST['notes'] ?? '');
        $payment_date = date("Y-m-d H:i:s");

        $conn->begin_transaction();
        try {
            // 1. Insert RD payment if amount_paid > 0
            if ($amount_paid > 0) {
                $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
                $stmt_payment = $conn->prepare($sql_payment);
                $stmt_payment->bind_param("idsis", $rd_id, $amount_paid, $payment_date, $agent_id, $notes);
                $stmt_payment->execute();
                $new_payment_id = $stmt_payment->insert_id;
                $stmt_payment->close();

                // Log wallet transaction
                $sql_wallet = "INSERT INTO wallet_transactions (agent_id, rd_id, rd_payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'rd-received', ?, ?)";
                $stmt_wallet = $conn->prepare($sql_wallet);
                $description = "[Closure Settle] RD closed. Notes: $notes";
                $stmt_wallet->bind_param("iiids", $agent_id, $rd_id, $new_payment_id, $amount_paid, $description);
                $stmt_wallet->execute();
                $stmt_wallet->close();
            }

            // 2. Set RD status to 'closed'
            $stmt_close = $conn->prepare("UPDATE recurring_deposits SET status = 'closed' WHERE id = ? AND agent_id = ?");
            $stmt_close->bind_param("ii", $rd_id, $agent_id);
            $stmt_close->execute();
            $stmt_close->close();

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-checkbox-circle-line fs-4 me-2'></i> RD account closed successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error closing RD: " . $e->getMessage() . "</div>";
        }
        header("Location: all-customer.php");
        exit();
    }
}

// --- Fetch all customers assigned to THIS agent ---
$customers = [];
$sql = "SELECT id, full_name, customer_id_string, phone, email, avatar, status FROM customers WHERE agent_id = ? ORDER BY full_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$stmt->close();

// Fetch all customer loans for agent's customers
$customer_loans = [];
$loans_res = $conn->prepare("SELECT id, customer_id, loan_amount, total_repayable_amount, monthly_installment, approval_date, repayment_cycle, tenure, status FROM loans WHERE agent_id = ?");
$loans_res->bind_param("i", $agent_id);
$loans_res->execute();
$loans_result = $loans_res->get_result();
while ($row = $loans_result->fetch_assoc()) {
    $customer_loans[$row['customer_id']][] = $row;
}
$loans_res->close();

// Fetch loan payments for agent's loans
$loan_payments = [];
$pay_res = $conn->prepare("SELECT p.loan_id, SUM(p.amount_paid) as total_paid FROM payments p JOIN loans l ON p.loan_id = l.id WHERE l.agent_id = ? AND p.status != 'rejected' GROUP BY p.loan_id");
$pay_res->bind_param("i", $agent_id);
$pay_res->execute();
$pay_result = $pay_res->get_result();
while ($row = $pay_result->fetch_assoc()) {
    $loan_payments[$row['loan_id']] = (float)$row['total_paid'];
}
$pay_res->close();

// Fetch all RDs for agent's customers
$customer_rds = [];
$rds_res = $conn->prepare("SELECT id, customer_id, deposit_amount, tenure, repayment_cycle, start_date, status FROM recurring_deposits WHERE agent_id = ? AND status != 'rejected'");
$rds_res->bind_param("i", $agent_id);
$rds_res->execute();
$rds_result = $rds_res->get_result();
while ($row = $rds_result->fetch_assoc()) {
    $customer_rds[$row['customer_id']][] = $row;
}
$rds_res->close();

// Fetch RD payments for agent's RDs
$rd_payments = [];
$rd_pay_res = $conn->prepare("SELECT rp.rd_id, SUM(rp.amount_paid) as total_paid FROM rd_payments rp JOIN recurring_deposits rd ON rp.rd_id = rd.id WHERE rd.agent_id = ? AND rp.status != 'rejected' GROUP BY rp.rd_id");
$rd_pay_res->bind_param("i", $agent_id);
$rd_pay_res->execute();
$rd_pay_result = $rd_pay_res->get_result();
while ($row = $rd_pay_result->fetch_assoc()) {
    $rd_payments[$row['rd_id']] = (float)$row['total_paid'];
}
$rd_pay_res->close();
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
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>My Customers</h5>
                                        <a href="add-customer.php" class="align-items-center btn btn-theme d-flex">
                                            <i data-feather="plus"></i>Add New Customer
                                        </a>
                                    </div>

                                    <?php if (!empty($message)) echo $message; ?>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Name & ID</th>
                                                    <th>Phone</th>
                                                    <th>Loan Summary</th>
                                                    <th>RD Summary</th>
                                                    <th>Status</th>
                                                    <th>Option</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($customers)) : ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center text-muted">You have not added any customers yet.</td>
                                                    </tr>
                                                <?php else : ?>
                                                    <?php foreach ($customers as $customer) : ?>
                                                        <tr>
                                                            <td>
                                                                <div class="table-image">
                                                                    <?php
                                                                    $avatar_path = !empty($customer['avatar']) ? 'upload/customers/avatars/' . $customer['avatar'] : 'assets/images/users/default-avatar.png';
                                                                    ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Customer Avatar">
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="user-name">
                                                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($customer['full_name']); ?></span>
                                                                    <span class="text-muted">(<?php echo htmlspecialchars($customer['customer_id_string']); ?>)</span>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                                            
                                                            <?php
                                                            $customer_id = $customer['id'];

                                                            // --- LOAN SUMMARY CALCULATIONS ---
                                                            $total_loans = 0;
                                                            $total_loan_amount = 0;
                                                            $due_loan_amount = 0;
                                                            $default_loan_amount = 0;
                                                            $active_loans_count = 0;

                                                            if (isset($customer_loans[$customer_id])) {
                                                                foreach ($customer_loans[$customer_id] as $loan) {
                                                                    $total_loans++;
                                                                    $total_loan_amount += (float)$loan['loan_amount'];

                                                                    $loan_id = $loan['id'];
                                                                    $paid = isset($loan_payments[$loan_id]) ? $loan_payments[$loan_id] : 0.0;
                                                                    $repayable = (float)$loan['total_repayable_amount'];
                                                                    $remaining = max(0.0, $repayable - $paid);

                                                                    if ($loan['status'] === 'active' || $loan['status'] === 'approved' || $loan['status'] === 'defaulted') {
                                                                        if ($loan['status'] !== 'paid' && $loan['status'] !== 'rejected' && $loan['status'] !== 'closed') {
                                                                            $active_loans_count++;
                                                                        }
                                                                        $due_loan_amount += $remaining;

                                                                        // Calculate Default Amount (overdue installments)
                                                                        if ($loan['status'] === 'defaulted') {
                                                                            $default_loan_amount += $remaining;
                                                                        } else {
                                                                            $approval_date_str = $loan['approval_date'];
                                                                            if (!empty($approval_date_str)) {
                                                                                $approval_date = new DateTime($approval_date_str);
                                                                                $approval_date->setTime(0, 0, 0);
                                                                                $today = new DateTime();
                                                                                $today->setTime(0, 0, 0);

                                                                                if ($today > $approval_date) {
                                                                                    $interval_str = '1 month';
                                                                                    switch (strtolower($loan['repayment_cycle'])) {
                                                                                        case 'daily': $interval_str = '1 day'; break;
                                                                                        case 'weekly': $interval_str = '1 week'; break;
                                                                                        case 'monthly': $interval_str = '1 month'; break;
                                                                                        case 'quarterly': $interval_str = '3 months'; break;
                                                                                        case 'half-yearly': $interval_str = '6 months'; break;
                                                                                        case 'annually': $interval_str = '1 year'; break;
                                                                                    }
                                                                                    
                                                                                    $installments_due = 0;
                                                                                    $temp_date = clone $approval_date;
                                                                                    while ($temp_date < $today && $installments_due < (int)$loan['tenure']) {
                                                                                        $temp_date->modify('+' . $interval_str);
                                                                                        if ($temp_date <= $today) {
                                                                                            $installments_due++;
                                                                                        }
                                                                                    }

                                                                                    $expected_paid = $installments_due * (float)$loan['monthly_installment'];
                                                                                    $overdue = max(0.0, $expected_paid - $paid);
                                                                                    $overdue = min($overdue, $remaining);
                                                                                    $default_loan_amount += $overdue;
                                                                                }
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }

                                                            // --- RD SUMMARY CALCULATIONS ---
                                                            $total_rds = 0;
                                                            $total_rd_amount = 0;
                                                            $due_rd_amount = 0;
                                                            $default_rd_amount = 0;
                                                            $active_rds_count = 0;

                                                            if (isset($customer_rds[$customer_id])) {
                                                                foreach ($customer_rds[$customer_id] as $rd) {
                                                                    $total_rds++;
                                                                    $target_val = (float)$rd['deposit_amount'] * (int)$rd['tenure'];
                                                                    $total_rd_amount += $target_val;

                                                                    $rd_id = $rd['id'];
                                                                    $paid = isset($rd_payments[$rd_id]) ? $rd_payments[$rd_id] : 0.0;
                                                                    $remaining = max(0.0, $target_val - $paid);

                                                                    if ($rd['status'] === 'active' || $rd['status'] === 'approved') {
                                                                        if ($rd['status'] !== 'matured' && $rd['status'] !== 'rejected' && $rd['status'] !== 'closed') {
                                                                            $active_rds_count++;
                                                                        }
                                                                        $due_rd_amount += $remaining;

                                                                        // Calculate Default Amount (overdue installments) for RDs
                                                                        $start_date_str = $rd['start_date'];
                                                                        if (!empty($start_date_str)) {
                                                                            $start_date = new DateTime($start_date_str);
                                                                            $start_date->setTime(0, 0, 0);
                                                                            $today = new DateTime();
                                                                            $today->setTime(0, 0, 0);

                                                                            if ($today > $start_date) {
                                                                                $interval_str = '1 month';
                                                                                switch (strtolower($rd['repayment_cycle'])) {
                                                                                    case 'daily': $interval_str = '1 day'; break;
                                                                                    case 'weekly': $interval_str = '1 week'; break;
                                                                                    case 'monthly': $interval_str = '1 month'; break;
                                                                                    case 'quarterly': $interval_str = '3 months'; break;
                                                                                    case 'half-yearly': $interval_str = '6 months'; break;
                                                                                    case 'annually': $interval_str = '1 year'; break;
                                                                                }

                                                                                $installments_due = 0;
                                                                                $temp_date = clone $start_date;
                                                                                while ($temp_date < $today && $installments_due < (int)$rd['tenure']) {
                                                                                    $temp_date->modify('+' . $interval_str);
                                                                                    if ($temp_date <= $today) {
                                                                                        $installments_due++;
                                                                                    }
                                                                                }

                                                                                $expected_paid = $installments_due * (float)$rd['deposit_amount'];
                                                                                $overdue = max(0.0, $expected_paid - $paid);
                                                                                $overdue = min($overdue, $remaining);
                                                                                $default_rd_amount += $overdue;
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                            ?>
                                                            
                                                            <td>
                                                                <div><strong>Loans:</strong> <?php echo $total_loans; ?></div>
                                                                <div><strong>Total Amount:</strong> ₹<?php echo number_format($total_loan_amount, 2); ?></div>
                                                                <div><strong>Due Amount:</strong> ₹<?php echo number_format($due_loan_amount, 2); ?></div>
                                                                <div><strong>Default Amount:</strong> ₹<?php echo number_format($default_loan_amount, 2); ?></div>
                                                                <?php
                                                                $active_loans_list = [];
                                                                if (isset($customer_loans[$customer_id])) {
                                                                    foreach ($customer_loans[$customer_id] as $l) {
                                                                        if ($l['status'] !== 'paid' && $l['status'] !== 'rejected' && $l['status'] !== 'closed') {
                                                                            $paid = isset($loan_payments[$l['id']]) ? $loan_payments[$l['id']] : 0.0;
                                                                            $repayable = (float)$l['total_repayable_amount'];
                                                                            $remaining = max(0.0, $repayable - $paid);
                                                                            $active_loans_list[] = [
                                                                                'id' => $l['id'],
                                                                                'amount' => $l['loan_amount'],
                                                                                'remaining' => $remaining
                                                                            ];
                                                                        }
                                                                    }
                                                                }
                                                                if (!empty($active_loans_list)):
                                                                ?>
                                                                    <button class="btn btn-sm btn-outline-danger close-loan-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name']); ?>"
                                                                            data-loans='<?php echo json_encode($active_loans_list); ?>'
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-close-circle-line"></i> Close Loan
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div><strong>RDs:</strong> <?php echo $total_rds; ?></div>
                                                                <div><strong>Total Amount:</strong> ₹<?php echo number_format($total_rd_amount, 2); ?></div>
                                                                <div><strong>Due Amount:</strong> ₹<?php echo number_format($due_rd_amount, 2); ?></div>
                                                                <div><strong>Default Amount:</strong> ₹<?php echo number_format($default_rd_amount, 2); ?></div>
                                                                <?php
                                                                $active_rds_list = [];
                                                                if (isset($customer_rds[$customer_id])) {
                                                                    foreach ($customer_rds[$customer_id] as $r) {
                                                                        if ($r['status'] !== 'matured' && $r['status'] !== 'rejected' && $r['status'] !== 'closed') {
                                                                            $paid = isset($rd_payments[$r['id']]) ? $rd_payments[$r['id']] : 0.0;
                                                                            $target_val = (float)$r['deposit_amount'] * (int)$r['tenure'];
                                                                            $remaining = max(0.0, $target_val - $paid);
                                                                            $active_rds_list[] = [
                                                                                'id' => $r['id'],
                                                                                'amount' => $r['deposit_amount'],
                                                                                'remaining' => $remaining
                                                                            ];
                                                                        }
                                                                    }
                                                                }
                                                                if (!empty($active_rds_list)):
                                                                ?>
                                                                    <button class="btn btn-sm btn-outline-danger close-rd-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name']); ?>"
                                                                            data-rds='<?php echo json_encode($active_rds_list); ?>'
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-close-circle-line"></i> Close RD
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                            
                                                            <td>
                                                                <?php if(isset($customer['status']) && strtolower($customer['status']) == 'pending'): ?>
                                                                    <span class="badge" style="background-color: #ffc107; color: #000; padding: 5px 10px;">Pending</span>
                                                                <?php else: ?>
                                                                    <span class="badge" style="background-color: #28a745; padding: 5px 10px;">Active</span>
                                                                <?php endif; ?>
                                                            </td>

                                                            <td>
                                                                <ul>
                                                                    <li>
                                                                        <a href="customer-rds.php?id=<?php echo $customer['id']; ?>" title="View RDs">
                                                                            <i class="ri-save-line" style="color: #007bff;"></i> 
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="customer-loans.php?id=<?php echo $customer['id']; ?>" title="View Loans">
                                                                            <i class="ri-money-dollar-box-line" style="color: #28a745;"></i>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="edit-customer.php?id=<?php echo $customer['id']; ?>" title="Edit Customer">
                                                                            <i class="ri-pencil-line" style="color: #ffc107;"></i>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <?php if(isset($customer['status']) && strtolower($customer['status']) == 'pending'): ?>
                                                                            <a href="javascript:void(0)" title="Already Pending Deletion" style="opacity: 0.4; cursor: not-allowed;">
                                                                                <i class="ri-delete-bin-line" style="color: #dc3545;"></i>
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="javascript:void(0)" class="customer-delete-btn" 
                                                                                data-id="<?php echo $customer['id']; ?>" 
                                                                                data-name="<?php echo htmlspecialchars($customer['full_name']); ?>" 
                                                                                data-active-loans="<?php echo $active_loans_count; ?>" 
                                                                                data-active-rds="<?php echo $active_rds_count; ?>"
                                                                                data-role="agent" 
                                                                                title="Delete Customer">
                                                                                <i class="ri-delete-bin-line" style="color: #dc3545;"></i>
                                                                            </a>
                                                                        <?php endif; ?>
                                                                    </li>
                                                                </ul>
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
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>

</body>
</html>