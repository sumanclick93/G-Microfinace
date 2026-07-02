<?php
// Include the database configuration and start the session
include('config.php');

// If the admin is already logged in, redirect them to the dashboard
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php"); // Or your main admin page
    exit();
}

$error_message = '';

// Check if the form has been submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Prepare a statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, password FROM admins WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Verify the hashed password
            if (password_verify($password, $admin['password'])) {
                // Password is correct, create session variables
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $username;

                // Redirect to the admin dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                // Incorrect password
                $error_message = "Invalid password.";
            }
        } else {
            // No user found
            $error_message = "Invalid username ";
        }
        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - G-Microfinance</title>
    <link rel="stylesheet" href="assets/css/style.css"> <style>
        /* Simple styling for the login page */
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; }
        .login-card { max-width: 400px; width: 100%; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); background-color: white; }
        .login-card h2 { text-align: center; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ced4da; border-radius: 4px; }
        .btn-primary { width: 100%; padding: 0.75rem; background-color: #0d6efd; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary:hover { background-color: #0b5ed7; }
        .alert-danger { padding: 1rem; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Admin Panel Login</h2>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Log In</button>
        </form>
    </div>
</body>
</html>