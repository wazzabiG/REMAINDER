<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// Fetch ALL history, sorted by newest first
$historySql = "SELECT TID, amount, type, category, expenseType, note, createdAt, isArchived 
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
    <title>Transaction History | Remainder</title>
    <style>
        :root { --primary: #1e293b; --accent: #3b82f6; --success: #22c55e; --danger: #ef4444; --bg: #f8fafc; --card: #ffffff; --text: #334155; --border: #e2e8f0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; }
        
        .history-card { background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; }
        .card-header { padding: 20px; border-bottom: 1px solid var(--border); background: #f8fafc; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { margin: 0; color: var(--primary); font-size: 1.1rem; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 15px 20px; font-size: 0.8rem; text-transform: uppercase; color: #64748b; border-bottom: 1px solid var(--border); background: white; }
        td { padding: 15px 20px; border-bottom: 1px solid var(--border); }
        tr:last-child td { border-bottom: none; }
        
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; }
        .badge-need { background: #dcfce7; color: #166534; }
        .badge-want { background: #ffedd5; color: #9a3412; }
        .badge-gain { background: #dbeafe; color: #1e40af; }
        .badge-vault { background: #fef08a; color: #854d0e; }
        
        .status-badge { font-size: 0.7rem; padding: 3px 6px; border-radius: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; }
        .status-active { background: #eff6ff; color: #1e40af; }
        .status-archived { background: #f1f5f9; color: #64748b; }

        .empty-state { text-align: center; padding: 40px; color: #94a3b8; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0;">Full Transaction Ledger 📚</h2>
        <a href="user_dashboard.php" class="back-link">← Back to Dashboard</a>
    </header>

    <div class="history-card">
        <div class="card-header">
            <h3>All Records</h3>
            <span style="font-size: 0.85rem; color: #64748b;">Showing active and archived cycles</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Category</th>
                    <th>Classification</th>
                    <th>Amount</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                <?php if($historyResult->num_rows > 0): ?>
                    <?php while($row = $historyResult->fetch_assoc()): ?>
                    <tr style="<?= $row['isArchived'] ? 'opacity: 0.75;' : '' ?>">
                        <td style="font-size: 0.85rem; color: #64748b; white-space: nowrap;">
                            <?= date('M d, Y', strtotime($row['createdAt'])) ?><br>
                            <span style="font-size: 0.75rem;"><?= date('g:i A', strtotime($row['createdAt'])) ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?= $row['isArchived'] ? 'status-archived' : 'status-active' ?>">
                                <?= $row['isArchived'] ? 'Archived' : 'Active' ?>
                            </span>
                        </td>
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
                        <td style="font-size: 0.85rem; color: #64748b; max-width: 250px;">
                            <?= htmlspecialchars($row['note'] ?? '-') ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan=\"6\" class=\"empty-state\">No records found in the database.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>