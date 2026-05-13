<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST['amount'];
    $category = $_POST['category'];
    $note = $_POST['note'];
    $type = 'Income'; // Hardcoded as Income

    // Insert into the TRANSACTION table
    $sql = "INSERT INTO TRANSACTION (author_UID, amount, type, category, note, createdAt) VALUES (?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("idsss", $uid, $amount, $type, $category, $note);

    if ($stmt->execute()) {
        header("Location: user_dashboard.php?msg=income_added");
        exit();
    } else {
        $error = "Something went wrong. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Gain | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --success: #22c55e;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
            --border: #e2e8f0;
        }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .form-card {
            background: var(--card);
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            border-top: 8px solid var(--success);
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); text-align: center; }
        p { color: #64748b; font-size: 0.9rem; text-align: center; margin-bottom: 25px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 8px; color: #475569; }

        .money-input {
            position: relative;
            display: flex;
            align-items: center;
        }

        .money-input span {
            position: absolute;
            left: 15px;
            font-weight: 700;
            color: var(--success);
            font-size: 1.2rem;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            padding-left: 35px;
            border: 1px solid var(--border);
            border-radius: 10px;
            box-sizing: border-box;
            font-size: 1rem;
            font-family: inherit;
        }

        textarea { padding-left: 12px; height: 80px; resize: none; }

        .btn-submit {
            width: 100%;
            background-color: var(--success);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-submit:hover { background-color: #16a34a; transform: translateY(-1px); }

        .cancel-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #94a3b8;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>

<div class="form-card">
    <h2>Log a Gain 💰</h2>
    <p>Received some extra cash? Add it to your current balance.</p>

    <?php if(isset($error)): ?>
        <p style="color: var(--danger);"><?= $error ?></p>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Amount (₱)</label>
            <div class="money-input">
                <span>₱</span>
                <input type="number" step="0.01" name="amount" placeholder="0.00" required autofocus>
            </div>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category" required style="padding-left: 12px;">
                <option value="Allowance">💵 Allowance</option>
                <option value="Gift">🎁 Gift</option>
                <option value="Side Hustle">🛠️ Side Hustle</option>
                <option value="Refund">↩️ Refund</option>
                <option value="Other">✨ Other</option>
            </select>
        </div>

        <div class="form-group">
            <label>Note (Optional)</label>
            <textarea name="note" placeholder="Where did this money come from?"></textarea>
        </div>

        <button type="submit" class="btn-submit">Add to Budget</button>
        <a href="user_dashboard.php" class="cancel-link">Cancel</a>
    </form>
</div>

</body>
</html>