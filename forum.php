<?php
require 'db_connect.php';
session_start();

// Security: Ensure user is logged in
if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];
$role = $_SESSION['role'];

// --- 1. Handle creating a post (End User Only) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_post'])) {
    
    // Backend Role Check
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
    // Increment report count
    $conn->query("UPDATE FORUM_POST SET reportCount = reportCount + 1 WHERE FPID = $reportId");
    // Flag if reports reach 3
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
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; }

        /* Compose Card Styles */
        .compose-card {
            background: var(--card);
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 40px;
            border: 1px solid var(--border);
        }

        .compose-card h3 { margin-top: 0; color: var(--primary); font-size: 1.1rem; }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 0.9rem;
            font-family: inherit;
        }

        textarea { grid-column: span 2; height: 80px; resize: none; }

        .btn-post {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-post:hover { background: #0f172a; }

        /* Feed Styles */
        .feed-title { font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 20px; }

        .post-card {
            background: var(--card);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            border-left: 4px solid var(--accent);
            position: relative;
        }

        /* Flagged Styling */
        .post-card.flagged {
            background: #fff5f5;
            border-left-color: var(--danger);
        }

        .post-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; }
        .post-header h4 { margin: 0; font-size: 1.1rem; color: var(--primary); }
        
        .meta { font-size: 0.8rem; color: #64748b; margin-bottom: 15px; }
        .post-content { line-height: 1.6; margin-bottom: 15px; }

        .badge {
            background: #f1f5f9;
            color: #475569;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .footer-actions {
            display: flex;
            gap: 15px;
            border-top: 1px solid #f1f5f9;
            padding-top: 15px;
            margin-top: 10px;
        }

        .action-link {
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            transition: 0.2s;
        }

        .action-link:hover { color: var(--danger); }
        .admin-del { color: var(--danger); font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0;">Bayanihan Tips 💡</h2>
        <a href="user_dashboard.php" class="back-link">← Dashboard</a>
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
            <p style="font-size: 0.9rem; color: #64748b; margin: 0;">
                You are currently in <strong>Moderator Mode</strong>. Posting is reserved for the student community. 
                You can monitor, flag, or delete posts from the feed below.
            </p>
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

            <div class="post-content">
                <?= nl2br(htmlspecialchars($row['content'])) ?>
            </div>

            <?php if(!empty($row['tags'])): ?>
                <div style="margin-bottom: 15px;">
                    <?php 
                        $tags = explode(',', $row['tags']);
                        foreach($tags as $tag): 
                    ?>
                        <span style="color: var(--accent); font-size: 0.8rem; margin-right: 8px;">#<?= trim(htmlspecialchars($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="footer-actions">
                <a href="forum.php?report_id=<?= $row['FPID'] ?>" class="action-link" onclick="return confirm('Report this post?')">⚠️ Report</a>
                
                <?php if($role === 'Admin'): ?>
                    <a href="delete_post.php?id=<?= $row['FPID'] ?>" class="action-link admin-del" onclick="return confirm('ADMIN: Delete this post permanently?')">🗑 Delete</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

</body>
</html>