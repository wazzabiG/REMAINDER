<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- 1. Fetch User Data ---
$sql = "SELECT U.firstName, E.allowanceCycle, E.totalAllowance, E.savingsGoal, E.savingsBalance, E.cycleStartDate 
        FROM USER U JOIN END_USER E ON U.UID = E.UID WHERE U.UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// --- 2. Budget Logic (Accounting for Expenses and Gains) ---
$spentSql = "SELECT SUM(amount) as totalSpent FROM TRANSACTION WHERE author_UID = ? AND type = 'Expense' AND isDeleted = FALSE AND createdAt >= ?";
$spentStmt = $conn->prepare($spentSql);
$spentStmt->bind_param("is", $uid, $user['cycleStartDate']);
$spentStmt->execute();
$totalSpent = $spentStmt->get_result()->fetch_assoc()['totalSpent'] ?? 0;

$incomeSql = "SELECT SUM(amount) as totalIncome FROM TRANSACTION WHERE author_UID = ? AND type = 'Income' AND isDeleted = FALSE AND createdAt >= ?";
$incomeStmt = $conn->prepare($incomeSql);
$incomeStmt->bind_param("is", $uid, $user['cycleStartDate']);
$incomeStmt->execute();
$totalIncome = $incomeStmt->get_result()->fetch_assoc()['totalIncome'] ?? 0;

$startDate = new DateTime($user['cycleStartDate']);
$currentDate = new DateTime();
$daysPassed = $startDate->diff($currentDate)->days;

if ($user['allowanceCycle'] == 'Weekly') {
    $daysRemaining = 7 - $daysPassed;
} elseif ($user['allowanceCycle'] == 'Monthly') {
    $daysRemaining = 30 - $daysPassed; 
} else {
    $daysRemaining = 1; 
}

if ($daysRemaining <= 0) $daysRemaining = 1; 

$totalPool = $user['totalAllowance'] + $totalIncome - $totalSpent - $user['savingsGoal'];

if ($totalPool < 0) {
    $remainingDailyBudget = $totalPool;
    $isDeficit = true;
} else {
    $remainingDailyBudget = $totalPool / $daysRemaining;
    $isDeficit = false;
}

// --- 3. History ---
$historySql = "SELECT TID, amount, type, category, expenseType, note, createdAt 
               FROM TRANSACTION 
               WHERE author_UID = ? AND isDeleted = FALSE 
               ORDER BY createdAt DESC";
