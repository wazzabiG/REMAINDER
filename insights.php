<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- 1. Fetch User Cycle Data ---
$sql = "SELECT cycleStartDate, totalAllowance FROM END_USER WHERE UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// --- 2. Fetch Total Income (Gains) ---
$incomeSql = "SELECT SUM(amount) as totalIncome FROM TRANSACTION WHERE author_UID = ? AND type = 'Income' AND isDeleted = FALSE AND createdAt >= ?";
$incomeStmt = $conn->prepare($incomeSql);
$incomeStmt->bind_param("is", $uid, $user['cycleStartDate']);
$incomeStmt->execute();
$totalIncome = $incomeStmt->get_result()->fetch_assoc()['totalIncome'] ?? 0;

// --- 3. Fetch Category Breakdown with Need/Want Split ---
$insightSql = "SELECT category, 
               SUM(amount) as totalCategorySpend,
               SUM(CASE WHEN expenseType = 'Need' THEN amount ELSE 0 END) as needSpend,
               SUM(CASE WHEN expenseType = 'Want' THEN amount ELSE 0 END) as wantSpend
               FROM TRANSACTION 
               WHERE author_UID = ? AND type = 'Expense' AND isDeleted = FALSE AND createdAt >= ? 
               GROUP BY category 
               ORDER BY totalCategorySpend DESC";
$insightStmt = $conn->prepare($insightSql);
$insightStmt->bind_param("is", $uid, $user['cycleStartDate']);
$insightStmt->execute();
$insights = $insightStmt->get_result();

$top_categories = [];
$other_categories = [];
$max_spend = 0;
$total_need = 0;
$total_want = 0;
$count = 0;

while($row = $insights->fetch_assoc()) {
    // Find the highest single category spend to scale the bars properly
    if ($row['totalCategorySpend'] > $max_spend) {
        $max_spend = $row['totalCategorySpend'];
    }
    
    // Aggregate global needs and wants
    $total_need += $row['needSpend'];
    $total_want += $row['wantSpend'];
    
    // Split into Top 3 and Others
    if ($count < 3) {
        $top_categories[] = $row;
    } else {
        $other_categories[] = $row;
    }
    $count++;
}

$total_spent = $total_need + $total_want;
$total_budget = $user['totalAllowance'] + $totalIncome;
$leftoverFunds = $total_budget - $total_spent;

