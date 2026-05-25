<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- 1. Fetch current cycle data ---
$sql = "SELECT totalAllowance, allowanceCycle, cycleStartDate, savingsBalance, savingsGoal FROM END_USER WHERE UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// --- 2. Calculate actual spending (Expenses only, Active only) ---
$spentSql = "SELECT SUM(amount) as totalSpent FROM TRANSACTION WHERE author_UID = ? AND type = 'Expense' AND isDeleted = FALSE AND isArchived = 0";
$spentStmt = $conn->prepare($spentSql);
$spentStmt->bind_param("i", $uid);
$spentStmt->execute();
$totalSpent = $spentStmt->get_result()->fetch_assoc()['totalSpent'] ?? 0;

// --- 3. Calculate gains (Income only, Active only) ---
$gainsSql = "SELECT SUM(amount) as totalGains FROM TRANSACTION WHERE author_UID = ? AND type = 'Income' AND isDeleted = FALSE AND isArchived = 0";
$gainsStmt = $conn->prepare($gainsSql);
$gainsStmt->bind_param("i", $uid);
$gainsStmt->execute();
$totalGains = $gainsStmt->get_result()->fetch_assoc()['totalGains'] ?? 0;

$leftoverFunds = ($user['totalAllowance'] + $totalGains) - $totalSpent;

