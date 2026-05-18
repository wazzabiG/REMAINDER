<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];
$role = $_SESSION['role'];

// --- 1. Handle creating a post ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_post'])) {
    if ($role !== 'End User') {
        die("Unauthorized Action: Posting is reserved for students.");
    }
    $title = $_POST['title'];
    $content = $_POST['content'];
    $category = $_POST['category'];
    $tags = $_POST['tags'];
    $city = $_POST['city'];

    $insert = $conn->prepare("INSERT INTO FORUM_POST (author_UID, title, content, category, tags, city, createdAt) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insert->bind_param("isssss", $uid, $title, $content, $category, $tags, $city);
    $insert->execute();
    header("Location: forum.php");
    exit();
}

// --- 2. Handle reporting a post ---
if (isset($_GET['report_id'])) {
    $reportId = $_GET['report_id'];
    $conn->query("UPDATE FORUM_POST SET reportCount = reportCount + 1 WHERE FPID = $reportId");
    $conn->query("UPDATE FORUM_POST SET isFlaggedForWarning = 1 WHERE FPID = $reportId AND reportCount >= 3");
    header("Location: forum.php");
    exit();
}

// --- 3. Fetch all posts ---
$posts = $conn->query("SELECT F.*, U.firstName FROM FORUM_POST F JOIN USER U ON F.author_UID = U.UID ORDER BY createdAt DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum | Lutong Bahay</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --danger: #ef4444;
            --warning: #f59e0b;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
            --border: #e2e8f0;
        }

        body { font-family: 'Inter', -apple-system, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; }

        .compose-card { background: var(--card); padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 40px; border: 1px solid var(--border); }
        .compose-card h3 { margin-top: 0; color: var(--primary); font-size: 1.1rem; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        input, textarea, select { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; font-size: 0.9rem; font-family: inherit; }
        textarea { grid-column: span 2; height: 80px; resize: none; }
        .btn-post { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-post:hover { background: #0f172a; }

        .feed-title { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; }
        .post-card { background: var(--card); padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; border-left: 4px solid var(--accent); }
        .post-card.flagged { background: #fff5f5; border-left-color: var(--danger); }
        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .post-header h4 { margin: 0; font-size: 1.1rem; color: var(--primary); }
        .meta { font-size: 0.8rem; color: #64748b; margin-bottom: 15px; }
        .post-content { line-height: 1.6; margin-bottom: 15px; }
        .badge { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
        .footer-actions { display: flex; gap: 15px; border-top: 1px solid #f1f5f9; padding-top: 15px; margin-top: 10px; }
        .action-link { text-decoration: none; font-size: 0.8rem; font-weight: 600; color: #94a3b8; cursor: pointer; transition: 0.2s; }
        .action-link:hover { color: var(--warning); }
        .admin-del { color: var(--danger); }
        .admin-del:hover { color: #991b1b; }

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
        .modal-box h3 { margin: 0 0 10px 0; }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .btn-confirm-report { background: var(--warning); color: white; }
        .btn-confirm-delete { background: var(--danger); color: white; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0;">Bayanihan Tips 💡</h2>
        <a href="<?= $role === 'Admin' ? 'index.php' : 'user_dashboard.php' ?>" class="back-link">← <?= $role === 'Admin' ? 'Admin Panel' : 'Dashboard' ?></a>
    </header>

    <?php if ($role === 'End User'): ?>
        <div class="compose-card">
            <h3>Share a Saving Tip</h3>
            <form method="POST">
                <input type="hidden" name="create_post" value="1">
                <div class="form-grid">
                    <input type="text" name="title" placeholder="Post Title" required style="grid-column: span 2;">
                    <textarea name="content" placeholder="Share your money-saving tip..." required></textarea>
                    <select name="category" required>
                        <option value="Food">🍱 Food</option>
                        <option value="Transport">🚌 Transport</option>
                        <option value="Housing">🏠 Housing</option>
                        <option value="Academic">📚 Academic</option>
                    </select>
                    <input type="text" name="city" placeholder="City (Optional)">
                    <input type="text" name="tags" placeholder="Tags (e.g. cheap, student-hacks)" style="grid-column: span 2;">
                </div>
                <button type="submit" class="btn-post">Post Tip</button>
            </form>
        </div>
    <?php else: ?>
        <div class="compose-card" style="border-left: 4px solid var(--accent); background: #f1f5f9;">
            <h3 style="color: var(--primary); margin-bottom: 5px;">Moderator View 🛡️</h3>
            <p style="font-size: 0.9rem; color: #64748b; margin: 0;">You are currently in <strong>Moderator Mode</strong>. Posting is reserved for the student community.</p>
        </div>
    <?php endif; ?>

    <div class="feed-title">Recent Community Wisdom</div>

    <?php while($row = $posts->fetch_assoc()): ?>
        <div class="post-card <?= $row['isFlaggedForWarning'] ? 'flagged' : '' ?>">
            <div class="post-header">
                <h4><?= htmlspecialchars($row['title']) ?></h4>
                <span class="badge"><?= htmlspecialchars($row['category']) ?></span>
            </div>
            
            <div class="meta">
                Shared by <strong><?= htmlspecialchars($row['firstName']) ?></strong> 
                in <?= htmlspecialchars($row['city'] ?? 'General') ?> 
                • <?= date('M d, Y', strtotime($row['createdAt'])) ?>
            </div>

            <div class="post-content"><?= nl2br(htmlspecialchars($row['content'])) ?></div>

            <?php if(!empty($row['tags'])): ?>
                <div style="margin-bottom: 15px;">
                    <?php foreach(explode(',', $row['tags']) as $tag): ?>
                        <span style="color: var(--accent); font-size: 0.8rem; margin-right: 8px;">#<?= trim(htmlspecialchars($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="footer-actions">
                <a class="action-link" onclick="openActionModal('report', 'forum.php?report_id=<?= $row['FPID'] ?>')">⚠️ Report</a>
                <?php if($role === 'Admin'): ?>
                    <a class="action-link admin-del" onclick="openActionModal('delete', 'delete_post.php?id=<?= $row['FPID'] ?>')">🗑 Delete</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<div id="actionModal" class="modal-overlay">
    <div class="modal-box">
        <h3 id="modalTitle">Action Required</h3>
        <p id="modalText">Are you sure you want to proceed?</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeActionModal()">Cancel</button>
            <button id="modalConfirmBtn" onclick="executeAction()">Confirm</button>
        </div>
    </div>
</div>

<script>
    let actionUrl = '';

    function openActionModal(type, url) {
        actionUrl = url;
        const title = document.getElementById('modalTitle');
        const text = document.getElementById('modalText');
        const btn = document.getElementById('modalConfirmBtn');

        if (type === 'report') {
            title.innerText = 'Report Post?';
            title.style.color = 'var(--warning)';
            text.innerText = 'Flag this post for inappropriate content? 3 reports will alert moderators.';
            btn.className = 'btn-confirm-report';
            btn.innerText = 'Yes, Report';
        } else if (type === 'delete') {
            title.innerText = 'ADMIN: Delete Post?';
            title.style.color = 'var(--danger)';
            text.innerText = 'Are you sure you want to permanently wipe this post from the database?';
            btn.className = 'btn-confirm-delete';
            btn.innerText = 'Wipe Post';
        }

        document.getElementById('actionModal').style.display = 'flex';
    }

    function closeActionModal() {
        document.getElementById('actionModal').style.display = 'none';
    }

    function executeAction() {
        if(actionUrl) {
            window.location.href = actionUrl;
        }
    }
</script>

</body>
</html>