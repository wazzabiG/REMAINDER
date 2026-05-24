<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || !isset($_GET['id'])) {
    header("Location: forum.php");
    exit();
}

$uid = $_SESSION['UID'];
$fpid = intval($_GET['id']);
$role = $_SESSION['role'] ?? 'End User';

// Handle adding a comment or a reply
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_comment'])) {
    $body = trim($_POST['body']);
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : NULL;
    
    if (!empty($body)) {
        $stmt = $conn->prepare("INSERT INTO forum_comment (FPID, author_UID, body, parent_FCID, createdAt) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisi", $fpid, $uid, $body, $parent_id);
        $stmt->execute();
        header("Location: view_post.php?id=" . $fpid);
        exit();
    }
}

// Fetch Main Post
$postSql = "
    SELECT F.*, U.firstName,
           (SELECT COUNT(*) FROM post_like WHERE FPID = F.FPID) as likes,
           (SELECT COUNT(*) FROM post_like WHERE FPID = F.FPID AND UID = ?) as userLiked
    FROM FORUM_POST F 
    JOIN USER U ON F.author_UID = U.UID 
    WHERE F.FPID = ?
";
$stmt = $conn->prepare($postSql);
$stmt->bind_param("ii", $uid, $fpid);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    header("Location: forum.php");
    exit();
}

// Fetch Comments with Parent Info
$commentSql = "
    SELECT C.*, U.firstName as authorName, 
           PC.author_UID as parentAuthorID, PU.firstName as parentAuthorName,
           (SELECT COUNT(*) FROM comment_like WHERE FCID = C.FCID) as likes,
           (SELECT COUNT(*) FROM comment_like WHERE FCID = C.FCID AND UID = ?) as userLiked
    FROM forum_comment C
    JOIN USER U ON C.author_UID = U.UID
    LEFT JOIN forum_comment PC ON C.parent_FCID = PC.FCID
    LEFT JOIN USER PU ON PC.author_UID = PU.UID
    WHERE C.FPID = ?
    ORDER BY C.createdAt ASC