// --- 4. Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $newAllowance = $_POST['totalAllowance']; 
    $newCycle = $_POST['allowanceCycle'];
    $newSavingsGoal = $_POST['savingsGoal']; 

    $conn->begin_transaction();
    try {
        // Step 1: Archive ALL active transactions to close out this cycle permanently
        $archive = $conn->prepare("UPDATE TRANSACTION SET isArchived = 1 WHERE author_UID = ? AND isArchived = 0");
        $archive->bind_param("i", $uid);
        $archive->execute();

        // Step 2: Start the new cycle timeline and reset core variables
        $update = $conn->prepare("UPDATE END_USER SET cycleStartDate = NOW(), totalAllowance = ?, allowanceCycle = ?, savingsGoal = ? WHERE UID = ?");
        $update->bind_param("dsdi", $newAllowance, $newCycle, $newSavingsGoal, $uid);
        $update->execute();

        // Step 3: Handle the balance routing
        if ($leftoverFunds > 0) {
            // Surplus -> Add to Vault
            $updateVault = $conn->prepare("UPDATE END_USER SET savingsBalance = savingsBalance + ? WHERE UID = ?");
            $updateVault->bind_param("di", $leftoverFunds, $uid);
            $updateVault->execute();
        } elseif ($leftoverFunds < 0) {
            // Deficit -> Inject an automatic expense into the new cycle to enforce debt repayment
            // (Because we already ran the archive update above, this new transaction will remain active)
            $debtAmount = abs($leftoverFunds);
            $type = 'Expense';
            $category = 'Other'; 
            $note = 'Rollover debt from previous cycle';
            $expType = 'Need'; 
            
            $insertDebt = $conn->prepare("INSERT INTO TRANSACTION (author_UID, amount, type, category, note, expenseType, createdAt, isArchived) VALUES (?, ?, ?, ?, ?, ?, NOW(), 0)");
            $insertDebt->bind_param("idssss", $uid, $debtAmount, $type, $category, $note, $expType);
            $insertDebt->execute();
        }

        $conn->commit();
        header("Location: user_dashboard.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "System error: Could not finalize cycle.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalize Cycle | Remainder</title>
    <style>
        :root { --primary: #1e293b; --accent: #3b82f6; --success: #22c55e; --danger: #ef4444; --bg: #f8fafc; --card: #ffffff; --text: #334155; --border: #e2e8f0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .summary-card { background: var(--card); padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); width: 100%; max-width: 500px; text-align: center; }
        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 25px; }
        
        .debt-banner { background: #fef2f2; border-left: 4px solid var(--danger); padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; text-align: left; }
        .debt-banner strong { color: #991b1b; display: block; font-size: 1rem; margin-bottom: 5px; }
        .debt-banner p { color: #991b1b; font-size: 0.85rem; margin: 0; line-height: 1.4; }

        .math-box { background: #f1f5f9; padding: 20px; border-radius: 12px; margin-bottom: 30px; text-align: left; }
        .math-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 0.95rem; }
        .math-total { border-top: 2px solid #cbd5e1; margin-top: 10px; padding-top: 10px; display: flex; justify-content: space-between; font-weight: 700; font-size: 1.2rem; color: var(--primary); }
        .allowance-setup { margin-bottom: 30px; text-align: left; }
        .allowance-setup label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--primary); margin-bottom: 8px; }
        .allowance-setup input, .allowance-setup select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 1.1rem; font-weight: 600; box-sizing: border-box; color: var(--primary); margin-bottom: 15px; }
        
        .action-btn { padding: 16px; border-radius: 12px; border: 2px solid transparent; cursor: pointer; text-align: left; transition: 0.2s; background: white; display: block; width: 100%; }
        .carry-over { border-color: #dcfce7; background: #f0fdf4; }
        .carry-over:hover { border-color: var(--success); }
        .carry-debt { border-color: #fee2e2; background: #fef2f2; }
        .carry-debt:hover { border-color: var(--danger); }
        .neutral-reset { border-color: #e2e8f0; background: #f8fafc; }
        .neutral-reset:hover { border-color: var(--primary); }
        
        .action-title { display: block; font-weight: 700; font-size: 1rem; margin-bottom: 4px; }
        .action-desc { display: block; font-size: 0.8rem; color: #64748b; line-height: 1.4; }
        .carry-over .action-title { color: #166534; }
        .carry-debt .action-title { color: #991b1b; }
        .neutral-reset .action-title { color: var(--primary); }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: var(--card); padding: 30px; border-radius: 16px; width: 90%; max-width: 350px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .modal-box h3 { margin: 0 0 10px 0; color: var(--primary); }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--accent); color: white; }
    </style>
</head>
<body>

<div class="summary-card">
    <h2>Cycle Complete</h2>
    <p class="subtitle">Your timeline has ended. Review your summary below to begin the next cycle.</p>

    <?php if ($leftoverFunds < 0): ?>
        <div class="debt-banner">
            <strong>⚠️ You are in Debt!</strong>
            <p>You overspent by ₱<?= number_format(abs($leftoverFunds), 2) ?> this cycle. This exact amount will automatically be deducted from your new cycle's starting budget.</p>
        </div>
    <?php endif; ?>

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
            <span><?= $leftoverFunds < 0 ? 'Total Debt' : 'Leftover Funds' ?></span>
            <span style="color: <?= $leftoverFunds < 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                <?= $leftoverFunds < 0 ? '-' : '' ?>₱<?= number_format(abs($leftoverFunds), 2) ?>
            </span>
        </div>
    </div>

    <form id="resetForm" method="POST">
        <div class="allowance-setup">
            <label for="allowanceCycle">Next Cycle's Duration</label>
            <select name="allowanceCycle" id="allowanceCycle" required>
                <option value="Daily" <?= $user['allowanceCycle'] == 'Daily' ? 'selected' : '' ?>>Daily</option>
                <option value="Weekly" <?= $user['allowanceCycle'] == 'Weekly' ? 'selected' : '' ?>>Weekly</option>
                <option value="Monthly" <?= $user['allowanceCycle'] == 'Monthly' ? 'selected' : '' ?>>Monthly</option>
            </select>

            <label for="totalAllowance">Next Cycle's Base Allowance (₱)</label>
            <input type="number" step="0.01" name="totalAllowance" id="totalAllowance" value="<?= $user['totalAllowance'] ?>" required>

            <label for="savingsGoal">Next Cycle's Savings Goal (₱)</label>
            <input type="number" step="0.01" name="savingsGoal" id="savingsGoal" value="<?= $user['savingsGoal'] ?>" required>
        </div>

        <?php if ($leftoverFunds > 0): ?>
            <button type="button" class="action-btn carry-over" onclick="openModal()">
                <span class="action-title">💰 Send Surplus to Vault</span>
                <span class="action-desc">Add your ₱<?= number_format($leftoverFunds, 2) ?> leftover funds to your emergency savings and start fresh.</span>
            </button>
        <?php elseif ($leftoverFunds < 0): ?>
            <button type="button" class="action-btn carry-debt" onclick="openModal()">
                <span class="action-title">📉 Absorb Debt</span>
                <span class="action-desc">Your ₱<?= number_format(abs($leftoverFunds), 2) ?> debt will be carried over into your new cycle as an expense.</span>
            </button>
        <?php else: ?>
            <button type="button" class="action-btn neutral-reset" onclick="openModal()">
                <span class="action-title">♻️ Start Fresh</span>
                <span class="action-desc">You broke exactly even. Begin your next tracking cycle.</span>
            </button>
        <?php endif; ?>
    </form>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Finalize Cycle?</h3>
        <p>This action will lock in your current cycle results and restart your timeline. Old transactions will be permanently archived.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="submitForm()">Yes, proceed</button>
        </div>
    </div>
</div>

<script>
    function openModal() { document.getElementById('confirmModal').style.display = 'flex'; }
    function closeModal() { document.getElementById('confirmModal').style.display = 'none'; }
    function submitForm() { document.getElementById('resetForm').submit(); }
</script>

</body>
</html>