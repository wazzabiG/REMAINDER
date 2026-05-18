<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// Fetch current savings balance to validate the withdrawal
$sql_vault = "SELECT savingsBalance FROM END_USER WHERE UID = ?";
$stmt_vault = $conn->prepare($sql_vault);
$stmt_vault->bind_param("i", $uid);
$stmt_vault->execute();
$current_balance = $stmt_vault->get_result()->fetch_assoc()['savingsBalance'] ?? 0;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $withdrawAmount = floatval($_POST['amount']);

    if ($withdrawAmount > 0 && $withdrawAmount <= $current_balance) {
        $conn->begin_transaction();
        try {
            // 1. Deduct from Vault
            $updateVault = $conn->prepare("UPDATE END_USER SET savingsBalance = savingsBalance - ? WHERE UID = ?");
            $updateVault->bind_param("di", $withdrawAmount, $uid);
            $updateVault->execute();

            // 2. Log as Income so it boosts the dashboard math and shows in history
            $category = "Vault Transfer";
            $type = "Income";
            $note = "Withdrawn from Vault Savings";
            
            $insertLog = $conn->prepare("INSERT INTO TRANSACTION (author_UID, amount, type, category, note, createdAt) VALUES (?, ?, ?, ?, ?, NOW())");
            $insertLog->bind_param("idsss", $uid, $withdrawAmount, $type, $category, $note);
            $insertLog->execute();

            $conn->commit();
            header("Location: user_dashboard.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "System error: Transfer failed.";
        }
    } else {
        $error = "Invalid amount. You cannot withdraw more than your current balance.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Use Vault | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --success: #22c55e;
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
            padding: 20px;
        }

        .transfer-card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border-top: 8px solid var(--accent);
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; }
        p { color: #64748b; font-size: 0.9rem; margin-bottom: 20px; }

        .balance-display {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .balance-display span { display: block; font-size: 0.85rem; color: #64748b; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .balance-display strong { font-size: 2rem; color: var(--primary); }

        .error-msg {
            background-color: #fee2e2;
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }

        .form-group { text-align: left; margin-bottom: 25px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #475569; }
        
        .input-wrapper { position: relative; }
        .input-wrapper span { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--success); }
        
        input {
            width: 100%;
            padding: 15px 15px 15px 35px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }

        .btn-submit {
            width: 100%;
            background-color: var(--accent);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover { background-color: #2563eb; transform: translateY(-1px); }
        .btn-submit:disabled { background-color: #cbd5e1; cursor: not-allowed; transform: none; }

        .cancel-link {
            display: block;
            margin-top: 20px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
        }

        /* Custom Modal Styles */
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

<div class="transfer-card">
    <h2>Withdraw from Vault 🏦</h2>
    <p>Move savings back into your active daily budget.</p>

    <div class="balance-display">
        <span>Available in Vault</span>
        <strong>₱<?= number_format($current_balance, 2) ?></strong>
    </div>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form id="transferForm" method="POST">
        <div class="form-group">
            <label>Amount to Withdraw</label>
            <div class="input-wrapper">
                <span>₱</span>
                <input type="number" step="0.01" name="amount" placeholder="0.00" max="<?= $current_balance ?>" required autofocus <?= $current_balance <= 0 ? 'disabled' : '' ?>>
            </div>
        </div>

        <?php if($current_balance > 0): ?>
            <button type="button" class="btn-submit" onclick="openModal()">Transfer to Wallet</button>
        <?php else: ?>
            <button type="button" class="btn-submit" disabled>Vault is Empty</button>
        <?php endif; ?>
        
        <a href="user_dashboard.php" class="cancel-link">Cancel</a>
    </form>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Confirm Transfer?</h3>
        <p>This will move funds from your Vault into your active cycle budget.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="submitForm()">Yes, proceed</button>
        </div>
    </div>
</div>

<script>
    function openModal() { document.getElementById('confirmModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('confirmModal').style.display = 'none'; }
    function submitForm() { document.getElementById('transferForm').submit(); }
</script>

</body>
</html>