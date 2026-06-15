<?php
session_start();

// If already logged in as admin, still require client-side sign-in flow
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: ../Client%20Side/index.html?openAdmin=1');
    exit();
}

// Database connection
include __DIR__ . '/../database/db_connect.php';

$error_message = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Query the database for admin user
        $query = "SELECT id, username, password FROM admin_users WHERE username = ?";
        $stmt = $conn->prepare($query);
        
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // Verify password (using password_verify for hashed passwords)
                if (password_verify($password, $admin['password'])) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    // Must complete sign-in on client before opening admin dashboard
                    header('Location: ../Client%20Side/index.html?openAdmin=1');
                    exit();
                } else {
                    $error_message = 'Invalid username or password.';
                }
            } else {
                $error_message = 'Invalid username or password.';
            }
            $stmt->close();
        } else {
            $error_message = 'Database error. Please try again.';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Sip & Pulse Cafe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #F8F4E1;
            --bg-surface: #E1DCC9;
            --text-main: #1F150C;
            --brand: #74512D;
            --brand-dark: #543310;
            --admin-accent: #c0392b;
        }

        [data-bs-theme="dark"] {
            --bg-body: #2b1f14;
            --bg-surface: #3a2a1c;
            --text-main: #F8F4E1;
            --brand: #c9a87a;
        }

        body {
            background: linear-gradient(135deg, #7b1c10, #c0392b);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .login-container {
            background: var(--bg-body);
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 420px;
            width: 100%;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo {
            width: 80px;
            height: auto;
            margin-bottom: 16px;
        }

        .login-title {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--brand);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-main);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid var(--brand);
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            background: white;
            color: var(--text-main);
        }

        .form-control:focus {
            border-color: var(--brand-dark);
            box-shadow: 0 0 0 3px rgba(116, 81, 45, 0.1);
            outline: none;
        }

        .error-message {
            background: rgba(192, 57, 43, 0.1);
            border: 2px solid var(--admin-accent);
            border-radius: 8px;
            padding: 12px 16px;
            color: var(--admin-accent);
            margin-bottom: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #74512D, #543310);
            color: #F8F4E1;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 24px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(116, 81, 45, 0.3);
            color: #F8F4E1;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 2px solid var(--bg-surface);
        }

        .login-footer a {
            color: var(--brand);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: var(--brand-dark);
        }

        .admin-badge {
            display: inline-block;
            background: var(--admin-accent);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="admin-badge">Admin Only</div>
            <h1 class="login-title">Admin Login</h1>
            <p class="login-subtitle">Sip & Pulse Cafe Management</p>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <span class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">error</span>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">
                    <span class="material-icons" style="vertical-align: middle; font-size: 18px; margin-right: 8px;">person</span>
                    Username
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="form-control" 
                    placeholder="Enter your admin username"
                    required
                    autocomplete="off"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">
                    <span class="material-icons" style="vertical-align: middle; font-size: 18px; margin-right: 8px;">lock</span>
                    Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    placeholder="Enter your password"
                    required
                    autocomplete="off"
                >
            </div>

            <button type="submit" class="btn-login">
                <span class="material-icons" style="vertical-align: middle; margin-right: 8px; font-size: 20px;">login</span>
                Sign In
            </button>
        </form>

        <div class="login-footer">
            <a href="../Client Side/index.html">
                <span class="material-icons" style="vertical-align: middle; margin-right: 4px; font-size: 18px;">arrow_back</span>
                Back to Client View
            </a>
        </div>
    </div>
</body>
</html>