";
$stmtC = $conn->prepare($commentSql);
$stmtC->bind_param("ii", $uid, $fpid);
$stmtC->execute();
$comments = $stmtC->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> | Remainder</title>
    <style>
        :root { --primary: #1e293b; --accent: #3b82f6; --danger: #ef4444; --bg: #f8fafc; --card: #ffffff; --text: #334155; --border: #e2e8f0; }
        body { font-family: 'Inter', -apple-system, sans-serif; background-color: var(--bg); color: var(--text); margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .back-link { text-decoration: none; color: var(--accent); font-weight: 600; font-size: 0.9rem; margin-bottom: 20px; display: inline-block; }
        
        .main-post { background: var(--card); padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px; border-top: 4px solid var(--accent); }
        .main-post h2 { margin: 0 0 10px 0; color: var(--primary); }
        .meta { font-size: 0.85rem; color: #64748b; margin-bottom: 20px; }
        .content { line-height: 1.6; font-size: 1.05rem; margin-bottom: 20px; }
        
        .action-bar { display: flex; gap: 15px; border-top: 1px solid var(--border); padding-top: 15px; align-items: center; }
        .btn-like { text-decoration: none; font-weight: 600; font-size: 0.9rem; padding: 8px 16px; border-radius: 8px; border: 1px solid var(--border); color: var(--text); background: #f8fafc; transition: 0.2s; }
        .btn-like.liked { background: #eff6ff; border-color: var(--accent); color: var(--accent); }
        
        .comments-section { margin-top: 40px; }
        h3 { color: var(--primary); margin-bottom: 20px; }
        
        .comment-box { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 15px; }
        .comment-header { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 10px; color: #64748b; }
        .comment-header strong { color: var(--primary); font-size: 0.95rem; }
        .reply-tag { color: var(--accent); font-weight: 600; margin-right: 5px; }
        
        .comment-actions { display: flex; gap: 15px; margin-top: 15px; font-size: 0.85rem; font-weight: 600; }
        .comment-actions a { color: #64748b; text-decoration: none; cursor: pointer; }
        .comment-actions a.liked-text { color: var(--accent); }
        .comment-actions a:hover { color: var(--accent); }

        .compose-area { background: white; padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-top: 30px; }
        .replying-to-banner { background: #eff6ff; color: var(--accent); padding: 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; margin-bottom: 15px; display: none; justify-content: space-between; align-items: center; }
        .cancel-reply { cursor: pointer; color: #94a3b8; }
        textarea { width: 100%; padding: 15px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; font-family: inherit; resize: none; margin-bottom: 10px; }
        .btn-submit { background: var(--primary); color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    </style>
</head>
<body>

<div class="container">
    <a href="forum.php" class="back-link">← Back to Forum</a>

    <div class="main-post">
        <h2><?= htmlspecialchars($post['title']) ?></h2>
        <div class="meta">
            Posted by <strong><?= htmlspecialchars($post['firstName']) ?></strong> in <?= htmlspecialchars($post['category']) ?> • <?= date('M d, g:i A', strtotime($post['createdAt'])) ?>
        </div>
        <div class="content">
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>
        
        <div class="action-bar">
            <a href="toggle_like.php?type=post&id=<?= $post['FPID'] ?>&return=view_post.php?id=<?= $post['FPID'] ?>" 
               class="btn-like <?= $post['userLiked'] ? 'liked' : '' ?>">
                <?= $post['userLiked'] ? '❤️' : '🤍' ?> <?= $post['likes'] ?> Likes
            </a>
            <?php if($role === 'Admin'): ?>
                <a href="delete_post.php?id=<?= $post['FPID'] ?>" style="color: var(--danger); font-size: 0.85rem; margin-left: auto; font-weight: 600; text-decoration: none;">🗑 Delete Post</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="comments-section">
        <h3>Discussion</h3>

        <?php if($comments->num_rows > 0): ?>
            <?php while($c = $comments->fetch_assoc()): ?>
                <div class="comment-box" id="comment-<?= $c['FCID'] ?>">
                    <div class="comment-header">
                        <span><strong><?= htmlspecialchars($c['authorName']) ?></strong></span>
                        <span><?= date('M d, g:i A', strtotime($c['createdAt'])) ?></span>
                    </div>
                    <div style="line-height: 1.5;">
                        <?php if($c['parent_FCID']): ?>
                            <span class="reply-tag">@<?= htmlspecialchars($c['parentAuthorName']) ?></span>
                        <?php endif; ?>
                        <?= nl2br(htmlspecialchars($c['body'])) ?>
                    </div>
                    
                    <div class="comment-actions">
                        <a href="toggle_like.php?type=comment&id=<?= $c['FCID'] ?>&return=view_post.php?id=<?= $post['FPID'] ?>" 
                           class="<?= $c['userLiked'] ? 'liked-text' : '' ?>">
                            <?= $c['likes'] ?> <?= $c['likes'] == 1 ? 'Like' : 'Likes' ?>
                        </a>
                        <?php if($role === 'End User'): ?>
                            <a onclick="setupReply(<?= $c['FCID'] ?>, '<?= htmlspecialchars(addslashes($c['authorName'])) ?>')">💬 Reply</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #64748b; font-size: 0.9rem;">No comments yet. Start the conversation!</p>
        <?php endif; ?>

        <?php if($role === 'End User'): ?>
            <div class="compose-area" id="reply-box">
                <div class="replying-to-banner" id="reply-banner">
                    <span id="reply-text"></span>
                    <span class="cancel-reply" onclick="cancelReply()">✖</span>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="submit_comment" value="1">
                    <input type="hidden" name="parent_id" id="parent_id_input" value="">
                    <textarea name="body" rows="4" placeholder="Add to the discussion..." required></textarea>
                    <button type="submit" class="btn-submit">Post Comment</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function setupReply(commentId, authorName) {
        document.getElementById('parent_id_input').value = commentId;
        document.getElementById('reply-text').innerText = 'Replying to @' + authorName;
        document.getElementById('reply-banner').style.display = 'flex';
        document.getElementById('reply-box').scrollIntoView({ behavior: 'smooth' });
    }

    function cancelReply() {
        document.getElementById('parent_id_input').value = '';
        document.getElementById('reply-banner').style.display = 'none';
    }
</script>

</body>
</html>