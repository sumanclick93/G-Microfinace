<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- ACTIVE BLOCK: Handle Admin Approval, Rejection, or Direct Deletion of Customers ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['customer_id'])) {
    $customer_id = intval($_POST['customer_id']);
    $action = $_POST['action'];

    if ($action === 'approve_delete' || $action === 'delete_customer') {
        // Double-check safety: ensure no active loans or RDs with remaining due balance
        $stmt_loans = $conn->prepare("SELECT COUNT(*) FROM loans WHERE customer_id = ? AND LOWER(TRIM(status)) NOT IN ('paid', 'rejected', 'closed', 'settled') AND (total_repayable_amount - IFNULL((SELECT SUM(amount_paid) FROM payments WHERE loan_id = loans.id AND status != 'rejected'), 0)) > 0.01");
        $stmt_loans->bind_param("i", $customer_id);
        $stmt_loans->execute();
        $active_loans = $stmt_loans->get_result()->fetch_row()[0];
        $stmt_loans->close();

        $stmt_rds = $conn->prepare("SELECT COUNT(*) FROM recurring_deposits WHERE customer_id = ? AND LOWER(TRIM(status)) NOT IN ('matured', 'rejected', 'closed', 'premature-closed', 'settled') AND ((deposit_amount * tenure) - IFNULL((SELECT SUM(amount_paid) FROM rd_payments WHERE rd_id = recurring_deposits.id AND status != 'rejected'), 0)) > 0.01");
        $stmt_rds->bind_param("i", $customer_id);
        $stmt_rds->execute();
        $active_rds = $stmt_rds->get_result()->fetch_row()[0];
        $stmt_rds->close();

        if ($active_loans > 0 || $active_rds > 0) {
            $_SESSION['message'] = "<div class='alert alert-danger d-flex align-items-center'><i class='ri-error-warning-line fs-4 me-2'></i> <strong>Cannot Delete:</strong> Customer has active Loans or RDs with pending balance ($active_loans active loans, $active_rds active RDs). Deletion blocked.</div>";
        } else {
            // 1. Fetch file names to delete them from server
            $stmt_files = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM customers WHERE id = ?");
            $stmt_files->bind_param("i", $customer_id);
            $stmt_files->execute();
            $files = $stmt_files->get_result()->fetch_assoc();
            $stmt_files->close();
            
            // Unlink files from the Agents upload folder
            if ($files) {
                if (!empty($files['avatar']) && file_exists('../Agents/upload/customers/avatars/' . $files['avatar'])) {
                    unlink('../Agents/upload/customers/avatars/' . $files['avatar']);
                }
                if (!empty($files['aadhar_photo']) && file_exists('../Agents/upload/customers/aadhar_cards/' . $files['aadhar_photo'])) {
                    unlink('../Agents/upload/customers/aadhar_cards/' . $files['aadhar_photo']);
                }
                if (!empty($files['pan_photo']) && file_exists('../Agents/upload/customers/pan_cards/' . $files['pan_photo'])) {
                    unlink('../Agents/upload/customers/pan_cards/' . $files['pan_photo']);
                }
            }

            // 2. Clean up historical closed loans/RDs and their payments so foreign key checks don't block customer deletion
            $conn->query("DELETE p FROM payments p JOIN loans l ON p.loan_id = l.id WHERE l.customer_id = " . intval($customer_id));
            $conn->query("DELETE FROM loans WHERE customer_id = " . intval($customer_id));
            $conn->query("DELETE rp FROM rd_payments rp JOIN recurring_deposits r ON rp.rd_id = r.id WHERE r.customer_id = " . intval($customer_id));
            $conn->query("DELETE FROM recurring_deposits WHERE customer_id = " . intval($customer_id));

            // 3. Permanently Delete from Database
            $stmt_del = $conn->prepare("DELETE FROM customers WHERE id = ?");
            $stmt_del->bind_param("i", $customer_id);
            if ($stmt_del->execute()) {
                $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-check-line fs-4 me-2'></i> Customer permanently deleted.</div>";
            } else {
                $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting customer from database: " . htmlspecialchars($conn->error, ENT_QUOTES, 'UTF-8') . "</div>";
            }
            $stmt_del->close();
        }
        header("Location: all-customers-loans.php");
        exit();

    } elseif ($action === 'reject_delete') {
        // Restore Customer to Active Status
        $stmt_restore = $conn->prepare("UPDATE customers SET status = 'active' WHERE id = ?");
        $stmt_restore->bind_param("i", $customer_id);
        if ($stmt_restore->execute()) {
            $_SESSION['message'] = "<div class='alert alert-info d-flex align-items-center'><i class='ri-refresh-line fs-4 me-2'></i> Customer restored to Active status.</div>";
        }
        $stmt_restore->close();
    }
    
    header("Location: all-customers-loans.php");
    exit();
}

