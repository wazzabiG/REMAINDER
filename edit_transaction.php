<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || !isset($_GET['id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$tid = $_GET['id'];
$uid = $_SESSION['UID'];

// --- 1. Fetch full transaction details ---
$sql = "SELECT amount, category, expenseType, note, createdAt FROM TRANSACTION WHERE TID = ? AND author_UID = ? AND isDeleted = FALSE";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $tid, $uid);
$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die("Transaction not found or deleted.");
}

// --- 2. Check 24-hour window (86400 seconds) ---
$secondsPassed = time() - strtotime($transaction['createdAt']);
if ($secondsPassed > 86400) {
    // Elegant error handling instead of a raw die()
    $isLocked = true;
} else {
    $isLocked = false;
}

// --- 3. Handle Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$isLocked) {
    $newAmount = $_POST['amount'];
    $newCategory = $_POST['category'];
    $newType = $_POST['expenseType'];
    $newNote = !empty($_POST['note']) ? $_POST['note'] : NULL;

    $updateSql = "UPDATE TRANSACTION SET amount = ?, category = ?, expenseType = ?, note = ?, updatedAt = NOW() WHERE TID = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("dsssi", $newAmount, $newCategory, $newType, $newNote, $tid);
    $updateStmt->execute();
    
    header("Location: user_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction | Lutong Bahay</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --success: #22c55e;
            --warning: #f59e0b;
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

        .edit-card {
            background: var(--card);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; text-align: center; }
        
        .time-banner {
            background-color: #eff6ff;
            border: 1px solid #dbeafe;
            color: #1e40af;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 25px;
            text-align: center;
        }

        .locked-error {
            background-color: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #475569; }

        .amount-input {
            position: relative;
            display: flex;
            align-items: center;
        }
        .amount-input span { position: absolute; left: 15px; font-weight: 700; color: var(--primary); }
        .amount-input input { padding-left: 35px; font-size: 1.5rem; font-weight: 700; color: var(--primary); }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 1rem;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .type-selector { display: flex; gap: 10px; }
        .type-option { flex: 1; position: relative; }
        .type-option input { position: absolute; opacity: 0; cursor: pointer; }
        .type-label {
            display: block;
            text-align: center;
            padding: 12px;
            background: #f1f5f9;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.2s;
            border: 2px solid transparent;
        }
        
        #need:checked + .type-label { background: #dcfce7; color: #166534; border-color: var(--success); }
        #want:checked + .type-label { background: #ffedd5; color: #9a3412; border-color: var(--warning); }

        .btn-update {
            width: 100%;
            background-color: var(--primary);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 10px;
        }

        .btn-update:hover { background-color: #0f172a; }

        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.9rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="edit-card">
    <?php if ($isLocked): ?>
        <div class="locked-error">
            <h3>Window Closed</h3>
            <p>This transaction was logged on <strong><?= date('M d, g:i A', strtotime($transaction['createdAt'])) ?></strong>.</p>
            <p>The 24-hour edit window has expired.</p>
            <a href="user_dashboard.php" class="cancel-link" style="color: var(--accent);">Back to Dashboard</a>
        </div>
    <?php else: ?>
        <h2>Edit Transaction</h2>
        <div class="time-banner">
            Logged on <?= date('M d, g:i A', strtotime($transaction['createdAt'])) ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Amount (₱)</label>
                <div class="amount-input">
                    <span>₱</span>
                    <input type="number" step="0.01" name="amount" value="<?= $transaction['amount'] ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Category</label>
                <select name="category" required>
                    <option value="Food" <?= $transaction['category'] == 'Food' ? 'selected' : '' ?>>🍱 Food & Drinks</option>
                    <option value="Transport" <?= $transaction['category'] == 'Transport' ? 'selected' : '' ?>>🚌 Transport</option>
                    <option value="Academic" <?= $transaction['category'] == 'Academic' ? 'selected' : '' ?>>📚 Academic / School</option>
                    <option value="Social" <?= $transaction['category'] == 'Social' ? 'selected' : '' ?>>🎮 Social / Fun</option>
                    <option value="Other" <?= $transaction['category'] == 'Other' ? 'selected' : '' ?>>⚙️ Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Necessity Type</label>
                <div class="type-selector">
                    <div class="type-option">
                        <input type="radio" id="need" name="expenseType" value="Need" <?= $transaction['expenseType'] == 'Need' ? 'checked' : '' ?> required>
                        <label for="need" class="type-label">Need</label>
                    </div>
                    <div class="type-option">
                        <input type="radio" id="want" name="expenseType" value="Want" <?= $transaction['expenseType'] == 'Want' ? 'checked' : '' ?>>
                        <label for="want" class="type-label">Want</label>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>Note</label>
                <textarea name="note" placeholder="Update your note..."><?= htmlspecialchars($transaction['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-update">Save Changes</button>
            <a href="user_dashboard.php" class="cancel-link">Cancel</a>
        </form>
    <?php endif; ?>
</div>

</body>
</html>