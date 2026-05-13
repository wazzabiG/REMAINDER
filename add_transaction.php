<?php
require 'db_connect.php';
session_start();

// Security: End Users only
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $author_UID = $_SESSION['UID'];
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $expenseType = $_POST['expenseType'];
    $note = !empty($_POST['note']) ? $_POST['note'] : NULL;
    $type = 'Expense'; // Explicitly set to Expense so the dashboard math catches it

    // Fixed SQL: We use NOW() directly and perfectly match the column count
    $stmt = $conn->prepare("INSERT INTO TRANSACTION (author_UID, amount, type, category, note, expenseType, createdAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("idssss", $author_UID, $amount, $type, $category, $note, $expenseType);
    
    if ($stmt->execute()) {
        header("Location: user_dashboard.php");
        exit();
    } else {
        $error = "Error logging transaction: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Expense | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --success: #22c55e;
            --warning: #f59e0b;
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

        .form-card {
            background: var(--card);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; text-align: center; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 25px; text-align: center; }

        .error-msg {
            background-color: #fee2e2;
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
        }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; color: #475569; }

        /* Amount Input Styling */
        .amount-input {
            position: relative;
            display: flex;
            align-items: center;
        }
        .amount-input span {
            position: absolute;
            left: 15px;
            font-weight: 700;
            color: var(--primary);
        }
        .amount-input input {
            padding-left: 35px;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

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

        /* Custom Radio/Toggle Style for Need/Want */
        .type-selector {
            display: flex;
            gap: 10px;
        }
        .type-option {
            flex: 1;
            position: relative;
        }
        .type-option input {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }
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
        
        /* Green for Need, Orange for Want */
        #need:checked + .type-label { background: #dcfce7; color: #166534; border-color: var(--success); }
        #want:checked + .type-label { background: #ffedd5; color: #9a3412; border-color: var(--warning); }

        .btn-submit {
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

        .btn-submit:hover { background-color: #0f172a; transform: translateY(-1px); }

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

<div class="form-card">
    <h2>Log Expense</h2>
    <p class="subtitle">Where did your money go today?</p>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Amount Spent</label>
            <div class="amount-input">
                <span>₱</span>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category" required>
                <option value="Food">🍱 Food & Drinks</option>
                <option value="Transport">🚌 Transport</option>
                <option value="Academic">📚 Academic / School</option>
                <option value="Social">🎮 Social / Fun</option>
                <option value="Other">⚙️ Other</option>
            </select>
        </div>

        <div class="form-group">
            <label>Was this a Necessity?</label>
            <div class="type-selector">
                <div class="type-option">
                    <input type="radio" id="need" name="expenseType" value="Need" required checked>
                    <label for="need" class="type-label">Need</label>
                </div>
                <div class="type-option">
                    <input type="radio" id="want" name="expenseType" value="Want">
                    <label for="want" class="type-label">Want</label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Note (Optional)</label>
            <textarea name="note" placeholder="What was this for? (e.g. Lunch at CIT-U)"></textarea>
        </div>

        <button type="submit" class="btn-submit">Save Transaction</button>
        <a href="user_dashboard.php" class="cancel-link">Cancel and go back</a>
    </form>
</div>

</body>
</html>