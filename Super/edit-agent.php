<?php
// Include config and check for admin login
include('config.php');
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Check if an agent ID is provided in the URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-agents.php");
    exit();
}
$agent_id = $_GET['id'];
$message = '';

// --- Handle Form Submission for UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- 1. Sanitize Text Inputs ---
    $first_name = $conn->real_escape_string($_POST['first_name']);
    $last_name = $conn->real_escape_string($_POST['last_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];

    // --- 2. Fetch current filenames to manage deletions ---
    $stmt_get_files = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM agents WHERE id = ?");
    $stmt_get_files->bind_param("i", $agent_id);
    $stmt_get_files->execute();
    $current_files = $stmt_get_files->get_result()->fetch_assoc();
    $stmt_get_files->close();

    // --- 3. Handle File Uploads ---
    function handle_upload($file_input_name, $upload_dir, $current_filename) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            // A new file is being uploaded
            $file_extension = pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid('', true) . '.' . $file_extension;
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $target_file)) {
                // New file uploaded successfully, so delete the old one if it exists
                if ($current_filename && file_exists($upload_dir . $current_filename)) {
                    unlink($upload_dir . $current_filename);
                }
                return $new_filename; // Return the new filename
            }
        }
        return $current_filename; // Return the old filename if no new file was uploaded
    }

    $avatar_filename = handle_upload('avatar', '../Agents/uploads/avatars/', $current_files['avatar']);
    $aadhar_filename = handle_upload('aadhar_photo', '../Agents/uploads/aadhar_cards/', $current_files['aadhar_photo']);
    $pan_filename = handle_upload('pan_photo', '../Agents/uploads/pan_cards/', $current_files['pan_photo']);

    // --- 4. Build and Execute the UPDATE Query ---
    $sql = "UPDATE agents SET first_name=?, last_name=?, email=?, phone=?, address=?, avatar=?, aadhar_photo=?, pan_photo=?";
    $types = "ssssssss";
    $params = [$first_name, $last_name, $email, $phone, $address, $avatar_filename, $aadhar_filename, $pan_filename];

    if (!empty($password)) {
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
        $_SESSION['message'] = "<div class='alert alert-success'>Agent details updated successfully!</div>";
        header("Location: all-agents.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error updating agent: " . $stmt->error . "</div>";
    }
    $stmt->close();
}


// --- Fetch Current Agent Data to Pre-fill the Form ---
$agent = null;
$stmt = $conn->prepare("SELECT * FROM agents WHERE id = ?");
$stmt->bind_param("i", $agent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $agent = $result->fetch_assoc();
} else {
    // Agent not found, redirect back
    header("Location: all-agents.php");
    exit();
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
                        <div class="col-sm-10 m-auto">
                            <div class="card">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Edit Agent: <?php echo htmlspecialchars($agent['first_name'] . ' ' . $agent['last_name']); ?></h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>

                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="edit-agent.php?id=<?php echo $agent_id; ?>" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">First Name</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="first_name" value="<?php echo htmlspecialchars($agent['first_name']); ?>" required></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Last Name</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="last_name" value="<?php echo htmlspecialchars($agent['last_name']); ?>" required></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Email Address</label>
                                                <div class="col-sm-9"><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($agent['email']); ?>" required></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Phone Number</label>
                                                <div class="col-sm-9"><input class="form-control" type="text" name="phone" value="<?php echo htmlspecialchars($agent['phone']); ?>" required></div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Address</label>
                                                <div class="col-sm-9"><textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($agent['address']); ?></textarea></div>
                                            </div>

                                            <hr>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Profile Photo</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="avatar">
                                                    <?php if(!empty($agent['avatar'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="../Agents/uploads/avatars/<?php echo $agent['avatar']; ?>" target="_blank"><?php echo $agent['avatar']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">Aadhar Card Photo</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="aadhar_photo">
                                                    <?php if(!empty($agent['aadhar_photo'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="../Agents/uploads/aadhar_cards/<?php echo $agent['aadhar_photo']; ?>" target="_blank"><?php echo $agent['aadhar_photo']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">PAN Card Photo</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="file" name="pan_photo">
                                                    <?php if(!empty($agent['pan_photo'])): ?>
                                                        <small class="form-text text-muted">Current: <a href="../Agents/uploads/pan_cards/<?php echo $agent['pan_photo']; ?>" target="_blank"><?php echo $agent['pan_photo']; ?></a></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <hr>
                                            <p class="text-muted">Only fill in the password if you want to change it.</p>
                                            <div class="mb-4 row align-items-center">
                                                <label class="form-label-title col-sm-3 mb-0">New Password</label>
                                                <div class="col-sm-9">
                                                    <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current password">
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-sm-9 offset-sm-3">
                                                    <button type="submit" class="btn btn-primary">Update Agent</button>
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
        <?php include('footer.php'); ?>
    </div>
</body>
</html>