$historyStmt = $conn->prepare($historySql);
$historyStmt->bind_param("i", $uid);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --success: #22c55e;
            --danger: #ef4444;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
        }

        .container { max-width: 1000px; margin: 0 auto; }

        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .welcome-msg h2 { margin: 0; color: var(--primary); }
        
        .logout-btn {
            text-decoration: none; color: var(--danger); font-weight: 600;
            padding: 8px 16px; border: 1px solid var(--danger); border-radius: 6px; transition: 0.2s;
        }
        .logout-btn:hover { background: var(--danger); color: white; }

        .grid-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: var(--card); padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .card h3 { margin: 0 0 10px 0; font-size: 0.875rem; text-transform: uppercase; color: #64748b; }
        .card .value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        
        .card-links { display: flex; gap: 15px; margin-top: 8px; }
        .card-links a { font-size: 0.85rem; text-decoration: none; font-weight: 600; cursor: pointer; }
        .card-links a:hover { text-decoration: underline; }

        .budget-hero { color: white; padding: 30px; border-radius: 16px; margin-bottom: 30px; text-align: center; }
        .hero-normal { background: var(--primary); }
        .hero-deficit { background: #7f1d1d; border: 2px solid #ef4444; }

        .budget-hero .daily-value { font-size: 3rem; margin: 10px 0; }
        .budget-hero p { margin: 0; opacity: 0.9; }

        .actions-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 30px; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: 0.2s; display: inline-block; cursor: pointer; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-outline { border: 1px solid #cbd5e1; color: var(--text); }
        .btn-test { background: #f1f5f9; color: #475569; font-size: 0.8rem; }

        .history-section { background: var(--card); border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #f1f5f9; padding: 15px; font-size: 0.8rem; text-transform: uppercase; color: #64748b; }
        td { padding: 15px; border-top: 1px solid #f1f5f9; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-need { background: #dcfce7; color: #166534; }
        .badge-want { background: #ffedd5; color: #9a3412; }
        .badge-gain { background: #dbeafe; color: #1e40af; }
        .badge-vault { background: #fef08a; color: #854d0e; }

        .action-links a { color: var(--accent); text-decoration: none; margin-right: 10px; font-size: 0.85rem; cursor: pointer; }

        /* Custom Modal Styles */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);
            justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-box {
            background: var(--card); padding: 30px; border-radius: 16px; width: 90%; max-width: 350px;
            text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-box h3 { margin: 0 0 10px 0; color: var(--danger); }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--danger); color: white; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <div class="welcome-msg"><h2>Hello, <?= htmlspecialchars($user['firstName']) ?>! 👋</h2></div>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <?php if(file_exists('check_warning.php')) require 'check_warning.php'; ?>

    <div class="budget-hero <?= $isDeficit ? 'hero-deficit' : 'hero-normal' ?>">
        <?php if ($isDeficit): ?>
            <p style="color: #fca5a5; font-weight: 600;">⚠️ CURRENT DEFICIT</p>
            <div class="daily-value" style="color: #fef2f2;">-₱<?= number_format(abs($remainingDailyBudget), 2) ?></div>
            <p>You have overspent your available cycle funds. Any gains logged will directly reduce this debt.</p>
        <?php else: ?>
            <p>Your Remaining Daily Budget</p>
            <div class="daily-value" style="color: #fff;">₱<?= number_format($remainingDailyBudget, 2) ?></div>
            <p>Pace yourself! You have <strong><?= $daysRemaining ?> days</strong> left in your <?= $user['allowanceCycle'] ?> cycle.</p>
        <?php endif; ?>
    </div>

    <div class="grid-stats">
        <div class="card">
            <h3>Vault Savings</h3>
            <div class="value" style="color: var(--accent);">₱<?= number_format($user['savingsBalance'], 2) ?></div>
            <div class="card-links">
                <a href="update_savings.php" style="color: #64748b;">Goal: ₱<?= number_format($user['savingsGoal'], 2) ?></a>
                <a href="use_vault.php" style="color: var(--success);">+ Withdraw</a>
            </div>
        </div>
        <div class="card">
            <h3>Total Spent vs Gained</h3>
            <div class="value">
                <span style="color: var(--danger);">-₱<?= number_format($totalSpent, 2) ?></span>
                <span style="font-size: 0.9rem; color: #94a3b8;"> / </span>
                <span style="color: var(--success);">+₱<?= number_format($totalIncome, 2) ?></span>
            </div>
        </div>
        <div class="card">
            <h3>Base Allowance</h3>
            <div class="value">₱<?= number_format($user['totalAllowance'], 2) ?></div>
            <div class="card-links">
                <a href="update_cycle.php" style="color: var(--accent);">Change Cycle (<?= $user['allowanceCycle'] ?>)</a>
            </div>
        </div>
    </div>

    <div class="actions-bar">
        <a href="add_transaction.php" class="btn btn-primary">+ Log Expense</a>
        <a href="add_income.php" class="btn btn-success">+ Log Gain</a>
        <a href="forum.php" class="btn btn-outline">Community Forum</a>
        <a href="insights.php" class="btn btn-outline">Spending Insights</a>
        <div style="flex-grow: 1;"></div>
        <a href="fast_forward.php" class="btn btn-test">Time Travel +1 Day</a>
        <a href="reset_cycle.php" class="btn btn-test" style="color: var(--danger);">Reset Cycle</a>
    </div>

    <div class="history-section">
        <h3 style="padding: 15px; margin: 0; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">Activity History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Classification</th>
                    <th>Amount</th>
                    <th>Note</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($historyResult->num_rows > 0): ?>
                    <?php while($row = $historyResult->fetch_assoc()): ?>
                    <tr>
                        <td style="font-size: 0.85rem; color: #64748b;"><?= date('M d, g:i A', strtotime($row['createdAt'])) ?></td>
                        <td><strong><?= htmlspecialchars($row['category']) ?></strong></td>
                        <td>
                            <?php if(isset($row['type']) && $row['type'] == 'Income'): ?>
                                <?php if($row['category'] == 'Vault Transfer'): ?>
                                    <span class="badge badge-vault">VAULT</span>
                                <?php else: ?>
                                    <span class="badge badge-gain">GAIN</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge <?= $row['expenseType'] == 'Need' ? 'badge-need' : 'badge-want' ?>">
                                    <?= htmlspecialchars($row['expenseType'] ?? 'N/A') ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600; color: <?= (isset($row['type']) && $row['type'] == 'Income') ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= (isset($row['type']) && $row['type'] == 'Income') ? '+' : '-' ?> ₱<?= number_format($row['amount'], 2) ?>
                        </td>
                        <td style="font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($row['note'] ?? '-') ?></td>
                        <td class="action-links">
                            <?php if ((time() - strtotime($row['createdAt'])) < 86400): ?>
                                <a href="edit_transaction.php?id=<?= $row['TID'] ?>">Edit</a>
                            <?php else: ?>
                                <span style="color: #cbd5e1; font-size: 0.8rem;">Locked</span>
                            <?php endif; ?>
                            <a onclick="openDeleteModal('delete_transaction.php?id=<?= $row['TID'] ?>')" style="color: var(--danger);">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: #94a3b8;">No records found. Time to balance your budget!</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Delete Record?</h3>
        <p>Are you sure you want to remove this transaction from your history?</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="executeDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
    let deleteUrl = '';
    function openDeleteModal(url) {
        deleteUrl = url;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() { document.getElementById('deleteModal').style.display = 'none'; }
    function executeDelete() { if(deleteUrl) window.location.href = deleteUrl; }
</script>

</body>
</html>