<?php
// Include the config file from the Super admin directory
include('config.php');

// 1. Authentication Check for AGENT
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];
$message = '';

// 2. Handle Form Submission to UPDATE agent details
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize text inputs
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Get current filenames from the DB to manage deletions
    $stmt_get_files = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM agents WHERE id = ?");
    $stmt_get_files->bind_param("i", $agent_id);
    $stmt_get_files->execute();
    $current_files = $stmt_get_files->get_result()->fetch_assoc();
    $stmt_get_files->close();

    // Handle password update
    $update_password = false;
    if (!empty($password)) {
        if ($password === $confirm_password) {
            $update_password = true;
        } else {
            $message = "<div class='alert alert-danger'>Passwords do not match!</div>";
        }
    }

    // Reusable function to handle file uploads
    function handle_upload($file_input_name, $upload_dir, $current_filename) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $file_extension = pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid('', true) . '.' . $file_extension;
            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $upload_dir . $new_filename)) {
                if ($current_filename && file_exists($upload_dir . $current_filename)) {
                    unlink($upload_dir . $current_filename);
                }
                return $new_filename;
            }
        }
        return $current_filename; // No new file, so keep the old one
    }

    $avatar_filename = handle_upload('avatar', 'uploads/avatars/', $current_files['avatar']);
    $aadhar_filename = handle_upload('aadhar_photo', 'uploads/aadhar_cards/', $current_files['aadhar_photo']);
    $pan_filename = handle_upload('pan_photo', 'uploads/pan_cards/', $current_files['pan_photo']);

    // Build and execute the final query if no errors
    if (empty($message)) {
        $sql = "UPDATE agents SET first_name=?, last_name=?, phone=?, email=?, address=?, avatar=?, aadhar_photo=?, pan_photo=?";
        $types = "ssssssss";
        $params = [$first_name, $last_name, $phone, $email, $address, $avatar_filename, $aadhar_filename, $pan_filename];
        
        if ($update_password) {
            $sql .= ", password=?";
            $types .= "s";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id=?";
        $types .= "i";
        $params[] = $agent_id;

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Profile updated successfully! Refreshing...</div>";
            $message .= "<script>setTimeout(() => window.location.reload(), 2000);</script>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating profile: " . $stmt->error . "</div>";
        }
        $stmt->close();
    }
}

// 3. Fetch current agent data to display in the form
$result = $conn->query("SELECT * FROM agents WHERE id = $agent_id");
$agent = $result->fetch_assoc();
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
                                    <form class="theme-form theme-form-2 mega-form" method="POST" enctype="multipart/form-data" action="profile-setting.php">
                                        <div class="row">
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">First Name</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="first_name" value="<?php echo htmlspecialchars($agent['first_name'] ?? ''); ?>"></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Last Name</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="last_name" value="<?php echo htmlspecialchars($agent['last_name'] ?? ''); ?>"></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Phone Number</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="phone" value="<?php echo htmlspecialchars($agent['phone'] ?? ''); ?>"></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Email Address</label>
                                                <div class="col-sm-9"><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($agent['email'] ?? ''); ?>"></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Address</label>
                                                <div class="col-sm-9"><textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($agent['address'] ?? ''); ?></textarea></div>
                                            </div>

                                            <hr class="my-3">

                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Profile Photo</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="avatar">
                                                    <?php if(!empty($agent['avatar'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="uploads/avatars/<?php echo $agent['avatar']; ?>" target="_blank"><?php echo $agent['avatar']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Aadhar Card</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="aadhar_photo">
                                                    <?php if(!empty($agent['aadhar_photo'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="uploads/aadhar_cards/<?php echo $agent['aadhar_photo']; ?>" target="_blank"><?php echo $agent['aadhar_photo']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">PAN Card</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="pan_photo">
                                                    <?php if(!empty($agent['pan_photo'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="uploads/pan_cards/<?php echo $agent['pan_photo']; ?>" target="_blank"><?php echo $agent['pan_photo']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <hr class="my-3">
                                            
                                            <p class="text-muted">Only fill in password fields below if you want to change your password.</p>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">New Password</label>
                                                <div class="col-sm-9"><input class="form-control" type="password" name="password" placeholder="Enter New Password"></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Confirm Password</label>
                                                <div class="col-sm-9"><input class="form-control" type="password" name="confirm_password" placeholder="Confirm New Password"></div>
                                            </div>

                                            <div class="mt-2">
                                                <button type="submit" class="btn btn-primary w-100">Update Profile</button>
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
        <?php include('footer.php'); ?>
    </div>
</body>
</html>