// --- Handle Loan / RD Account Closure ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'close_loan' && isset($_POST['loan_id'])) {
        $loan_id = intval($_POST['loan_id']);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $payment_date = date("Y-m-d H:i:s");

        $conn->begin_transaction();
        try {
            // Fetch agent_id for the Loan
            $agent_id = 0;
            try {
                $stmt_agent = $conn->prepare("SELECT agent_id FROM loans WHERE id = ?");
                if ($stmt_agent) {
                    $stmt_agent->bind_param("i", $loan_id);
                    $stmt_agent->execute();
                    $agent_id = intval($stmt_agent->get_result()->fetch_row()[0] ?? 0);
                    $stmt_agent->close();
                }
            } catch (Throwable $t) {
                $agent_id = 0;
            }

            // 1. Insert payment if amount_paid > 0
            if ($amount_paid > 0) {
                $inserted_pay = false;
                if ($agent_id > 0) {
                    try {
                        $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
                        $stmt_payment = $conn->prepare($sql_payment);
                        if ($stmt_payment) {
                            $stmt_payment->bind_param("idsis", $loan_id, $amount_paid, $payment_date, $agent_id, $notes);
                            $inserted_pay = $stmt_payment->execute();
                            $new_payment_id = $stmt_payment->insert_id;
                            $stmt_payment->close();
                        }
                    } catch (Throwable $t) {
                        $inserted_pay = false;
                    }

                    if (!$inserted_pay) {
                        try {
                            $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, collected_by_agent_id, notes) VALUES (?, ?, ?, ?, ?)";
                            $stmt_payment = $conn->prepare($sql_payment);
                            if ($stmt_payment) {
                                $stmt_payment->bind_param("idsis", $loan_id, $amount_paid, $payment_date, $agent_id, $notes);
                                $inserted_pay = $stmt_payment->execute();
                                $new_payment_id = $stmt_payment->insert_id;
                                $stmt_payment->close();
                            }
                        } catch (Throwable $t) {
                            $inserted_pay = false;
                        }
                    }
                }

                if (!$inserted_pay) {
                    try {
                        $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, notes, status) VALUES (?, ?, ?, ?, 'approved')";
                        $stmt_payment = $conn->prepare($sql_payment);
                        if ($stmt_payment) {
                            $stmt_payment->bind_param("idss", $loan_id, $amount_paid, $payment_date, $notes);
                            $inserted_pay = $stmt_payment->execute();
                            $new_payment_id = $stmt_payment->insert_id;
                            $stmt_payment->close();
                        }
                    } catch (Throwable $t) {
                        $inserted_pay = false;
                    }
                }

                if (!$inserted_pay) {
                    $sql_payment = "INSERT INTO payments (loan_id, amount_paid, payment_date, notes) VALUES (?, ?, ?, ?)";
                    $stmt_payment = $conn->prepare($sql_payment);
                    if (!$stmt_payment) throw new Exception("Prepare payment failed: " . $conn->error);
                    $stmt_payment->bind_param("idss", $loan_id, $amount_paid, $payment_date, $notes);
                    if (!$stmt_payment->execute()) throw new Exception("Execute payment failed: " . $stmt_payment->error);
                    $new_payment_id = $stmt_payment->insert_id;
                    $stmt_payment->close();
                }

                // Log wallet transaction if agent_id > 0
                if ($agent_id > 0) {
                    try {
                        $sql_wallet = "INSERT INTO wallet_transactions (agent_id, loan_id, payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'emi-received', ?, ?)";
                        $stmt_wallet = $conn->prepare($sql_wallet);
                        if ($stmt_wallet) {
                            $description = "[Admin Closure Settle] Loan closed. Notes: $notes";
                            $stmt_wallet->bind_param("iiids", $agent_id, $loan_id, $new_payment_id, $amount_paid, $description);
                            $stmt_wallet->execute();
                            $stmt_wallet->close();
                        } else {
                            throw new Exception("Fallback wallet");
                        }
                    } catch (Throwable $t) {
                        try {
                            $sql_wallet = "INSERT INTO wallet_transactions (agent_id, loan_id, transaction_type, amount, description) VALUES (?, ?, 'emi-received', ?, ?)";
                            $stmt_wallet = $conn->prepare($sql_wallet);
                            if ($stmt_wallet) {
                                $description = "[Admin Closure Settle] Loan closed. Notes: $notes";
                                $stmt_wallet->bind_param("iids", $agent_id, $loan_id, $amount_paid, $description);
                                $stmt_wallet->execute();
                                $stmt_wallet->close();
                            }
                        } catch (Throwable $t2) {
                            // ignore
                        }
                    }
                }
            }

            // 2. Set loan status to 'closed'
            $conn->query("UPDATE loans SET status = 'closed' WHERE id = " . intval($loan_id));

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-checkbox-circle-line fs-4 me-2'></i> Loan account closed successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error closing loan: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
        }
        header("Location: all-customers-loans.php");
        exit();
    }

    if ($_POST['action'] === 'close_rd' && isset($_POST['rd_id'])) {
        $rd_id = intval($_POST['rd_id']);
        $amount_paid = floatval($_POST['amount_paid'] ?? 0);
        $notes = $_POST['notes'] ?? '';
        $payment_date = date("Y-m-d H:i:s");

        $conn->begin_transaction();
        try {
            // Fetch agent_id for the RD
            $agent_id = 0;
            try {
                $stmt_agent = $conn->prepare("SELECT agent_id FROM recurring_deposits WHERE id = ?");
                if ($stmt_agent) {
                    $stmt_agent->bind_param("i", $rd_id);
                    $stmt_agent->execute();
                    $agent_id = intval($stmt_agent->get_result()->fetch_row()[0] ?? 0);
                    $stmt_agent->close();
                }
            } catch (Throwable $t) {
                $agent_id = 0;
            }

            // 1. Insert RD payment if amount_paid > 0
            if ($amount_paid > 0) {
                $inserted_pay = false;
                if ($agent_id > 0) {
                    try {
                        $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, collected_by_agent_id, notes, status) VALUES (?, ?, ?, ?, ?, 'approved')";
                        $stmt_payment = $conn->prepare($sql_payment);
                        if ($stmt_payment) {
                            $stmt_payment->bind_param("idsis", $rd_id, $amount_paid, $payment_date, $agent_id, $notes);
                            $inserted_pay = $stmt_payment->execute();
                            $new_payment_id = $stmt_payment->insert_id;
                            $stmt_payment->close();
                        }
                    } catch (Throwable $t) {
                        $inserted_pay = false;
                    }

                    if (!$inserted_pay) {
                        try {
                            $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, collected_by_agent_id, notes) VALUES (?, ?, ?, ?, ?)";
                            $stmt_payment = $conn->prepare($sql_payment);
                            if ($stmt_payment) {
                                $stmt_payment->bind_param("idsis", $rd_id, $amount_paid, $payment_date, $agent_id, $notes);
                                $inserted_pay = $stmt_payment->execute();
                                $new_payment_id = $stmt_payment->insert_id;
                                $stmt_payment->close();
                            }
                        } catch (Throwable $t) {
                            $inserted_pay = false;
                        }
                    }
                }

                if (!$inserted_pay) {
                    try {
                        $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, notes, status) VALUES (?, ?, ?, ?, 'approved')";
                        $stmt_payment = $conn->prepare($sql_payment);
                        if ($stmt_payment) {
                            $stmt_payment->bind_param("idss", $rd_id, $amount_paid, $payment_date, $notes);
                            $inserted_pay = $stmt_payment->execute();
                            $new_payment_id = $stmt_payment->insert_id;
                            $stmt_payment->close();
                        }
                    } catch (Throwable $t) {
                        $inserted_pay = false;
                    }
                }

                if (!$inserted_pay) {
                    $sql_payment = "INSERT INTO rd_payments (rd_id, amount_paid, payment_date, notes) VALUES (?, ?, ?, ?)";
                    $stmt_payment = $conn->prepare($sql_payment);
                    if (!$stmt_payment) throw new Exception("Prepare RD payment failed: " . $conn->error);
                    $stmt_payment->bind_param("idss", $rd_id, $amount_paid, $payment_date, $notes);
                    if (!$stmt_payment->execute()) throw new Exception("Execute RD payment failed: " . $stmt_payment->error);
                    $new_payment_id = $stmt_payment->insert_id;
                    $stmt_payment->close();
                }

                // Log wallet transaction if agent_id > 0
                if ($agent_id > 0) {
                    try {
                        $sql_wallet = "INSERT INTO wallet_transactions (agent_id, rd_id, rd_payment_id, transaction_type, amount, description) VALUES (?, ?, ?, 'rd-received', ?, ?)";
                        $stmt_wallet = $conn->prepare($sql_wallet);
                        if ($stmt_wallet) {
                            $description = "[Admin Closure Settle] RD closed. Notes: $notes";
                            $stmt_wallet->bind_param("iiids", $agent_id, $rd_id, $new_payment_id, $amount_paid, $description);
                            $stmt_wallet->execute();
                            $stmt_wallet->close();
                        } else {
                            throw new Exception("Fallback wallet");
                        }
                    } catch (Throwable $t) {
                        try {
                            $sql_wallet = "INSERT INTO wallet_transactions (agent_id, rd_id, transaction_type, amount, description) VALUES (?, ?, 'rd-received', ?, ?)";
                            $stmt_wallet = $conn->prepare($sql_wallet);
                            if ($stmt_wallet) {
                                $description = "[Admin Closure Settle] RD closed. Notes: $notes";
                                $stmt_wallet->bind_param("iids", $agent_id, $rd_id, $amount_paid, $description);
                                $stmt_wallet->execute();
                                $stmt_wallet->close();
                            }
                        } catch (Throwable $t2) {
                            // ignore
                        }
                    }
                }
            }

            // 2. Set RD status to 'closed'
            $conn->query("UPDATE recurring_deposits SET status = 'closed' WHERE id = " . intval($rd_id));

            $conn->commit();
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-checkbox-circle-line fs-4 me-2'></i> RD account closed successfully.</div>";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['message'] = "<div class='alert alert-danger'>Error closing RD: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
        }
        header("Location: all-customers-loans.php");
        exit();
    } elseif ($_POST['action'] === 'delete_loan' && isset($_POST['loan_id'])) {
        $loan_id = intval($_POST['loan_id']);
        $status_check = $conn->query("SELECT status FROM loans WHERE id = $loan_id");
        $status = ($status_check && $row_s = $status_check->fetch_assoc()) ? $row_s['status'] : '';

        if (in_array($status, ['paid', 'closed', 'rejected'])) {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM payments WHERE loan_id = $loan_id");
                $conn->query("DELETE FROM wallet_transactions WHERE loan_id = $loan_id");
                $conn->query("DELETE FROM loans WHERE id = $loan_id");
                $conn->commit();
                $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-delete-bin-line fs-4 me-2'></i> Completed/Closed Loan account permanently deleted.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting loan: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Only completed (paid) or closed loans can be deleted.</div>";
        }
        header("Location: all-customers-loans.php");
        exit();
    } elseif ($_POST['action'] === 'delete_rd' && isset($_POST['rd_id'])) {
        $rd_id = intval($_POST['rd_id']);
        $status_check = $conn->query("SELECT status FROM recurring_deposits WHERE id = $rd_id");
        $status = ($status_check && $row_s = $status_check->fetch_assoc()) ? $row_s['status'] : '';

        if (in_array($status, ['matured', 'closed', 'premature-closed', 'rejected'])) {
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM rd_payments WHERE rd_id = $rd_id");
                $conn->query("DELETE FROM wallet_transactions WHERE rd_id = $rd_id");
                $conn->query("DELETE FROM recurring_deposits WHERE id = $rd_id");
                $conn->commit();
                $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-delete-bin-line fs-4 me-2'></i> Completed/Closed RD account permanently deleted.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting RD: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Only matured or closed RDs can be deleted.</div>";
        }
        header("Location: all-customers-loans.php");
        exit();
    }
}

