<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// Fetch current cycle to show as selected
$stmt_current = $conn->prepare("SELECT allowanceCycle FROM END_USER WHERE UID = ?");
$stmt_current->bind_param("i", $uid);
$stmt_current->execute();
$current_cycle = $stmt_current->get_result()->fetch_assoc()['allowanceCycle'] ?? 'Weekly';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newCycle = $_POST['allowanceCycle'];
    
    // We update BOTH the cycle type AND reset the cycle start date to NOW().
    // This prevents negative day calculations if scaling down from Monthly to Daily.
    $sql = "UPDATE END_USER SET allowanceCycle = ?, cycleStartDate = NOW() WHERE UID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $newCycle, $uid);
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
    <title>Update Cycle | Remainder</title>
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
            background: #fffbeb;
            padding: 15px;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #92400e;
            margin-bottom: 25px;
            text-align: left;
            border-left: 4px solid #f59e0b;
        }

        .form-group { text-align: left; margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #475569; }
        
        select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }

        select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

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

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(2px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-box {
            background: var(--card);
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 350px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-box h3 { margin: 0 0 10px 0; color: var(--primary); }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }

        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--accent); color: white; }
    </style>
</head>
<body>

<div class="settings-card">
    <div class="icon-box">⏳</div>
    <h2>Adjust Cycle</h2>
    <p>Change your pacing between Daily, Weekly, or Monthly.</p>

    <div class="info-box">
        <strong>Warning:</strong> Changing this will immediately restart your cycle tracking from today to prevent budget pacing errors.
    </div>

    <form id="cycleForm" method="POST">
        <div class="form-group">
            <label>Allowance Cycle</label>
            <select name="allowanceCycle" required>
                <option value="Daily" <?= $current_cycle == 'Daily' ? 'selected' : '' ?>>Daily</option>
                <option value="Weekly" <?= $current_cycle == 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="Monthly" <?= $current_cycle == 'Monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>
        </div>

        <button type="button" class="btn-save" onclick="openModal()">Update Cycle</button>
        <a href="user_dashboard.php" class="cancel-link">Nevermind, go back</a>
    </form>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Is this information correct?</h3>
        <p>You are about to modify your core database settings and restart your current cycle.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="submitForm()">Yes, proceed</button>
        </div>
    </div>
</div>

<script>
    function openModal() { document.getElementById('confirmModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('confirmModal').style.display = 'none'; }
    function submitForm() { document.getElementById('cycleForm').submit(); }
</script>

</body>
</html>