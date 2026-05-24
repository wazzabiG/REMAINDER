<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// --- Handle Adding a Category ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO user_category (UID, name, type) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $uid, $name, $type);
        $stmt->execute();
        header("Location: manage_categories.php");
        exit();
    }
}

// --- Handle Editing a Category ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_category'])) {
    $catId = $_POST['cat_id'];
    $newName = trim($_POST['new_name']);
    $oldName = $_POST['old_name'];
    $type = $_POST['cat_type'];

    if (!empty($newName) && $newName !== $oldName) {
        $conn->begin_transaction();
        try {
            // 1. Update the actual category table
            $stmt1 = $conn->prepare("UPDATE user_category SET name = ? WHERE CatID = ? AND UID = ?");
            $stmt1->bind_param("sii", $newName, $catId, $uid);
            $stmt1->execute();

            // 2. Cascade the text change to all past transactions so the history doesn't break
            $stmt2 = $conn->prepare("UPDATE TRANSACTION SET category = ? WHERE category = ? AND type = ? AND author_UID = ?");
            $stmt2->bind_param("sssi", $newName, $oldName, $type, $uid);
            $stmt2->execute();

            $conn->commit();
            header("Location: manage_categories.php");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error updating category.";
        }
    }
}

// --- Handle Deleting a Category ---
if (isset($_GET['delete_id'])) {
    $catId = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM user_category WHERE CatID = ? AND UID = ?");
    $stmt->bind_param("ii", $catId, $uid);
    $stmt->execute();
    header("Location: manage_categories.php");
    exit();
}

// --- Fetch & Sort Categories ---
$stmt = $conn->prepare("SELECT * FROM user_category WHERE UID = ? ORDER BY name");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
$incomes = [];
while ($row = $result->fetch_assoc()) {
    if ($row['type'] == 'Expense') {
        $expenses[] = $row;
    } else {
        $incomes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | Remainder</title>
    <style>
        :root {
            --primary: #1e293b;
            --accent: #3b82f6;
            --success: #22c55e;
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

        .form-card { background: var(--card); padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); margin-bottom: 20px; border: 1px solid var(--border); }
        h3 { margin-top: 0; color: var(--primary); font-size: 1.2rem; margin-bottom: 15px; }
        
        .form-row { display: flex; gap: 10px; }
        input, select { flex: 1; padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; font-family: inherit; }
        input:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .btn-add { background: var(--primary); color: white; border: none; padding: 12px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .btn-add:hover { background: #0f172a; }

        /* Tabs Styling */
        .tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .tab-btn { flex: 1; padding: 12px; background: transparent; border: 2px solid var(--border); border-radius: 10px; font-weight: 600; color: #64748b; cursor: pointer; transition: 0.2s; }
        .tab-btn.active { background: var(--card); border-color: var(--accent); color: var(--accent); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .list-card { background: var(--card); border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; }
        .list-item { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--border); }
        .list-item:last-child { border-bottom: none; }
        
        .cat-name { font-weight: 600; color: var(--primary); font-size: 1.05rem; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; margin-left: 10px; }
        .badge-income { background: #dcfce7; color: #166534; }
        .badge-expense { background: #fee2e2; color: #991b1b; }
        
        .actions { display: flex; gap: 15px; }
        .btn-edit { color: var(--accent); text-decoration: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-del { color: var(--danger); text-decoration: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
        .btn-edit:hover, .btn-del:hover { text-decoration: underline; }
        
        .empty-state { padding: 30px; text-align: center; color: #64748b; font-size: 0.9rem; }

        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: var(--card); padding: 30px; border-radius: 16px; width: 90%; max-width: 350px; text-align: center; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .modal-box h3 { margin: 0 0 15px 0; color: var(--primary); }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--accent); color: white; }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h2 style="margin:0; color: var(--primary);">Categories 🏷️</h2>
        <a href="user_dashboard.php" class="back-link">← Dashboard</a>
    </header>

    <?php if(isset($error)): ?>
        <div style="background: #fee2e2; color: var(--danger); padding: 10px; border-radius: 8px; margin-bottom: 15px;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="form-card">
        <h3>Create New Category</h3>
        <form method="POST" class="form-row">
            <input type="hidden" name="add_category" value="1">
            <input type="text" name="name" placeholder="Category Name" required>
            <select name="type" required style="max-width: 150px;">
                <option value="Expense">Expense</option>
                <option value="Income">Income</option>
            </select>
            <button type="submit" class="btn-add">Add</button>
        </form>
    </div>

    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('expense')">Expenses (<?= count($expenses) ?>)</button>
        <button class="tab-btn" onclick="switchTab('income')">Incomes (<?= count($incomes) ?>)</button>
    </div>

    <div id="tab-expense" class="tab-content active">
        <div class="list-card">
            <?php if(count($expenses) > 0): ?>
                <?php foreach($expenses as $row): ?>
                    <div class="list-item">
                        <div>
                            <span class="cat-name"><?= htmlspecialchars($row['name']) ?></span>
                            <span class="badge badge-expense">Expense</span>
                        </div>
                        <div class="actions">
                            <a class="btn-edit" onclick="openEditModal(<?= $row['CatID'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', 'Expense')">Edit</a>
                            <a class="btn-del" onclick="confirmDelete(<?= $row['CatID'] ?>)">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No expense categories yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div id="tab-income" class="tab-content">
        <div class="list-card">
            <?php if(count($incomes) > 0): ?>
                <?php foreach($incomes as $row): ?>
                    <div class="list-item">
                        <div>
                            <span class="cat-name"><?= htmlspecialchars($row['name']) ?></span>
                            <span class="badge badge-income">Income</span>
                        </div>
                        <div class="actions">
                            <a class="btn-edit" onclick="openEditModal(<?= $row['CatID'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>', 'Income')">Edit</a>
                            <a class="btn-del" onclick="confirmDelete(<?= $row['CatID'] ?>)">Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">No income categories yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Edit Category</h3>
        <form method="POST" id="editForm">
            <input type="hidden" name="edit_category" value="1">
            <input type="hidden" name="cat_id" id="edit_cat_id">
            <input type="hidden" name="old_name" id="edit_old_name">
            <input type="hidden" name="cat_type" id="edit_cat_type">
            
            <input type="text" name="new_name" id="edit_new_name" required style="width: 100%; box-sizing: border-box; text-align: center; font-size: 1.1rem; font-weight: 600;">
            
            <div style="font-size: 0.8rem; color: #64748b; margin-top: 15px;">
                Note: Updating this name will automatically update all past transactions using this category.
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="modal-btn-confirm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <h3 style="color: var(--danger);">Delete Category?</h3>
        <p>Past transactions will keep this text label, but it will disappear from your dropdowns permanently.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <button class="modal-btn-confirm" style="background: var(--danger);" onclick="executeDelete()">Yes, Delete</button>
        </div>
    </div>
</div>

<script>
    // Tab Logic
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById('tab-' + tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }

    // Edit Modal Logic
    function openEditModal(id, currentName, type) {
        document.getElementById('edit_cat_id').value = id;
        document.getElementById('edit_old_name').value = currentName;
        document.getElementById('edit_new_name').value = currentName;
        document.getElementById('edit_cat_type').value = type;
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Delete Modal Logic
    let deleteId = null;
    function confirmDelete(id) {
        deleteId = id;
        document.getElementById('deleteModal').style.display = 'flex';
    }
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    function executeDelete() {
        if(deleteId) window.location.href = 'manage_categories.php?delete_id=' + deleteId;
    }
</script>

</body>
</html>