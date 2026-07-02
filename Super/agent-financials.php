<?php
include('config.php');

// Check for admin login
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

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
                                <div class="card-header border-0">
                                    <div class="card-header-title">
                                        <h4>Agent Financial Overview</h4>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="table-responsive category-table">
    <form action="process-wallet-transaction.php" method="POST">
        <table class="table all-package theme-table" id="table_id">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Agent Name</th>
                    <th>Wallet Balance</th>
                    <th>Total Loans</th>
                    <th>Total RD</th>
                    <th>Action (Select to Process)</th> </tr>
            </thead>
            <tbody>
                <?php 
                $query = "SELECT a.id,a.username,a.first_name, a.last_name,IFNULL(aw.balance, 0.00) as wallet_balance,(SELECT IFNULL(SUM(loan_amount), 0) FROM loans WHERE agent_id = a.id) as total_loans,
                                                            (SELECT IFNULL(SUM(deposit_amount), 0) FROM recurring_deposits WHERE agent_id = a.id) as total_rd FROM agents a LEFT JOIN agent_wallets aw ON a.id = aw.agent_id GROUP BY a.id ORDER BY a.id DESC";
                                                    
                                                    $result = $conn->query($query);
                                                if ($result->num_rows > 0) {
                                                    while($row = $result->fetch_assoc()) { 
                ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <?php echo $row['first_name'] . ' ' . $row['last_name']; ?><br>
                            <small class="text-muted">(<?php echo $row['username']; ?>)</small>
                        </td>
                        <td class="td-price">₹<?php echo number_format($row['wallet_balance'], 2); ?></td>
                        <td class="text-danger">₹<?php echo number_format($row['total_loans'], 2); ?></td>
                        <td class="text-success">₹<?php echo number_format($row['total_rd'], 2); ?></td>
                        
                        <td>
    <div style="display: flex; align-items: center; gap: 12px;">
        
        <input type="checkbox" name="selected_agents[]" value="<?php echo $row['id']; ?>" id="chk_<?php echo $row['id']; ?>" style="display: none;">
        
        <input type="hidden" name="transaction_types[<?php echo $row['id']; ?>]" value="deposit">
        
        <div class="form-check form-switch" style="margin: 0; padding: 0; display: flex; align-items: center; gap: 5px;">
            <input class="form-check-input" type="checkbox" role="switch" 
                   name="transaction_types[<?php echo $row['id']; ?>]" 
                   value="withdraw" 
                   id="toggle_<?php echo $row['id']; ?>"
                   onchange="toggleMode(<?php echo $row['id']; ?>)"
                   style="cursor: pointer; width: 35px; height: 18px; margin: 0;">
            <label class="form-check-label" for="toggle_<?php echo $row['id']; ?>" id="label_<?php echo $row['id']; ?>" style="font-weight: bold; color: #28a745; min-width: 35px; cursor: pointer; margin-bottom: 0;">Dep</label>
        </div>

        <div class="input-group input-group-sm" style="width: 140px; flex-wrap: nowrap;">
            <input type="number" name="amounts[<?php echo $row['id']; ?>]" id="amount_<?php echo $row['id']; ?>" 
                   class="form-control" placeholder="Amount" step="0.01" min="0"
                   oninput="checkAmount(<?php echo $row['id']; ?>)">
            <button class="btn btn-outline-info" type="button" id="full_btn_<?php echo $row['id']; ?>" 
        style="display: none; padding: 2px 8px; font-weight: bold; font-size: 11px; background-color: #e0f7fa; color: #00838f;" 
        onclick="setFullAmount(<?php echo $row['id']; ?>, '<?php echo isset($row['wallet_balance']) ? $row['wallet_balance'] : (isset($wallet_balance) ? $wallet_balance : 0); ?>')">FULL</button>
        </div>

        <a href="agent-wallet-log.php?id=<?php echo $row['id']; ?>" class="btn btn-sm" style="background-color: #ff8b3d; color: white; padding: 4px 8px;" title="View Logs">
            <i class="ri-history-line" style="font-size: 16px;"></i>
        </a>
    </div>
</td>
                    </tr>
                <?php 
                    } 
                } else {
                    echo "<tr><td colspan='6' class='text-center'>No Agents Found</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <div class="text-end mt-3 mb-3" style="padding-right: 20px;">
            <button type="submit" class="btn btn-primary" style="background-color: #007bff; border-color: #007bff;">
                Submit Selected Transactions
            </button>
        </div>
    </form>
    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include('footer.php'); ?>
                <script>
    // 1. Handle Toggle Switch UI
    function toggleMode(id) {
        var toggle = document.getElementById('toggle_' + id);
        var label = document.getElementById('label_' + id);
        var fullBtn = document.getElementById('full_btn_' + id);

        if (toggle.checked) {
            // Switch to Withdrawal Mode
            label.innerText = "Wdl";
            label.style.color = "#dc3545"; // Red color
            fullBtn.style.display = "inline-block"; // Show FULL button
        } else {
            // Switch to Deposit Mode
            label.innerText = "Dep";
            label.style.color = "#28a745"; // Green color
            fullBtn.style.display = "none"; // Hide FULL button
        }
    }

    // 2. Handle "FULL" Button Click
    function setFullAmount(id, maxBalance) {
        var amountInput = document.getElementById('amount_' + id);
        
        // Ensure it is treated as a clean decimal number
        var cleanBalance = parseFloat(maxBalance);
        if (isNaN(cleanBalance)) { cleanBalance = 0; }
        
        amountInput.value = cleanBalance.toFixed(2); 
        
        // Manually trigger the input event so the hidden checkbox gets checked
        amountInput.dispatchEvent(new Event('input'));
    }

    // 3. Auto-Select Row if Amount is Entered
    function checkAmount(id) {
        var amountInput = document.getElementById('amount_' + id);
        var hiddenCheck = document.getElementById('chk_' + id);
        
        // If they type a number greater than 0, silently check the hidden box for submission
        if (parseFloat(amountInput.value) > 0) {
            hiddenCheck.checked = true;
        } else {
            hiddenCheck.checked = false;
        }
    }
</script>
            </div>
        </div>
    </div>
</body>
</html>