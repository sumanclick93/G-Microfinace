<?php
// Include config and check for admin login
include('config.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$message = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // --- 1. Sanitize Text Inputs ---
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];

    // --- 2. Auto-Generate a UNIQUE Username (This logic was already correct) ---
    $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name));
    $username = $base_username;
    $stmt_check = $conn->prepare("SELECT id FROM agents WHERE username = ?");
    $stmt_check->bind_param("s", $username);
    $stmt_check->execute();
    $stmt_check->store_result();
    $counter = 1;
    while($stmt_check->num_rows > 0) {
        $username = $base_username . $counter;
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();
        $counter++;
    }
    $stmt_check->close();


    // --- 3. Hash the Password ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // --- 4. Handle File Uploads ---
    function upload_file($file_input_name, $upload_dir) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $file_extension = pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid('', true) . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
                return $new_filename;
            }
        }
        return null;
    }

    $avatar_filename = upload_file('avatar', '../Agents/uploads/avatars/');
    $aadhar_filename = upload_file('aadhar_photo', '../Agents/uploads/aadhar_cards/');
    $pan_filename = upload_file('pan_photo', '../Agents/uploads/pan_cards/');

    // --- 5. Insert Agent into Database ---
    $sql = "INSERT INTO agents (username, password, first_name, last_name, email, phone, address, avatar, aadhar_photo, pan_photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssss", $username, $hashed_password, $first_name, $last_name, $email, $phone, $address, $avatar_filename, $aadhar_filename, $pan_filename, $created_at);

    if ($stmt->execute()) {
        $new_agent_id = $stmt->insert_id;

        // --- 6. Create Agent's Wallet ---
        $wallet_sql = "INSERT INTO agent_wallets (agent_id, balance) VALUES (?, 0.00)";
        $wallet_stmt = $conn->prepare($wallet_sql);
        $wallet_stmt->bind_param("i", $new_agent_id);
        $wallet_stmt->execute();
        
        $message = "<div class='alert alert-success'>New agent created successfully! Their username is: <strong>$username</strong></div>";

        // --- 7. ADDED: JavaScript for Page Refresh ---
        // This script is added to the message and will redirect after 3 seconds
        $message .= "<script>
                        setTimeout(function() {
                            window.location.href = 'add-new-agents.php';
                        }, 3000);
                     </script>";

    } else {
        $message = "<div class='alert alert-danger'>Error creating agent: " . $stmt->error . "</div>";
    }
    $stmt->close();
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
                            <div class="row">
                                <div class="col-sm-10 m-auto">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="title-header option-title">
                                                <h5>Add New Agent</h5>
                                            </div>

                                            <?php if (!empty($message)) echo $message; ?>

                                            <form class="theme-form theme-form-2 mega-form" method="POST" enctype="multipart/form-data">
                                                <div class="row">
                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">First Name</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="text" name="first_name" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Last Name</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="text" name="last_name" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Email Address</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="email" name="email" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Phone Number</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="text" name="phone" required>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Address</label>
                                                        <div class="col-sm-9">
                                                            <textarea class="form-control" name="address" rows="3"></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Password</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="password" name="password" required>
                                                        </div>
                                                    </div>

                                                    <hr>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Profile Photo</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="file" name="avatar">
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">Aadhar Card Photo</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="file" name="aadhar_photo">
                                                        </div>
                                                    </div>

                                                    <div class="mb-4 row align-items-center">
                                                        <label class="form-label-title col-sm-3 mb-0">PAN Card Photo</label>
                                                        <div class="col-sm-9">
                                                            <input class="form-control" type="file" name="pan_photo">
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row">
                                                        <div class="col-sm-9 offset-sm-3">
                                                            <button type="submit" class="btn btn-primary">Create Agent</button>
                                                        </div>
                                                    </div>

                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
</body>
</html>