<?php
// Include config and check for admin login
include('config.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// --- THIS IS THE NEW PART ---
// Check if a flash message exists from a redirect (e.g., after an update)
if (isset($_SESSION['message'])) {
    // Store the message in a local variable
    $message = $_SESSION['message'];
    // Unset the session variable so it doesn't show again on refresh
    unset($_SESSION['message']);
}

// --- Handle Agent Deletion ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $agent_id_to_delete = (int)$_POST['delete_id'];
    $reassign_agent_id = isset($_POST['reassign_agent_id']) ? (int)$_POST['reassign_agent_id'] : 0;

    // 1. Check if the agent has customers with active loans or RDs
    $stmt_active_loans = $conn->prepare("
        SELECT COUNT(l.id) as count 
        FROM loans l 
        JOIN customers c ON l.customer_id = c.id 
        WHERE c.agent_id = ? AND l.status IN ('approved', 'active')
    ");
    $stmt_active_loans->bind_param("i", $agent_id_to_delete);
    $stmt_active_loans->execute();
    $active_loans_count = $stmt_active_loans->get_result()->fetch_assoc()['count'];
    $stmt_active_loans->close();

    $stmt_active_rds = $conn->prepare("
        SELECT COUNT(rd.id) as count 
        FROM recurring_deposits rd 
        JOIN customers c ON rd.customer_id = c.id 
        WHERE c.agent_id = ? AND rd.status = 'active'
    ");
    $stmt_active_rds->bind_param("i", $agent_id_to_delete);
    $stmt_active_rds->execute();
    $active_rds_count = $stmt_active_rds->get_result()->fetch_assoc()['count'];
    $stmt_active_rds->close();

    if ($active_loans_count > 0 || $active_rds_count > 0) {
        $message = "<div class='alert alert-danger'>Cannot delete agent. This agent's customers have active loans ($active_loans_count) or recurring deposits ($active_rds_count).</div>";
    } else {
        // 2. Check if the agent has any customers
        $stmt_cust_count = $conn->prepare("SELECT COUNT(id) as count FROM customers WHERE agent_id = ?");
        $stmt_cust_count->bind_param("i", $agent_id_to_delete);
        $stmt_cust_count->execute();
        $customer_count = $stmt_cust_count->get_result()->fetch_assoc()['count'];
        $stmt_cust_count->close();

        if ($customer_count > 0 && $reassign_agent_id <= 0) {
            $message = "<div class='alert alert-danger'>Cannot delete agent. Please select another agent to reassign the $customer_count customer(s) to.</div>";
        } else {
            // Start transaction
            $conn->begin_transaction();
            try {
                // A. Reassign customers if any exist
                if ($customer_count > 0) {
                    $stmt_reassign = $conn->prepare("UPDATE customers SET agent_id = ? WHERE agent_id = ?");
                    $stmt_reassign->bind_param("ii", $reassign_agent_id, $agent_id_to_delete);
                    $stmt_reassign->execute();
                    $stmt_reassign->close();
                }

                // B. Soft-delete the agent: set is_active = 0, clear credentials, and free username
                $stmt_username = $conn->prepare("SELECT username FROM agents WHERE id = ?");
                $stmt_username->bind_param("i", $agent_id_to_delete);
                $stmt_username->execute();
                $orig_username = $stmt_username->get_result()->fetch_assoc()['username'] ?? '';
                $stmt_username->close();

                $new_username = $orig_username . '_deleted_' . $agent_id_to_delete;
                $stmt_soft_delete = $conn->prepare("
                    UPDATE agents 
                    SET is_active = 0, username = ?, password = '', phone = NULL, email = NULL 
                    WHERE id = ?
                ");
                $stmt_soft_delete->bind_param("si", $new_username, $agent_id_to_delete);
                $stmt_soft_delete->execute();
                $stmt_soft_delete->close();

                $conn->commit();
                $message = "<div class='alert alert-success'>Agent deactivated and customer(s) reassigned successfully.</div>";
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<div class='alert alert-danger'>Error processing agent deletion: " . $e->getMessage() . "</div>";
            }
        }
    }
}

// --- Fetch All Active Agents from Database along with Loan and RD Summary Stats ---
$agents = [];
$sql = "SELECT id, username, first_name, last_name, phone, email, avatar FROM agents WHERE is_active = 1 ORDER BY id DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    // Prepare statement to fetch loan stats for each agent
    $stmt_loan_stats = $conn->prepare("
        SELECT 
            COALESCE(SUM(l.loan_amount), 0) as total_principal,
            COALESCE(SUM(l.total_repayable_amount), 0) as total_repayable,
            COALESCE(SUM(
                (SELECT COALESCE(SUM(p.amount_paid), 0) FROM payments p WHERE p.loan_id = l.id AND p.status != 'rejected')
            ), 0) as total_collected,
            COALESCE(SUM(
                CASE WHEN LOWER(TRIM(l.status)) NOT IN ('paid', 'rejected', 'closed', 'settled') 
                     THEN GREATEST(0, l.total_repayable_amount - COALESCE((SELECT SUM(p.amount_paid) FROM payments p WHERE p.loan_id = l.id AND p.status != 'rejected'), 0))
                     ELSE 0 
                END
            ), 0) as total_pending
        FROM loans l
        WHERE l.agent_id = ? AND LOWER(TRIM(l.status)) != 'rejected'
    ");

    // Prepare statement to fetch RD stats for each agent
    $stmt_rd_stats = $conn->prepare("
        SELECT 
            COALESCE(SUM(rd.deposit_amount * rd.tenure), 0) as total_deposit,
            COALESCE(SUM(rd.maturity_amount), 0) as total_maturity,
            COALESCE(SUM(
                (SELECT COALESCE(SUM(rp.amount_paid), 0) FROM rd_payments rp WHERE rp.rd_id = rd.id AND rp.status != 'rejected')
            ), 0) as total_collected,
            COALESCE(SUM(
                CASE WHEN LOWER(TRIM(rd.status)) NOT IN ('matured', 'closed', 'premature-closed', 'settled', 'rejected') 
                     THEN GREATEST(0, (rd.deposit_amount * rd.tenure) - COALESCE((SELECT SUM(rp.amount_paid) FROM rd_payments rp WHERE rp.rd_id = rd.id AND rp.status != 'rejected'), 0))
                     ELSE 0 
                END
            ), 0) as total_pending
        FROM recurring_deposits rd
        WHERE rd.agent_id = ? AND LOWER(TRIM(rd.status)) != 'rejected'
    ");

    while ($row = $result->fetch_assoc()) {
        $aid = (int)$row['id'];
        
        $loan_stats = ['total' => 0.0, 'collected' => 0.0, 'pending' => 0.0];
        $rd_stats = ['total' => 0.0, 'collected' => 0.0, 'pending' => 0.0];

        try {
            if ($stmt_loan_stats) {
                $stmt_loan_stats->bind_param("i", $aid);
                $stmt_loan_stats->execute();
                $l_res = $stmt_loan_stats->get_result()->fetch_assoc();
                if ($l_res) {
                    $loan_stats['total'] = (float)$l_res['total_repayable'];
                    $loan_stats['collected'] = (float)$l_res['total_collected'];
                    $loan_stats['pending'] = (float)$l_res['total_pending'];
                }
            }
        } catch (Throwable $e) {
            // Safe fallback to 0
        }

        try {
            if ($stmt_rd_stats) {
                $stmt_rd_stats->bind_param("i", $aid);
                $stmt_rd_stats->execute();
                $r_res = $stmt_rd_stats->get_result()->fetch_assoc();
                if ($r_res) {
                    $rd_stats['total'] = (float)$r_res['total_deposit'];
                    $rd_stats['collected'] = (float)$r_res['total_collected'];
                    $rd_stats['pending'] = (float)$r_res['total_pending'];
                }
            }
        } catch (Throwable $e) {
            // Safe fallback to 0
        }

        $row['loan_stats'] = $loan_stats;
        $row['rd_stats'] = $rd_stats;

        $agents[] = $row;
    }

    if ($stmt_loan_stats) $stmt_loan_stats->close();
    if ($stmt_rd_stats) $stmt_rd_stats->close();
}

// Fetch list of active agents for reassignment dropdown
$reassign_agents = [];
$ra_result = $conn->query("SELECT id, first_name, last_name, username FROM agents WHERE is_active = 1 ORDER BY first_name ASC");
if ($ra_result && $ra_result->num_rows > 0) {
    while ($row = $ra_result->fetch_assoc()) {
        $reassign_agents[] = $row;
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
                        <div class="col-sm-12">
                            <div class="card card-table">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>All Agents</h5>
                                        <a href="add-new-agents.php" class="align-items-center btn btn-theme d-flex">
                                            <i data-feather="plus"></i>Add New Agent
                                        </a>
                                    </div>
                                    
                                    <?php if (!empty($message)) echo $message; ?>

                                    <div class="table-responsive table-product">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Name & Username</th>
                                                    <th>Phone</th>
                                                    <th>Email</th>
                                                    <th style="min-width: 180px;">Loan Summary</th>
                                                    <th style="min-width: 180px;">RD Summary</th>
                                                    <th>Option</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($agents as $agent) : ?>
                                                    <tr>
                                                        <td>
                                                            <div class="table-image">
                                                                <?php
                                                                // Construct the correct path to the agent's avatar
                                                                $avatar_path = !empty($agent['avatar']) ? '../Agents/uploads/avatars/' . $agent['avatar'] : 'assets/images/users/default-avatar.png';
                                                                ?>
                                                                <img src="<?php echo htmlspecialchars($avatar_path); ?>" class="img-fluid" alt="Agent Avatar">
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="user-name">
                                                                <span><?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></span>
                                                                <span>(<?php echo htmlspecialchars($agent['username']); ?>)</span>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($agent['phone']); ?></td>
                                                        <td><?php echo htmlspecialchars($agent['email']); ?></td>
                                                        <td style="min-width: 180px;">
                                                            <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 8px 10px; font-size: 12.5px;">
                                                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px; border-bottom: 1px dashed #ddd; padding-bottom: 4px;">
                                                                    <span style="color: #555; font-weight: 500;">Total Loan:</span>
                                                                    <span style="font-weight: 700; color: #333;">₹<?php echo number_format($agent['loan_stats']['total'] ?? 0, 2); ?></span>
                                                                </div>
                                                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                                    <span style="color: #28a745; font-weight: 500;">Collected:</span>
                                                                    <span style="font-weight: 700; color: #28a745;">₹<?php echo number_format($agent['loan_stats']['collected'] ?? 0, 2); ?></span>
                                                                </div>
                                                                <div style="display: flex; justify-content: space-between;">
                                                                    <span style="color: #dc3545; font-weight: 500;">Pending:</span>
                                                                    <span style="font-weight: 700; color: #dc3545;">₹<?php echo number_format($agent['loan_stats']['pending'] ?? 0, 2); ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td style="min-width: 180px;">
                                                            <div style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; padding: 8px 10px; font-size: 12.5px;">
                                                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px; border-bottom: 1px dashed #ddd; padding-bottom: 4px;">
                                                                    <span style="color: #555; font-weight: 500;">Total RD:</span>
                                                                    <span style="font-weight: 700; color: #333;">₹<?php echo number_format($agent['rd_stats']['total'] ?? 0, 2); ?></span>
                                                                </div>
                                                                <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                                                    <span style="color: #28a745; font-weight: 500;">Collected:</span>
                                                                    <span style="font-weight: 700; color: #28a745;">₹<?php echo number_format($agent['rd_stats']['collected'] ?? 0, 2); ?></span>
                                                                </div>
                                                                <div style="display: flex; justify-content: space-between;">
                                                                    <span style="color: #dc3545; font-weight: 500;">Pending:</span>
                                                                    <span style="font-weight: 700; color: #dc3545;">₹<?php echo number_format($agent['rd_stats']['pending'] ?? 0, 2); ?></span>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <ul>
                                                                <li>
                                                                    <a href="agent-wallet.php?id=<?php echo $agent['id']; ?>" title="Agent Wallet">
                                                                        <i class="ri-wallet-3-line"></i>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="all-loans.php?agent_id=<?php echo $agent['id']; ?>" title="View Agent Loans & Payment History">
                                                                        <i class="ri-hand-coin-line text-primary"></i>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="all-rds.php?agent_id=<?php echo $agent['id']; ?>" title="View Agent RDs & Payment History">
                                                                        <i class="ri-safe-2-line text-success"></i>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="edit-agent.php?id=<?php echo $agent['id']; ?>">
                                                                        <i class="ri-pencil-line"></i>
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a href="javascript:void(0)" class="delete-agent-btn" data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalToggle" data-id="<?php echo $agent['id']; ?>">
                                                                        <i class="ri-delete-bin-line"></i>
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                <?php if (empty($agents)) : ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center">No agents found.</td>
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
    </div>

    <div class="modal fade theme-modal remove-coupon" id="exampleModalToggle" aria-hidden="true" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header d-block text-center">
                    <h5 class="modal-title w-100" id="exampleModalLabel22">Deactivate Agent</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST" action="all-agents.php">
                    <div class="modal-body">
                        <div class="remove-box text-center">
                            <p class="mb-3">This agent will be deactivated. They will not be able to log in or manage accounts, but their name will be preserved on historical records.</p>
                        </div>
                        <div class="mb-3">
                            <label for="reassignAgentId" class="form-label font-bold">Transfer existing customers to active agent:</label>
                            <select name="reassign_agent_id" id="reassignAgentId" class="form-select" required>
                                <option value="">-- Select Agent --</option>
                                <?php foreach ($reassign_agents as $ra): ?>
                                    <option value="<?php echo $ra['id']; ?>"><?php echo htmlspecialchars($ra['first_name'] . ' ' . $ra['last_name'] . ' (' . $ra['username'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">All customers assigned to the deactivated agent will be transferred to the selected agent.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="delete_id" id="agentIdToDelete" value="">
                        <button type="button" class="btn btn-animation btn-md fw-bold" data-bs-dismiss="modal">No</button>
                        <button type="submit" class="btn btn-animation btn-md fw-bold">Yes, Deactivate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include('footer.php'); ?>
    
    <script>
    $(document).ready(function() {
        $('.delete-agent-btn').on('click', function() {
            var agentId = $(this).data('id');
            $('#agentIdToDelete').val(agentId);
            
            // Show all options, then hide/disable the current agent being deleted
            $('#reassignAgentId option').show().prop('disabled', false);
            $('#reassignAgentId option[value="' + agentId + '"]').hide().prop('disabled', true);
            $('#reassignAgentId').val(''); // Reset dropdown selection
        });
    });
    </script>
</body>
</html>