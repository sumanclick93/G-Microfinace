<?php
// Include the config file from the Super admin directory
include('config.php');

// 1. Authentication Check: Ensure an agent is logged in
if (!isset($_SESSION['agent_id'])) {
    header("Location: index.php");
    exit();
}

$agent_id = $_SESSION['agent_id'];
$message = '';

// Check for a success message from a previous redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize user inputs
    $full_name = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']); // Added email
    $address = $conn->real_escape_string($_POST['address']);
    $password = $_POST['password'];

    if (empty($full_name) || empty($phone) || empty($password)) {
        $message = "<div class='alert alert-danger'>Full Name, Phone, and Password are required.</div>";
    } else {
        // Handle File Uploads
        function upload_file($file_input_name, $upload_dir) {
            if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == 0) {
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                $file_extension = pathinfo($_FILES[$file_input_name]["name"], PATHINFO_EXTENSION);
                $new_filename = uniqid('', true) . '.' . $file_extension;
                if (move_uploaded_file($_FILES[$file_input_name]["tmp_name"], $upload_dir . $new_filename)) {
                    return $new_filename;
                }
            }
            return null;
        }

        $avatar_filename = upload_file('avatar', 'upload/customers/avatars/');
        $aadhar_filename = upload_file('aadhar_photo', 'upload/customers/aadhar_cards/');
        $pan_filename = upload_file('pan_photo', 'upload/customers/pan_cards/');

        // Generate a Unique Customer ID
        $customer_id_string = 'CUST' . time();

        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Prepare and execute the INSERT statement with the new email field
        $sql = "INSERT INTO customers (agent_id, customer_id_string, password, full_name, phone, email, address, avatar, aadhar_photo, pan_photo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        // Updated bind_param with an extra 's' for email
        $stmt->bind_param("issssssssss", $agent_id, $customer_id_string, $hashed_password, $full_name, $phone, $email, $address, $avatar_filename, $aadhar_filename, $pan_filename, $created_at);

        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Customer created successfully! Their login ID is: <strong>$customer_id_string</strong></div>";
            header("Location: add-customer.php");
            exit();
        } else {
            $message = "<div class='alert alert-danger'>Error creating customer: " . $stmt->error . "</div>";
        }
        $stmt->close();
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
                        <div class="col-sm-10 m-auto">
                            <div class="card">
                                <div class="card-body">
                                    <div class="title-header option-title">
                                        <h5>Add New Customer</h5>
                                    </div>

                                    <?php if (!empty($message)) echo $message; ?>

                                    <form class="theme-form theme-form-2 mega-form" method="POST" action="add-customer.php" enctype="multipart/form-data">
                                        <div class="row">
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Full Name</label>
                                                <input class="form-control" type="text" name="full_name" placeholder="Enter customer's full name" required>
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Phone Number</label>
                                                <input class="form-control" type="text" name="phone" placeholder="Enter 10-digit mobile number" required>
                                            </div>
                                            
                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Email Address (Optional)</label>
                                                <input class="form-control" type="email" name="email" placeholder="Enter customer's email">
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Full Address</label>
                                                <textarea class="form-control" name="address" rows="3" placeholder="Enter customer's full address"></textarea>
                                            </div>

                                            <hr class="my-3">

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Profile Photo</label>
                                                <input class="form-control" type="file" name="avatar">
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Aadhar Card Photo</label>
                                                <input class="form-control" type="file" name="aadhar_photo">
                                            </div>

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">PAN Card Photo</label>
                                                <input class="form-control" type="file" name="pan_photo">
                                            </div>

                                            <hr class="my-3">

                                            <div class="mb-4">
                                                <label class="form-label-title mb-2">Set Initial Password</label>
                                                <input class="form-control" type="password" name="password" placeholder="Set a temporary password for the customer" required>
                                            </div>

                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-primary w-100">Create Customer Account</button>
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