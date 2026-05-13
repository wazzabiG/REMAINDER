<?php
require 'db_connect.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT UID, password FROM USER WHERE firstName = ?");
    $stmt->bind_param("s", $firstName);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $uid = $row['UID'];
            $_SESSION['UID'] = $uid;

            $adminCheck = $conn->prepare("SELECT * FROM ADMINISTRATOR WHERE UID = ?");
            $adminCheck->bind_param("i", $uid);
            $adminCheck->execute();
            if ($adminCheck->get_result()->num_rows > 0) {
                $_SESSION['role'] = 'Admin';
                header("Location: index.php");
                exit();
            }

            $endUserCheck = $conn->prepare("SELECT * FROM END_USER WHERE UID = ?");
            $endUserCheck->bind_param("i", $uid);
            $endUserCheck->execute();
            if ($endUserCheck->get_result()->num_rows > 0) {
                $_SESSION['role'] = 'End User';
                header("Location: user_dashboard.php");
                exit();
            }
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }

        .login-card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .login-card h2 {
            margin-top: 0;
            color: var(--primary);
            font-size: 1.75rem;
            margin-bottom: 8px;
        }

        .login-card p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 24px;
        }

        .error-msg {
            background-color: #fee2e2;
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        .form-group {
            text-align: left;
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 6px;
            color: #475569;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .login-btn {
            width: 100%;
            background-color: var(--primary);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        .login-btn:hover {
            background-color: #0f172a;
        }

        .register-link {
            margin-top: 24px;
            font-size: 0.9rem;
            color: #64748b;
        }

        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">
        <h2>Remainder</h2>
        <p>Welcome back! Please login to your account.</p>

        <?php if(isset($error)): ?>
            <div class="error-msg">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="firstName">First Name</label>
                <input type="text" name="firstName" id="firstName" placeholder="e.g. Juan" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="register-link">
            Don't have an account? <br>
            <a href="register.php">Register as End User</a>
        </div>
    </div>
</div>

</body>
</html>