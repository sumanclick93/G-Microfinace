<?php
include('config.php');

// Check for admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Check if an agent ID was passed in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "<script>alert('No Agent Selected!'); window.location.href='agent-financials.php';</script>";
    exit();
}

$agent_id = intval($_GET['id']);

// Fetch the agent's details for the page header
$agent_query = "SELECT first_name, last_name, username FROM agents WHERE id = $agent_id";
$agent_result = $conn->query($agent_query);

if ($agent_result->num_rows == 0) {
    echo "<script>alert('Agent not found!'); window.location.href='agent-financials.php';</script>";
    exit();
}

$agent_data = $agent_result->fetch_assoc();
$agent_name = $agent_data['first_name'] . ' ' . $agent_data['last_name'] . ' (' . $agent_data['username'] . ')';

// Fetch the wallet logs for this specific agent
$log_query = "SELECT transaction_type, amount, description, transaction_date 
              FROM wallet_transactions 
              WHERE agent_id = $agent_id 
              ORDER BY id DESC";
$log_result = $conn->query($log_query);
?>

<!DOCTYPE html>
<html lang="en">
<?php include('head.php'); ?>

<body>
    <div class="tap-top"><span class="lnr lnr-chevron-up"></span></div>

    <div class="page-wrapper compact-wrapper" id="pageWrapper">
        <?php include('header.php'); ?>

        <div class="page-body-wrapper">
            <?php include('sidebaar.php'); ?>

            <div class="page-body">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                                    <div class="card-header-title">
                                        <h4>Wallet Logs: <span style="color: #ff8b3d;"><?php echo htmlspecialchars($agent_name); ?></span></h4>
                                    </div>
                                    <a href="agent-financials.php" class="btn btn-primary btn-sm">
                                        <i class="ri-arrow-left-line"></i> Back to Financials
                                    </a>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive category-table">
                                        <table class="table all-package theme-table" id="table_id">
                                            <thead>
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>Transaction Type</th>
                                                    <th>Description</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                if ($log_result->num_rows > 0) {
                                                    while ($row = $log_result->fetch_assoc()) {
                                                        // Determine color based on transaction type
                                                        $type = $row['transaction_type'];
                                                        $is_credit = in_array($type, ['recharge', 'emi-received', 'rd-received']);
                                                        
                                                        $text_color = $is_credit ? '#28a745' : '#dc3545'; // Green for in, Red for out
                                                        $sign = $is_credit ? '+' : '-';
                                                        
                                                        // Format the date nicely
                                                        $formatted_date = date('d M Y, h:i A', strtotime($row['transaction_date']));
                                                ?>
                                                    <tr>
                                                        <td><?php echo $formatted_date; ?></td>
                                                        <td>
                                                            <span style="font-weight: 600; text-transform: uppercase; font-size: 12px; color: <?php echo $text_color; ?>;">
                                                                <?php echo str_replace('-', ' ', $type); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                        <td style="font-weight: bold; color: <?php echo $text_color; ?>;">
                                                            <?php echo $sign; ?> ₹<?php echo number_format($row['amount'], 2); ?>
                                                        </td>
                                                    </tr>
                                                <?php 
                                                    } 
                                                } else {
                                                    echo "<tr><td colspan='4' class='text-center'>No transactions found for this agent.</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('footer.php'); ?>
            </div>
        </div>
    </div>
</body>
</html>