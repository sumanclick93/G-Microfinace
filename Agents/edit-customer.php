<?php
// Include the config file
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}
$agent_id = $_SESSION['agent_id'];
$message = '';

// 2. Get Customer ID from URL and validate
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: all-customer.php");
    exit();
}
$customer_id = $_GET['id'];

// 3. Handle Form Submission (POST request)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize text inputs
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];

    // Get current filenames to manage deletions
    $stmt_files = $conn->prepare("SELECT avatar, aadhar_photo, pan_photo FROM customers WHERE id = ? AND agent_id = ?");
    $stmt_files->bind_param("ii", $customer_id, $agent_id);
    $stmt_files->execute();
    $current_files = $stmt_files->get_result()->fetch_assoc();
    $stmt_files->close();

    // Reusable function to handle file uploads
    function handle_upload($file_input_name, $upload_dir, $current_filename) {
        if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
            $file_extension = pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION);
            $new_filename = uniqid('', true) . '.' . $file_extension;
            if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $upload_dir . $new_filename)) {
                // If a new file is uploaded, delete the old one
                if ($current_filename && file_exists($upload_dir . $current_filename)) {
                    unlink($upload_dir . $current_filename);
                }
                return $new_filename;
            }
        }
        return $current_filename; // Keep the old filename if no new file is uploaded
    }

    $avatar_filename = handle_upload('avatar', 'upload/customers/avatars/', $current_files['avatar']);
    $aadhar_filename = handle_upload('aadhar_photo', 'upload/customers/aadhar_cards/', $current_files['aadhar_photo']);
    $pan_filename = handle_upload('pan_photo', 'upload/customers/pan_cards/', $current_files['pan_photo']);

    // Build the SQL query
    $sql = "UPDATE customers SET full_name=?, phone=?, email=?, address=?, avatar=?, aadhar_photo=?, pan_photo=?";
    $types = "sssssss";
    $params = [$full_name, $phone, $email, $address, $avatar_filename, $aadhar_filename, $pan_filename];

    // Add password to query only if a new one is provided
    if (!empty($password)) {
        $sql .= ", password=?";
        $types .= "s";
        $params[] = password_hash($password, PASSWORD_DEFAULT);
    }

    // Add the WHERE clause with security check for agent_id
    $sql .= " WHERE id=? AND agent_id=?";
    $types .= "ii";
    $params[] = $customer_id;
    $params[] = $agent_id;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Customer details updated successfully!</div>";
        header("Location: all-customer.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error updating customer: " . $stmt->error . "</div>";
    }
    $stmt->close();
}

// 4. Fetch Customer Data for pre-filling the form (GET request)
$customer = null;
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = ? AND agent_id = ?");
$stmt->bind_param("ii", $customer_id, $agent_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $customer = $result->fetch_assoc();
} else {
    // If customer doesn't exist or doesn't belong to this agent, redirect
    $_SESSION['message'] = "<div class='alert alert-danger'>Customer not found or access denied.</div>";
    header("Location: all-customer.php");
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
                                        <h5>Edit Customer: <?php echo htmlspecialchars($customer['full_name']); ?></h5>
                                    </div>
                                    <?php if (!empty($message)) echo $message; ?>
                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="edit-customer.php?id=<?php echo $customer_id; ?>" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Full Name</label>
                                                <input class="form-control" type="text" name="full_name" value="<?php echo htmlspecialchars($customer['full_name']); ?>" required>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Phone Number</label>
                                                <input class="form-control" type="text" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Email Address (Optional)</label>
                                                <input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>">
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Full Address</label>
                                                <textarea class="form-control" name="address" rows="3"><?php echo htmlspecialchars($customer['address']); ?></textarea>
                                            </div>
                                            <hr class="my-3">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Profile Photo</label>
                                                <input class="form-control" type="file" name="avatar">
                                                <?php if(!empty($customer['avatar'])): ?>
                                                    <small class="form-text text-muted">Current: <a href="upload/customers/avatars/<?php echo $customer['avatar']; ?>" target="_blank">View Photo</a></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Aadhar Card Photo</label>
                                                <input class="form-control" type="file" name="aadhar_photo">
                                                <?php if(!empty($customer['aadhar_photo'])): ?>
                                                    <small class="form-text text-muted">Current: <a href="upload/customers/aadhar_cards/<?php echo $customer['aadhar_photo']; ?>" target="_blank">View Aadhar</a></small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">PAN Card Photo</label>
                                                <input class="form-control" type="file" name="pan_photo">
                                                <?php if(!empty($customer['pan_photo'])): ?>
                                                    <small class="form-text text-muted">Current: <a href="upload/customers/pan_cards/<?php echo $customer['pan_photo']; ?>" target="_blank">View PAN</a></small>
                                                <?php endif; ?>
                                            </div>
                                            <hr class="my-3">
                                            <p class="text-muted">Only fill in the password if you want to change it.</p>
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">New Password</label>
                                                <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current password">
                                            </div>
                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary w-100">Update Customer</button>
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