// --- 4. Determine if Warning Should Trigger ---
// Warning triggers if remaining budget is less than 30% AND Want spending is higher than Need spending
$show_warning = false;
if ($total_budget > 0) {
    $budgetRatio = $leftoverFunds / $total_budget;
    if ($budgetRatio < 0.30 && $total_want > $total_need) {
        $show_warning = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insights | Remainder</title>
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

        body { font-family: 'Inter', -apple-system, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; display: flex; justify-content: center; }
        .container { width: 100%; max-width: 600px; }
        
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; }

        .warning-banner { background: #fef2f2; border-left: 4px solid var(--danger); padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; color: #991b1b; }
        .warning-banner h4 { margin: 0 0 5px 0; display: flex; align-items: center; gap: 8px; }
        .warning-banner p { margin: 0; font-size: 0.85rem; line-height: 1.5; }

        .insight-card { background: var(--card); padding: 30px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); margin-bottom: 25px; }
        .insight-card h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.3rem; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 25px; }

        /* Overall Need vs Want Progress Bar */
        .overall-bar-container { margin-bottom: 10px; }
        .overall-stats { display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; margin-bottom: 8px; }
        .stat-need { color: #166534; }
        .stat-want { color: #9a3412; }
        
        .progress-bg-large { background: #f1f5f9; height: 16px; border-radius: 8px; overflow: hidden; display: flex; }
        
        /* Category Item Styles */
        .stat-item { margin-bottom: 25px; }
        .stat-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-weight: 600; align-items: flex-end; }
        .category-name { color: var(--primary); font-size: 1rem; }
        .amount { color: var(--text); }
        .amount-subtext { font-size: 0.75rem; color: #64748b; font-weight: normal; margin-left: 8px; }

        .progress-bg { background: #f1f5f9; height: 10px; border-radius: 5px; overflow: hidden; display: flex; }
        .need-fill { background: var(--success); transition: width 0.5s ease-in-out; }
        .want-fill { background: var(--warning); transition: width 0.5s ease-in-out; }

        .empty-state { text-align: center; padding: 30px 20px; color: #94a3b8; }
        .rank-badge { display: inline-block; background: #eff6ff; color: #1e40af; font-size: 0.7rem; padding: 3px 8px; border-radius: 4px; margin-bottom: 5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }

        /* Other Categories (Smaller) */
        .other-cats .stat-item { margin-bottom: 15px; }
        .other-cats .category-name { font-size: 0.9rem; }
        .other-cats .amount { font-size: 0.9rem; }
        .other-cats .progress-bg { height: 6px; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0;">Spending Insights</h2>
        <a href="user_dashboard.php" class="back-link">← Dashboard</a>
    </header>

    <?php if($show_warning): ?>
        <div class="warning-banner">
            <h4>⚠️ Budget Alert: High "Want" Spending</h4>
            <p>Your remaining balance is running low (below 30%), and the majority of your spending this cycle has been on Wants instead of Needs. Consider cutting back on non-essential expenses until your next cycle resets.</p>
        </div>
    <?php endif; ?>

    <div class="insight-card">
        <h2>Overall: Needs vs. Wants</h2>
        <?php if($total_spent > 0): 
            $needPercent = ($total_need / $total_spent) * 100;
            $wantPercent = ($total_want / $total_spent) * 100;
        ?>
            <div class="overall-bar-container">
                <div class="overall-stats">
                    <span class="stat-need">Needs: ₱<?= number_format($total_need, 2) ?> (<?= round($needPercent) ?>%)</span>
                    <span class="stat-want">Wants: ₱<?= number_format($total_want, 2) ?> (<?= round($wantPercent) ?>%)</span>
                </div>
                <div class="progress-bg-large">
                    <div class="need-fill" style="width: <?= $needPercent ?>%;"></div>
                    <div class="want-fill" style="width: <?= $wantPercent ?>%;"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding: 10px;">No spending recorded yet.</div>
        <?php endif; ?>
    </div>

    <div class="insight-card">
        <h2>Top 3 Categories</h2>
        <p class="subtitle">Your biggest expense areas this cycle.</p>

        <?php if(count($top_categories) > 0): ?>
            <?php foreach($top_categories as $index => $row): 
                $needWidth = ($row['needSpend'] / $max_spend) * 100;
                $wantWidth = ($row['wantSpend'] / $max_spend) * 100;
            ?>
                <div class="stat-item">
                    <div class="rank-badge">Rank #<?= $index + 1 ?></div>
                    <div class="stat-header">
                        <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                        <div>
                            <span class="amount">₱<?= number_format($row['totalCategorySpend'], 2) ?></span>
                        </div>
                    </div>
                    <div class="progress-bg">
                        <div class="need-fill" style="width: <?= $needWidth ?>%;" title="Need: ₱<?= number_format($row['needSpend'], 2) ?>"></div>
                        <div class="want-fill" style="width: <?= $wantWidth ?>%;" title="Want: ₱<?= number_format($row['wantSpend'], 2) ?>"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <p>No spending data found.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if(count($other_categories) > 0): ?>
        <div class="insight-card other-cats">
            <h2>Other Categories</h2>
            <p class="subtitle" style="margin-bottom: 15px;">Remaining expenses.</p>
            
            <?php foreach($other_categories as $row): 
                $needWidth = ($row['needSpend'] / $max_spend) * 100;
                $wantWidth = ($row['wantSpend'] / $max_spend) * 100;
            ?>
                <div class="stat-item">
                    <div class="stat-header">
                        <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                        <span class="amount">₱<?= number_format($row['totalCategorySpend'], 2) ?></span>
                    </div>
                    <div class="progress-bg">
                        <div class="need-fill" style="width: <?= $needWidth ?>%;"></div>
                        <div class="want-fill" style="width: <?= $wantWidth ?>%;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>