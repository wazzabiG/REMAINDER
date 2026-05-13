<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// Optional: Fetch current goal to show as a placeholder
$stmt_current = $conn->prepare("SELECT savingsGoal FROM END_USER WHERE UID = ?");
$stmt_current->bind_param("i", $uid);
$stmt_current->execute();
$current_goal = $stmt_current->get_result()->fetch_assoc()['savingsGoal'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newGoal = $_POST['savingsGoal'];
    $sql = "UPDATE END_USER SET savingsGoal = ? WHERE UID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $newGoal, $uid);
    $stmt->execute();
    
    header("Location: user_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Savings | Lutong Bahay</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
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
            padding: 20px;
        }

        .settings-card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .icon-box {
            width: 60px;
            height: 60px;
            background: #eff6ff;
            color: var(--accent);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            margin: 0 auto 20px auto;
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; }
        p { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; line-height: 1.5; }

        .info-box {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #475569;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid var(--accent);
        }

        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #475569; }
        
        .input-wrapper { position: relative; }
        .input-wrapper span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-weight: 600; }
        
        input {
            width: 100%;
            padding: 12px 12px 12px 30px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
        }

        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        .btn-save {
            width: 100%;
            background-color: var(--primary);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-save:hover { background-color: #0f172a; }

        .cancel-link {
            display: block;
            margin-top: 20px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .cancel-link:hover { color: var(--text); }
    </style>
</head>
<body>

<div class="settings-card">
    <div class="icon-box">💰</div>
    <h2>Adjust Savings Goal</h2>
    <p>Set a new target to save for this cycle.</p>

    <div class="info-box">
        <strong>Note:</strong> Increasing your goal will automatically lower your <em>Remaining Daily Budget</em> to help you stay on track.
    </div>

    <form method="POST">
        <div class="form-group">
            <label>New Target Amount</label>
            <div class="input-wrapper">
                <span>₱</span>
                <input type="number" step="0.01" name="savingsGoal" 
                       value="<?= number_format($current_goal, 2, '.', '') ?>" 
                       required autofocus>
            </div>
        </div>

        <button type="submit" class="btn-save">Update Goal</button>
        <a href="user_dashboard.php" class="cancel-link">Nevermind, go back</a>
    </form>
</div>

</body>
</html>