// 2. Fetch all customers with their assigned agent
$customers = [];
$sql = "SELECT
            c.id,
            c.full_name,
            c.customer_id_string,
            c.phone,
            c.email,
            c.avatar,
            c.status,
            a.first_name as agent_first_name,
            a.last_name as agent_last_name
        FROM customers c
        JOIN agents a ON c.agent_id = a.id
        ORDER BY c.status DESC, c.id DESC"; // 'pending' will show above 'active' alphabetically

$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Fetch all customer loans
$customer_loans = [];
$loans_res = $conn->query("SELECT id, customer_id, loan_amount, total_repayable_amount, monthly_installment, approval_date, repayment_cycle, tenure, status FROM loans");
while ($row = $loans_res->fetch_assoc()) {
    $customer_loans[$row['customer_id']][] = $row;
}

// Fetch loan payments
$loan_payments = [];
$pay_res = $conn->query("SELECT loan_id, SUM(amount_paid) as total_paid FROM payments WHERE status != 'rejected' GROUP BY loan_id");
while ($row = $pay_res->fetch_assoc()) {
    $loan_payments[$row['loan_id']] = (float)$row['total_paid'];
}

// Fetch all customer RDs
$customer_rds = [];
$rds_res = $conn->query("SELECT id, customer_id, deposit_amount, tenure, repayment_cycle, start_date, status FROM recurring_deposits WHERE status != 'rejected'");
while ($row = $rds_res->fetch_assoc()) {
    $customer_rds[$row['customer_id']][] = $row;
}

