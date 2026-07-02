<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

// 2. Fetch Admin's Payment QR and UPI details
$admin_payment = null;
$admin_query = $conn->query("SELECT payment_qr, payment_upi FROM admins LIMIT 1");
if ($admin_query && $admin_query->num_rows > 0) {
    $admin_payment = $admin_query->fetch_assoc();
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
                        <div class="col-sm-8 m-auto">
                            <div class="card">
                                <div class="card-body text-center p-4">
                                    <h4 class="card-title mb-4 font-bold text-dark">Payment Details</h4>
                                    <p class="text-muted mb-4">Scan the QR code or copy the UPI address below to make payments to the Admin.</p>

                                    <!-- QR Code Display -->
                                    <div class="qr-container mb-4">
                                        <?php if (!empty($admin_payment['payment_qr'])): ?>
                                            <div class="d-inline-block p-3 bg-white border rounded shadow-sm">
                                                <img src="../Super/uploads/qr_codes/<?php echo htmlspecialchars($admin_payment['payment_qr']); ?>" alt="Payment QR Code" class="img-fluid" style="max-height: 250px; border-radius: 5px;">
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info d-inline-block" role="alert">
                                                <i class="ri-information-line me-2"></i> No Payment QR code has been uploaded by the admin.
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- UPI Display -->
                                    <div class="col-md-8 m-auto">
                                        <div class="form-group mb-0">
                                            <label class="form-label font-bold text-dark mb-2">Admin UPI Address</label>
                                            <?php if (!empty($admin_payment['payment_upi'])): ?>
                                                <div class="input-group">
                                                    <input type="text" class="form-control text-center font-bold" id="upiId" value="<?php echo htmlspecialchars($admin_payment['payment_upi']); ?>" readonly style="background-color: #f8f9fa;">
                                                    <button class="btn btn-primary" type="button" onclick="copyUpi()" id="copyBtn">
                                                        <i class="ri-file-copy-line me-1"></i> Copy
                                                    </button>
                                                </div>
                                                <div id="copyAlert" class="text-success mt-2 font-bold" style="display: none; font-size: 0.9rem;">
                                                    <i class="ri-checkbox-circle-line me-1"></i> UPI ID copied to clipboard!
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning mb-0" role="alert">
                                                    <i class="ri-alert-line me-2"></i> No UPI address has been saved by the admin.
                                                </div>
                                            <?php endif; ?>
                                        </div>
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

    <script>
    function copyUpi() {
        const copyText = document.getElementById("upiId");
        
        // Select the text field
        copyText.select();
        copyText.setSelectionRange(0, 99999); // For mobile devices

        // Copy the text inside the text field
        navigator.clipboard.writeText(copyText.value).then(() => {
            // Show the success text
            const alertDiv = document.getElementById("copyAlert");
            alertDiv.style.display = "block";
            
            // Hide the success text after 3 seconds
            setTimeout(() => {
                alertDiv.style.display = "none";
            }, 3000);
        }).catch(err => {
            console.error("Failed to copy text: ", err);
        });
    }
    </script>
</body>
</html>
