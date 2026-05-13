<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- 1. Fetch Cycle Start Date ---
$sql = "SELECT cycleStartDate FROM END_USER WHERE UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// --- 2. Calculate Top 3 Spend Categories ---
$insightSql = "SELECT category, SUM(amount) as totalCategorySpend 
               FROM TRANSACTION 
               WHERE author_UID = ? AND isDeleted = FALSE AND createdAt >= ? 
               GROUP BY category 
               ORDER BY totalCategorySpend DESC 
               LIMIT 3";
$insightStmt = $conn->prepare($insightSql);
$insightStmt->bind_param("is", $uid, $user['cycleStartDate']);
$insightStmt->execute();
$insights = $insightStmt->get_result();

// Get the highest spending amount to scale the progress bars
$all_insights = [];
$max_spend = 0;
while($row = $insights->fetch_assoc()) {
    $all_insights[] = $row;
    if ($row['totalCategorySpend'] > $max_spend) {
        $max_spend = $row['totalCategorySpend'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights | Lutong Bahay</title>
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
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 600px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; }

        .insight-card {
            background: var(--card);
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; }

        .stat-item {
            margin-bottom: 25px;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .category-name { color: var(--primary); font-size: 1rem; }
        .amount { color: var(--text); }

        /* Progress Bar Styling */
        .progress-bg {
            background: #f1f5f9;
            height: 12px;
            border-radius: 6px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), #60a5fa);
            border-radius: 6px;
            transition: width 0.5s ease-in-out;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }

        .rank-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 4px;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0;">Spending Insights</h2>
        <a href="user_dashboard.php" class="back-link">← Dashboard</a>
    </header>

    <div class="insight-card">
        <h2>Top 3 Categories</h2>
        <p class="subtitle">A breakdown of where your money went this cycle.</p>

        <?php if(count($all_insights) > 0): ?>
            <?php foreach($all_insights as $index => $row): 
                // Calculate percentage relative to the highest spender
                $percentage = ($row['totalCategorySpend'] / $max_spend) * 100;
            ?>
                <div class="stat-item">
                    <div class="rank-badge">Rank #<?= $index + 1 ?></div>
                    <div class="stat-header">
                        <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                        <span class="amount">₱<?= number_format($row['totalCategorySpend'], 2) ?></span>
                    </div>
                    <div class="progress-bg">
                        <div class="progress-fill" style="width: <?= $percentage ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No spending data found for this cycle.</p>
                <p style="font-size: 0.8rem;">Log some expenses to see your insights!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>