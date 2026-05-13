<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- 1. Fetch current cycle data ---
$sql = "SELECT totalAllowance, cycleStartDate, savingsBalance FROM END_USER WHERE UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// --- 2. Calculate actual spending (Expenses only) ---
$spentSql = "SELECT SUM(amount) as totalSpent FROM TRANSACTION WHERE author_UID = ? AND type = 'Expense' AND isDeleted = FALSE AND createdAt >= ?";
$spentStmt = $conn->prepare($spentSql);
$spentStmt->bind_param("is", $uid, $user['cycleStartDate']);
$spentStmt->execute();
$totalSpent = $spentStmt->get_result()->fetch_assoc()['totalSpent'] ?? 0;

// --- 3. Calculate gains (Income only) ---
$gainsSql = "SELECT SUM(amount) as totalGains FROM TRANSACTION WHERE author_UID = ? AND type = 'Income' AND isDeleted = FALSE AND createdAt >= ?";
$gainsStmt = $conn->prepare($gainsSql);
$gainsStmt->bind_param("is", $uid, $user['cycleStartDate']);
$gainsStmt->execute();
$totalGains = $gainsStmt->get_result()->fetch_assoc()['totalGains'] ?? 0;

// Correct Leftover Math: (Allowance + Gains) - Spent
$leftoverFunds = ($user['totalAllowance'] + $totalGains) - $totalSpent;

// --- 4. Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $newAllowance = $_POST['totalAllowance']; 

    $conn->begin_transaction();
    try {
        if ($action == 'carry_over' && $leftoverFunds > 0) {
            // Use NOW() directly in the SQL string
            $update = $conn->prepare("UPDATE END_USER SET savingsBalance = savingsBalance + ?, cycleStartDate = NOW(), totalAllowance = ? WHERE UID = ?");
            $update->bind_param("ddi", $leftoverFunds, $newAllowance, $uid);
        } else {
            // Use NOW() directly in the SQL string
            $update = $conn->prepare("UPDATE END_USER SET cycleStartDate = NOW(), totalAllowance = ? WHERE UID = ?");
            $update->bind_param("di", $newAllowance, $uid);
        }
        $update->execute();

        $conn->commit();
        header("Location: user_dashboard.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "System error: Could not reset cycle.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Cycle | Remainder</title>
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
            min-height: 100vh;
            padding: 20px;
        }

        .summary-card {
            background: var(--card);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; }

        .math-box {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: left;
        }

        .math-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .math-total {
            border-top: 2px solid #cbd5e1;
            margin-top: 10px;
            padding-top: 10px;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary);
        }

        .allowance-setup {
            margin-bottom: 30px;
            text-align: left;
        }

        .allowance-setup label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .allowance-setup input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            box-sizing: border-box;
            color: var(--primary);
        }

        .decision-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .action-btn {
            padding: 16px;
            border-radius: 12px;
            border: 2px solid transparent;
            cursor: pointer;
            text-align: left;
            transition: 0.2s;
            background: white;
            display: block;
            width: 100%;
        }

        .carry-over { border-color: #dcfce7; background: #f0fdf4; }
        .carry-over:hover { border-color: var(--success); }
        .hard-reset { border-color: #fee2e2; background: #fef2f2; }
        .hard-reset:hover { border-color: var(--danger); }

        .action-title { display: block; font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
        .action-desc { display: block; font-size: 0.8rem; color: #64748b; line-height: 1.4; }

        .carry-over .action-title { color: #166534; }
        .hard-reset .action-title { color: #991b1b; }

        .cancel-btn {
            display: inline-block;
            margin-top: 25px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="summary-card">
    <h2>Cycle Summary</h2>
    <p class="subtitle">Your cycle has ended. Here is how your gains and spending balanced out.</p>

    <div class="math-box">
        <div class="math-row">
            <span>Base Allowance</span>
            <span>₱<?= number_format($user['totalAllowance'], 2) ?></span>
        </div>
        <div class="math-row">
            <span>Cycle Gains</span>
            <span style="color: var(--success);">+ ₱<?= number_format($totalGains, 2) ?></span>
        </div>
        <div class="math-row">
            <span>Total Spent</span>
            <span style="color: var(--danger);">- ₱<?= number_format($totalSpent, 2) ?></span>
        </div>
        <div class="math-total">
            <span>Leftover Funds</span>
            <span style="color: var(--success);">₱<?= number_format($leftoverFunds, 2) ?></span>
        </div>
    </div>

    <form method="POST">
        <div class="allowance-setup">
            <label for="totalAllowance">Next Cycle's Base Allowance (₱)</label>
            <input type="number" step="0.01" name="totalAllowance" id="totalAllowance" value="<?= $user['totalAllowance'] ?>" required>
        </div>

        <div class="decision-grid">
            <?php if ($leftoverFunds > 0): ?>
                <button type="submit" name="action" value="carry_over" class="action-btn carry-over">
                    <span class="action-title">💰 Carry Over to Savings</span>
                    <span class="action-desc">Add ₱<?= number_format($leftoverFunds, 2) ?> to your Vault and restart.</span>
                </button>
            <?php endif; ?>

            <button type="submit" name="action" value="reset" class="action-btn hard-reset">
                <span class="action-title">♻️ Hard Reset</span>
                <span class="action-desc">Start fresh. Leftover funds will not be saved.</span>
            </button>
        </div>
    </form>

    <a href="user_dashboard.php" class="cancel-btn">Back to Dashboard</a>
</div>

</body>
</html>