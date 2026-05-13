<?php
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $allowanceCycle = $_POST['allowanceCycle'];
    $totalAllowance = $_POST['totalAllowance'];
    $savingsGoal = $_POST['savingsGoal'];
    $forumRole = 'Normal User'; 
    $savingsBalance = 0.00;     
    $cycleStartDate = date('Y-m-d H:i:s'); 

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("INSERT INTO USER (firstName, lastName, password) VALUES (?, ?, ?)");
        $stmt1->bind_param("sss", $firstName, $lastName, $password);
        $stmt1->execute();
        $uid = $conn->insert_id;

        $stmt2 = $conn->prepare("INSERT INTO END_USER (UID, forumRole, allowanceCycle, totalAllowance, savingsGoal, savingsBalance, cycleStartDate) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("issddds", $uid, $forumRole, $allowanceCycle, $totalAllowance, $savingsGoal, $savingsBalance, $cycleStartDate);
        $stmt2->execute();

        $conn->commit();
        header("Location: login.php"); 
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $error = "Registration failed: " . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Lutong Bahay</title>
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
            min-height: 100vh;
            padding: 40px 20px;
        }

        .reg-container {
            width: 100%;
            max-width: 500px;
        }

        .reg-card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .reg-card h2 {
            margin-top: 0;
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 5px;
            text-align: center;
        }

        .reg-card p.subtitle {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 30px;
            text-align: center;
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

        .section-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent);
            margin-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 16px;
        }

        .form-group {
            flex: 1;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .reg-btn {
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

        .reg-btn:hover {
            background-color: #0f172a;
        }

        .footer-links {
            margin-top: 24px;
            text-align: center;
            font-size: 0.9rem;
            color: #64748b;
        }

        .footer-links a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="reg-container">
    <div class="reg-card">
        <h2>Create Account</h2>
        <p class="subtitle">Join Lutong Bahay and start pacing your budget.</p>

        <?php if(isset($error)): ?>
            <div class="error-msg">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <span class="section-label">Personal Information</span>
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="firstName" placeholder="Juan" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="lastName" placeholder="Dela Cruz" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a secure password" required>
            </div>

            <span class="section-label" style="margin-top: 25px;">Budget Setup</span>
            <div class="form-group">
                <label>Allowance Cycle</label>
                <select name="allowanceCycle" required>
                    <option value="Daily">Daily</option>
                    <option value="Weekly" selected>Weekly</option>
                    <option value="Monthly">Monthly</option>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Allowance (₱)</label>
                    <input type="number" step="0.01" name="totalAllowance" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Savings Goal (₱)</label>
                    <input type="number" step="0.01" name="savingsGoal" placeholder="0.00" required>
                </div>
            </div>

            <button type="submit" class="reg-btn">Start My Journey</button>
        </form>

        <div class="footer-links">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

</body>
</html>