// Fetch RD payments
$rd_payments = [];
$rd_pay_res = $conn->query("SELECT rd_id, SUM(amount_paid) as total_paid FROM rd_payments WHERE status != 'rejected' GROUP BY rd_id");
while ($row = $rd_pay_res->fetch_assoc()) {
    $rd_payments[$row['rd_id']] = (float)$row['total_paid'];
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
                        <div class="col-sm-12">
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>All Customers & Loans Overview</h5>
                                    </div>

                                    <?php if (!empty($message)) echo $message; ?>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Photo</th>
                                                    <th>Customer</th>
                                                    <th>Contact</th>
                                                    <th>Assigned Agent</th>
                                                    <th>Loan Summary</th>
                                                    <th>RD Summary</th>
                                                    <th>Status</th>
                                                    <th>Options</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($customers)) : ?>
                                                    <tr>
                                                        <td colspan="8" class="text-center text-muted">No customers found in the system.</td>
                                                    </tr>
                                                <?php else : ?>
                                                    <?php foreach ($customers as $customer) : ?>
                                                        <tr>
                                                            <td>
                                                                <div class="table-image">
                                                                    <?php
                                                                        $avatar_path = !empty($customer['avatar']) ? '../Agents/upload/customers/avatars/' . $customer['avatar'] : 'assets/images/users/default-avatar.png';
                                                                    ?>
                                                                    <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Avatar" style="max-width: 40px; border-radius: 5px;">
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="user-name">
                                                                    <span style="font-weight: 600;"><?php echo htmlspecialchars($customer['full_name']); ?></span>
                                                                    <span class="text-muted">(ID: <?php echo htmlspecialchars($customer['customer_id_string']); ?>)</span>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div><?php echo htmlspecialchars($customer['phone']); ?></div>
                                                                <small><?php echo htmlspecialchars($customer['email'] ?? 'No Email'); ?></small>
                                                            </td>
                                                            <td>
                                                                <?php echo htmlspecialchars($customer['agent_first_name'] . ' ' . $customer['agent_last_name']); ?>
                                                            </td>
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
                                                                    $status_clean = strtolower(trim($loan['status']));
                                                                    if (in_array($status_clean, ['paid', 'rejected', 'closed'])) {
                                                                        $remaining = 0.0;
                                                                    } else {
                                                                        $remaining = max(0.0, $repayable - $paid);
                                                                    }

                                                                    if (!in_array($status_clean, ['paid', 'rejected', 'closed']) && $remaining > 0.01) {
                                                                        $active_loans_count++;
                                                                        $due_loan_amount += $remaining;
                                                                    }

                                                                        // Calculate Default Amount (overdue installments)
                                                                        if ($status_clean === 'defaulted') {
                                                                            $default_loan_amount += $remaining;
                                                                        } elseif (!in_array($status_clean, ['paid', 'rejected', 'closed']) && $remaining > 0.01) {
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
                                                                    $status_clean = strtolower(trim($rd['status']));
                                                                    if (in_array($status_clean, ['matured', 'rejected', 'closed', 'premature-closed'])) {
                                                                        $remaining = 0.0;
                                                                    } else {
                                                                        $remaining = max(0.0, $target_val - $paid);
                                                                    }

                                                                    if (!in_array($status_clean, ['matured', 'rejected', 'closed', 'premature-closed']) && $remaining > 0.01) {
                                                                        $active_rds_count++;
                                                                        $due_rd_amount += $remaining;
                                                                    }

                                                                        // Calculate Default Amount (overdue installments) for RDs
                                                                        $start_date_str = $rd['start_date'];
                                                                        if (!empty($start_date_str) && !in_array($status_clean, ['matured', 'rejected', 'closed', 'premature-closed']) && $remaining > 0.01) {
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
                                                            ?>
                                                            <td>
                                                                <div><strong>Loans:</strong> <?php echo $total_loans; ?></div>
                                                                <div><strong>Total Amount:</strong> ₹<?php echo number_format($total_loan_amount, 2); ?></div>
                                                                <div><strong>Due Amount:</strong> ₹<?php echo number_format($due_loan_amount, 2); ?></div>
                                                                <div><strong>Default Amount:</strong> ₹<?php echo number_format($default_loan_amount, 2); ?></div>
                                                                <?php
                                                                $active_loans_list = [];
                                                                $completed_loans_list = [];
                                                                if (isset($customer_loans[$customer_id])) {
                                                                    foreach ($customer_loans[$customer_id] as $l) {
                                                                        $paid = isset($loan_payments[$l['id']]) ? $loan_payments[$l['id']] : 0.0;
                                                                        $repayable = (float)$l['total_repayable_amount'];
                                                                        $status_clean = strtolower(trim($l['status']));
                                                                        if (in_array($status_clean, ['paid', 'rejected', 'closed'])) {
                                                                            $remaining = 0.0;
                                                                            $completed_loans_list[] = [
                                                                                'id' => $l['id'],
                                                                                'amount' => $l['loan_amount'],
                                                                                'status' => ucfirst($status_clean)
                                                                            ];
                                                                        } else {
                                                                            $remaining = max(0.0, $repayable - $paid);
                                                                            if ($remaining > 0.01) {
                                                                                $active_loans_list[] = [
                                                                                    'id' => $l['id'],
                                                                                    'amount' => $l['loan_amount'],
                                                                                    'remaining' => $remaining
                                                                                ];
                                                                            } else {
                                                                                $completed_loans_list[] = [
                                                                                    'id' => $l['id'],
                                                                                    'amount' => $l['loan_amount'],
                                                                                    'status' => 'Paid'
                                                                                ];
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                                if (!empty($active_loans_list)):
                                                                ?>
                                                                    <button class="btn btn-sm btn-outline-danger close-loan-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-loans="<?php echo htmlspecialchars(json_encode($active_loans_list), ENT_QUOTES, 'UTF-8'); ?>"
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-close-circle-line"></i> Close Loan
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if (!empty($completed_loans_list)): ?>
                                                                    <button class="btn btn-sm btn-outline-secondary delete-loan-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-loans="<?php echo htmlspecialchars(json_encode($completed_loans_list), ENT_QUOTES, 'UTF-8'); ?>"
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-delete-bin-line"></i> Delete Loan
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
                                                                $completed_rds_list = [];
                                                                if (isset($customer_rds[$customer_id])) {
                                                                    foreach ($customer_rds[$customer_id] as $r) {
                                                                        $paid = isset($rd_payments[$r['id']]) ? $rd_payments[$r['id']] : 0.0;
                                                                        $target_val = (float)$r['deposit_amount'] * (int)$r['tenure'];
                                                                        $status_clean = strtolower(trim($r['status']));
                                                                        if (in_array($status_clean, ['matured', 'rejected', 'closed', 'premature-closed'])) {
                                                                            $remaining = 0.0;
                                                                            $completed_rds_list[] = [
                                                                                'id' => $r['id'],
                                                                                'amount' => $r['deposit_amount'],
                                                                                'status' => ucfirst($status_clean)
                                                                            ];
                                                                        } else {
                                                                            $remaining = max(0.0, $target_val - $paid);
                                                                            if ($remaining > 0.01) {
                                                                                $active_rds_list[] = [
                                                                                    'id' => $r['id'],
                                                                                    'amount' => $r['deposit_amount'],
                                                                                    'remaining' => $remaining
                                                                                ];
                                                                            } else {
                                                                                $completed_rds_list[] = [
                                                                                    'id' => $r['id'],
                                                                                    'amount' => $r['deposit_amount'],
                                                                                    'status' => 'Matured'
                                                                                ];
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                                if (!empty($active_rds_list)):
                                                                ?>
                                                                    <button class="btn btn-sm btn-outline-danger close-rd-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-rds="<?php echo htmlspecialchars(json_encode($active_rds_list), ENT_QUOTES, 'UTF-8'); ?>"
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-close-circle-line"></i> Close RD
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if (!empty($completed_rds_list)): ?>
                                                                    <button class="btn btn-sm btn-outline-secondary delete-rd-trigger-btn" 
                                                                            data-customer-id="<?php echo $customer_id; ?>" 
                                                                            data-customer-name="<?php echo htmlspecialchars($customer['full_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                            data-rds="<?php echo htmlspecialchars(json_encode($completed_rds_list), ENT_QUOTES, 'UTF-8'); ?>"
                                                                            style="padding: 2px 6px; font-size: 11px; display: block; margin: 5px auto 0;">
                                                                        <i class="ri-delete-bin-line"></i> Delete RD
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                            
                                                            <td>
                                                                <?php if(isset($customer['status']) && strtolower($customer['status']) == 'pending'): ?>
                                                                    <span class="badge" style="background-color: #ffc107; color: #000; padding: 5px 10px;">Pending Deletion</span>
                                                                <?php else: ?>
                                                                    <span class="badge" style="background-color: #28a745; padding: 5px 10px;">Active</span>
                                                                <?php endif; ?>
                                                            </td>

                                                            <td>
                                                                <ul style="display: flex; gap: 10px; align-items: center; list-style: none; padding: 0; margin: 0;">
                                                                    <li>
                                                                        <a href="admin-customer-details.php?id=<?php echo $customer['id']; ?>" title="View Full Details">
                                                                            <i class="ri-eye-line" style="font-size: 18px;"></i>
                                                                        </a>
                                                                    </li>
                                                                    
                                                                    <?php if(isset($customer['status']) && strtolower($customer['status']) == 'pending'): ?>
                                                                        <li>
                                                                            <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#approveModal" data-id="<?php echo $customer['id']; ?>" title="Approve Deletion">
                                                                                <i class="ri-delete-bin-line" style="color: #dc3545; font-size: 18px;"></i>
                                                                            </a>
                                                                        </li>
                                                                        <li>
                                                                            <a href="javascript:void(0)" class="action-btn" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $customer['id']; ?>" title="Reject & Restore">
                                                                                <i class="ri-refresh-line" style="color: #28a745; font-size: 18px;"></i>
                                                                            </a>
                                                                        </li>
                                                                    <?php else: ?>
                                                                        <li>
                                                                            <a href="javascript:void(0)" class="customer-delete-btn" 
                                                                                data-id="<?php echo $customer['id']; ?>" 
                                                                                data-name="<?php echo htmlspecialchars($customer['full_name']); ?>" 
                                                                                data-active-loans="<?php echo $active_loans_count; ?>" 
                                                                                data-active-rds="<?php echo $active_rds_count; ?>"
                                                                                data-role="admin" 
                                                                                title="Delete Customer">
                                                                                <i class="ri-delete-bin-line" style="color: #dc3545; font-size: 18px;"></i>
                                                                            </a>
                                                                        </li>
                                                                    <?php endif; ?>
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

    <div class="modal fade theme-modal remove-coupon" id="approveModal" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-block text-center">
                    <h5 class="modal-title w-100">Approve Deletion?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="remove-box">
                        <p>This will <strong>permanently delete</strong> the customer, their photos, and all associated account records. This action cannot be undone.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="all-customers-loans.php">
                        <input type="hidden" name="action" value="approve_delete">
                        <input type="hidden" name="customer_id" id="approveId" value="">
                        <button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-animation btn-md fw-bold btn-danger">Permanently Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade theme-modal remove-coupon" id="rejectModal" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-block text-center">
                    <h5 class="modal-title w-100">Reject Deletion?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <div class="remove-box">
                        <p>This will deny the Agent's deletion request and restore the customer to <strong>Active</strong> status.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="all-customers-loans.php">
                        <input type="hidden" name="action" value="reject_delete">
                        <input type="hidden" name="customer_id" id="rejectId" value="">
                        <button type="button" class="btn btn-animation btn-md fw-bold btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-animation btn-md fw-bold btn-success">Restore Customer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Pass the customer ID into the correct modal based on which button was clicked
        $('.action-btn').on('click', function() {
            var customerId = $(this).data('id');
            var targetModal = $(this).data('bs-target'); // gets "#approveModal" or "#rejectModal"
            
            if (targetModal === '#approveModal') {
                $('#approveId').val(customerId);
            } else if (targetModal === '#rejectModal') {
                $('#rejectId').val(customerId);
            }
        });
    });
    </script>
</body>
</html>