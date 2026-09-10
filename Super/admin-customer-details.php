<?php
// Include the config file
include('config.php');

// 1. Authentication Check for Super Admin
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Get Customer ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customers-loans.php");
    exit();
}
$customer_id = $_GET['id'];
$message = '';

// Check for a flash message from a redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 3. Handle Agent Reassignment Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['new_agent_id'])) {
    $new_agent_id = (int)$_POST['new_agent_id'];

    if ($new_agent_id > 0) {
        $stmt_update = $conn->prepare("UPDATE customers SET agent_id = ? WHERE id = ?");
        $stmt_update->bind_param("ii", $new_agent_id, $customer_id);

        if ($stmt_update->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Customer successfully reassigned!</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error reassigning customer.</div>";
        }
        $stmt_update->close();
        header("Location: admin-customer-details.php?id=" . $customer_id);
        exit();
    }
}

// 3b. Handle Password Reset Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password)) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Password cannot be empty.</div>";
    } elseif (strlen($new_password) < 6) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Password must be at least 6 characters long.</div>";
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Passwords do not match.</div>";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt_pw = $conn->prepare("UPDATE customers SET password = ? WHERE id = ?");
        $stmt_pw->bind_param("si", $hashed_password, $customer_id);

        if ($stmt_pw->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success d-flex align-items-center'><i class='ri-checkbox-circle-line fs-4 me-2'></i> Customer password updated successfully!</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error updating password: " . htmlspecialchars($stmt_pw->error, ENT_QUOTES, 'UTF-8') . "</div>";
        }
        $stmt_pw->close();
    }
    header("Location: admin-customer-details.php?id=" . $customer_id);
    exit();
}


// 4. Fetch Customer Details and their Assigned Agent
$customer = null;
$stmt = $conn->prepare("SELECT c.*, a.first_name as agent_first_name, a.last_name as agent_last_name 
                        FROM customers c 
                        JOIN agents a ON c.agent_id = a.id 
                        WHERE c.id = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
} else {
    $_SESSION['message'] = "<div class='alert alert-danger'>Customer not found.</div>";
    header("Location: all-customers-loans.php");
    exit();
}
$stmt->close();

// 5. Fetch All Loans for this Customer
$loans = [];
$stmt_loans = $conn->prepare("SELECT * FROM loans WHERE customer_id = ? ORDER BY application_date DESC");
$stmt_loans->bind_param("i", $customer_id);
$stmt_loans->execute();
$loan_result = $stmt_loans->get_result();
if ($loan_result->num_rows > 0) {
    while ($row = $loan_result->fetch_assoc()) {
        $loans[] = $row;
    }
}
$stmt_loans->close();

// 6. Fetch all agents for the reassign dropdown
$all_agents = [];
$agent_result = $conn->query("SELECT id, first_name, last_name FROM agents ORDER BY first_name ASC");
if ($agent_result->num_rows > 0) {
    while ($row = $agent_result->fetch_assoc()) {
        $all_agents[] = $row;
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
                        <div class="col-12">
                            <div class="title-header option-title">
                                <h5>Customer Details</h5>
                            </div>
                            <?php if (!empty($message)) echo $message; ?>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="text-center mb-3">
                                        <?php
                                            $avatar_path = !empty($customer['avatar']) ? '../Agents/upload/customers/avatars/' . $customer['avatar'] : 'assets/images/users/default-avatar.png';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid rounded-circle" alt="Customer Photo" style="width: 100px; height: 100px; object-fit: cover;">
                                        <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                        <small class="text-muted"><?php echo htmlspecialchars($customer['customer_id_string']); ?></small>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item"><strong>Phone:</strong> <?php echo htmlspecialchars($customer['phone']); ?></li>
                                        <li class="list-group-item"><strong>Email:</strong> <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?></li>
                                        <li class="list-group-item"><strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></li>
                                    </ul>
                                </div>
                            </div>
                            <!-- <div class="card">-->
                            <!--    <div class="card-body">-->
                            <!--        <h5 class="card-title mb-3">Assigned Agent</h5>-->
                            <!--        <h6><?php echo htmlspecialchars($customer['agent_first_name'] . ' ' . $customer['agent_last_name']); ?></h6>-->
                            <!--        <button class="btn btn-sm btn-outline-primary mt-2" data-bs-toggle="modal" data-bs-target="#reassignModal">Reassign Agent</button>-->
                            <!--    </div>-->
                            <!--</div>-->

                             <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3">Uploaded Documents</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>Aadhar Card</span>
                                            <?php if (!empty($customer['aadhar_photo'])): ?>
                                                <a href="../Agents/upload/customers/aadhar_cards/<?php echo $customer['aadhar_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View Aadhar</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <span>PAN Card</span>
                                            <?php if (!empty($customer['pan_photo'])): ?>
                                                <a href="../Agents/upload/customers/pan_cards/<?php echo $customer['pan_photo']; ?>" target="_blank" class="btn btn-sm btn-outline-info">View PAN</a>
                                            <?php else: ?>
                                                <span class="text-muted">Not Provided</span>
                                            <?php endif; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title mb-3"><i class="ri-lock-password-line me-1"></i> Reset Password</h5>
                                    <form method="POST" action="admin-customer-details.php?id=<?php echo $customer_id; ?>">
                                        <input type="hidden" name="action" value="reset_password">
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">New Password</label>
                                            <input type="password" name="new_password" class="form-control" placeholder="Enter new password" required minlength="6">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label text-muted small">Confirm Password</label>
                                            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required minlength="6">
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="ri-key-2-line me-1"></i> Update Password</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <div class="card card-table">
                                <div class="card-body">
                                    <h5 class="card-title">Loan History</h5>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Loan Amount</th>
                                                    <th>Total Repayable</th>
                                                    <th>Tenure</th>
                                                    <th>Status</th>
                                                    <th>Details</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($loans)) : ?>
                                                    <tr><td colspan="5" class="text-center text-muted">No loan history found for this customer.</td></tr>
                                                <?php else : ?>
                                                    <?php foreach ($loans as $loan) : ?>
                                                        <tr>
                                                            <td>₹<?php echo number_format($loan['loan_amount']); ?></td>
                                                            <td>₹<?php echo number_format($loan['total_repayable_amount']); ?></td>
                                                            <td><?php echo $loan['tenure'] . ' ' . ucfirst($loan['repayment_cycle']) . 's'; ?></td>
                                                            <td><span class="badge bg-info"><?php echo ucfirst($loan['status']); ?></span></td>
                                                            <td>
                                                                <a href="admin-loan-details.php?id=<?php echo $loan['id']; ?>" title="View Loan Details">
                                                                    <i class="ri-eye-line"></i>
                                                                </a>
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

    <div class="modal fade" id="reassignModal" tabindex="-1" aria-labelledby="reassignModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="admin-customer-details.php?id=<?php echo $customer_id; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="reassignModalLabel">Reassign Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Reassigning <strong><?php echo htmlspecialchars($customer['full_name']); ?></strong> from <?php echo htmlspecialchars($customer['agent_first_name']); ?> to:</p>
                        <select class="form-select" name="new_agent_id" required>
                            <option value="">-- Select New Agent --</option>
                            <?php foreach ($all_agents as $agent): ?>
                                <?php if ($agent['id'] != $customer['agent_id']): ?>
                                    <option value="<?php echo $agent['id']; ?>">
                                        <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Confirm Reassignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>