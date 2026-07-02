<?php
include('config.php');

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$admin_id = $_SESSION['admin_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $payment_upi = $conn->real_escape_string($_POST['payment_upi']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    $update_password = false;
    if (!empty($password)) {
        if ($password === $confirm_password) {
            $update_password = true;
        } else {
            $message = "<div class='alert alert-danger'>Passwords do not match!</div>";
        }
    }

    if (empty($message)) {
        // Build the query and parameters dynamically
        $sql = "UPDATE admins SET first_name=?, last_name=?, phone=?, email=?, payment_upi=?";
        $types = "sssss";
        $params = [$first_name, $last_name, $phone, $email, $payment_upi];
        
        if ($update_password) {
            $sql .= ", password=?";
            $types .= "s";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        // 1. Handle Avatar Upload
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $target_dir = "uploads/avatars/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION);
            $new_filename = "admin_" . $admin_id . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $target_file)) {
                $sql .= ", avatar=?";
                $types .= "s";
                $params[] = $new_filename;
            } else {
                $message = "<div class='alert alert-danger'>Sorry, there was an error uploading your profile photo.</div>";
            }
        }

        // 2. Handle Payment QR Upload
        if (isset($_FILES['payment_qr']) && $_FILES['payment_qr']['error'] == 0) {
            $qr_target_dir = "uploads/qr_codes/";
            // Ensure directory exists
            if (!is_dir($qr_target_dir)) {
                mkdir($qr_target_dir, 0777, true);
            }
            
            $qr_extension = pathinfo($_FILES["payment_qr"]["name"], PATHINFO_EXTENSION);
            $qr_new_filename = "qr_" . $admin_id . "_" . time() . "." . $qr_extension;
            $qr_target_file = $qr_target_dir . $qr_new_filename;

            if (move_uploaded_file($_FILES["payment_qr"]["tmp_name"], $qr_target_file)) {
                $sql .= ", payment_qr=?";
                $types .= "s";
                $params[] = $qr_new_filename;
            } else {
                $message = "<div class='alert alert-danger'>Sorry, there was an error uploading your Payment QR Code.</div>";
            }
        }

        if (empty($message)) {
            $sql .= " WHERE id=?";
            $types .= "i";
            $params[] = $admin_id;

            $stmt = $conn->prepare($sql);
            
            // This is the compatible way to bind params from an array
            $stmt->bind_param($types, ...$params); 

            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>Profile updated successfully!</div>";
            } else {
                $message = "<div class='alert alert-danger'>Error updating profile: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}

// Fetch current admin data
$result = $conn->query("SELECT * FROM admins WHERE id = $admin_id");
$admin = $result->fetch_assoc();
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
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Profile Setting</h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form theme-form-2 mega-form" method="POST" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">First Name</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="text" name="first_name" placeholder="Enter Your First Name" value="<?php echo isset($admin['first_name']) ? htmlspecialchars($admin['first_name']) : ''; ?>">
                                                </div>
                                            </div>

                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">Last Name</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="text" name="last_name" placeholder="Enter Your Last Name" value="<?php echo isset($admin['last_name']) ? htmlspecialchars($admin['last_name']) : ''; ?>">
                                                </div>
                                            </div>

                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">Your Phone Number</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="text" name="phone" placeholder="Enter Your Number" value="<?php echo isset($admin['phone']) ? htmlspecialchars($admin['phone']) : ''; ?>">
                                                </div>
                                            </div>

                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">Email Address</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="email" name="email" placeholder="Enter Your Email Address" value="<?php echo isset($admin['email']) ? htmlspecialchars($admin['email']) : ''; ?>">
                                                </div>
                                            </div>

                                            <div class="mb-4 row align-items-center">
                                                <label class="col-sm-2 col-form-label form-label-title">Profile Photo</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control form-choose" type="file" name="avatar" accept="image/*">
                                                    <small class="form-text text-muted">Current photo: <?php echo isset($admin['avatar']) && !empty($admin['avatar']) ? htmlspecialchars($admin['avatar']) : 'None'; ?></small>
                                                </div>
                                            </div>

                                            <div class="mb-4 row align-items-center">
                                                <label class="col-sm-2 col-form-label form-label-title">Master Payment QR</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control form-choose" type="file" name="payment_qr" accept="image/*">
                                                    <small class="form-text text-muted">This QR code will be displayed in the Customer App for direct payments.</small>
                                                    
                                                    <?php if(!empty($admin['payment_qr'])): ?>
                                                        <div class="mt-3">
                                                            <p class="mb-1 fw-bold" style="font-size: 13px;">Current QR Code:</p>
                                                            <img src="uploads/qr_codes/<?php echo htmlspecialchars($admin['payment_qr']); ?>" alt="Payment QR" style="max-height: 120px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">Payment Upi</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="text" name="payment_upi" placeholder="Enter Your UPI Address" value="<?php echo isset($admin['payment_upi']) ? htmlspecialchars($admin['payment_upi']) : ''; ?>">
                                                </div>
                                            </div>
                                            <hr>
                                            <p class="text-muted">Only fill in password fields if you want to change the password.</p>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">New Password</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="password" name="password" placeholder="Enter New Password">
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-2 mb-0">Confirm Password</label>
                                                <div class="col-sm-10">
                                                    <input class="form-control" type="password" name="confirm_password" placeholder="Confirm New Password">
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-sm-10 offset-sm-2">
                                                     <button type="submit" class="btn btn-primary">Update Profile</button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="container-fluid">
                    <footer class="footer">
                        <div class="row">
                            <div class="col-md-12 footer-copyright text-center">
                                <p class="mb-0">Copyright 2025 © Gmicrofinancefoundation</p>
                            </div>
                        </div>
                    </footer>
                </div>
                </div>
            </div>
        </div>
        <?php include('footer.php'); ?>
    </div>
</body>
</html>