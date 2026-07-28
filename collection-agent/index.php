<?php
include('config.php');

// Redirect if already logged in
if (isset($_SESSION['collection_agent_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, first_name, last_name FROM agents WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $agent = $result->fetch_assoc();

            if (password_verify($password, $agent['password'])) {
                $_SESSION['collection_agent_id']       = $agent['id'];
                $_SESSION['collection_agent_username'] = $username;
                $_SESSION['collection_agent_name']     = trim(($agent['first_name'] ?? '') . ' ' . ($agent['last_name'] ?? '')) ?: $username;

                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Invalid username or password.";
            }
        } else {
            $error_message = "Invalid username or password.";
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
    <title>Collection Agent Login – G-Microfinance</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            padding: 1rem;
            padding-top: calc(1rem + 38px);
        }

        .samaj-foundation-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 10050;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(90deg, #0f5132 0%, #198754 55%, #0f5132 100%);
            color: #fff;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18);
        }

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        /* Brand header */
        .brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #e94560, #c0392b);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 8px 24px rgba(233, 69, 96, 0.4);
        }
        .brand-icon svg {
            width: 32px;
            height: 32px;
            fill: white;
        }
        .brand h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        .brand p {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.55);
            margin-top: 0.25rem;
        }

        /* Card */
        .card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }
        .card-subtitle {
            font-size: 0.8125rem;
            color: rgba(255,255,255,0.45);
            margin-bottom: 1.75rem;
        }

        /* Alert */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.75rem 1rem;
            background: rgba(233, 69, 96, 0.15);
            border: 1px solid rgba(233, 69, 96, 0.35);
            border-radius: 10px;
            color: #ff8096;
            font-size: 0.875rem;
            margin-bottom: 1.25rem;
        }
        .alert svg { flex-shrink: 0; width: 16px; height: 16px; fill: currentColor; }

        /* Form */
        .form-group { margin-bottom: 1.25rem; }

        label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            margin-bottom: 0.5rem;
        }

        .input-wrap {
            position: relative;
        }
        .input-wrap .icon {
            position: absolute;
            left: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.35);
            pointer-events: none;
        }
        .input-wrap .icon svg { width: 16px; height: 16px; fill: currentColor; }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem 0.875rem 0.75rem 2.625rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 10px;
            color: #ffffff;
            font-size: 0.9375rem;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }
        input::placeholder { color: rgba(255,255,255,0.25); }
        input:focus {
            border-color: #e94560;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.15);
        }

        /* Toggle password */
        .toggle-pw {
            position: absolute;
            right: 0.875rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: rgba(255,255,255,0.35);
            display: flex;
            align-items: center;
            padding: 0;
            transition: color 0.2s;
        }
        .toggle-pw:hover { color: rgba(255,255,255,0.7); }
        .toggle-pw svg { width: 18px; height: 18px; fill: currentColor; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #e94560, #c0392b);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 0.9375rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(233, 69, 96, 0.35);
            letter-spacing: 0.3px;
        }
        .btn-login:hover {
            opacity: 0.92;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(233, 69, 96, 0.45);
        }
        .btn-login:active { transform: translateY(0); }

        /* Footer note */
        .footer-note {
            text-align: center;
            margin-top: 1.75rem;
            font-size: 0.78rem;
            color: rgba(255,255,255,0.3);
        }
    </style>
</head>
<body>
<div class="samaj-foundation-bar" role="banner">Samajbandhan Foundation</div>
<div class="login-wrapper">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">
            <!-- Collection / wallet icon -->
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 7H3a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-1 12H4V9h16v10zM16 3H8a1 1 0 0 0 0 2h8a1 1 0 0 0 0-2zm-2 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
            </svg>
        </div>
        <h1>G-Microfinance</h1>
        <p>Collection Agent Portal</p>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-title">Welcome back</div>
        <div class="card-subtitle">Sign in to access your collection dashboard</div>

        <?php if (!empty($error_message)): ?>
        <div class="alert">
            <svg viewBox="0 0 20 20"><path d="M10 2a8 8 0 1 0 0 16A8 8 0 0 0 10 2zm.75 11.5h-1.5v-1.5h1.5v1.5zm0-3h-1.5V6h1.5v4.5z"/></svg>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <form action="index.php" method="POST" autocomplete="on">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                    </span>
                    <input type="text" id="username" name="username"
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           required autofocus autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6A5 5 0 0 0 7 6v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-6 9a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm3.1-9H8.9V6a3.1 3.1 0 0 1 6.2 0v2z"/></svg>
                    </span>
                    <input type="password" id="password" name="password"
                           placeholder="Enter your password"
                           required autocomplete="current-password">
                    <button type="button" class="toggle-pw" onclick="togglePassword()" title="Show/hide password">
                        <svg id="eye-icon" viewBox="0 0 24 24">
                            <path d="M12 5C7 5 2.7 8.1 1 12.5 2.7 16.9 7 20 12 20s9.3-3.1 11-7.5C21.3 8.1 17 5 12 5zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">Sign In</button>
        </form>
    </div>

    <div class="footer-note">&copy; <?php echo date('Y'); ?> G-Microfinance. All rights reserved.</div>
</div>

<script>
    function togglePassword() {
        const input = document.getElementById('password');
        const icon  = document.getElementById('eye-icon');
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path d="M17.9 5.1 5.1 17.9A10 10 0 0 1 1 12.5C2.7 8.1 7 5 12 5c2.2 0 4.3.7 6 1.9l-.1.2zM12 7.5a4.5 4.5 0 0 0-4.4 5.3l6.1-6.1A4.5 4.5 0 0 0 12 7.5zm7 5a10 10 0 0 1-2.7 4.3l-1.4-1.4A4.5 4.5 0 0 0 12 9.5v-2c2.5 0 4.7 1.3 6 3.3l1 .7z"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path d="M12 5C7 5 2.7 8.1 1 12.5 2.7 16.9 7 20 12 20s9.3-3.1 11-7.5C21.3 8.1 17 5 12 5zm0 12.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-8a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>';
        }
    }
</script>
</body>
</html>
