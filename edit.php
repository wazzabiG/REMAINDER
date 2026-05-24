<?php
require 'db_connect.php';
session_start();

// Security: Only Admins can edit other admins
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$uid = $_GET['id'];

// Fetch existing data
$sql = "SELECT U.firstName, U.lastName, A.AID, A.department, A.adminLevel 
        FROM USER U JOIN ADMINISTRATOR A ON U.UID = A.UID WHERE U.UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    header("Location: index.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $department = $_POST['department'];
    $adminLevel = $_POST['adminLevel'];
    $newPassword = $_POST['password'];

    $conn->begin_transaction();
    try {
        if (!empty($newPassword)) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt1 = $conn->prepare("UPDATE USER SET firstName=?, lastName=?, password=? WHERE UID=?");
            $stmt1->bind_param("sssi", $firstName, $lastName, $hashedPassword, $uid);
        } else {
            $stmt1 = $conn->prepare("UPDATE USER SET firstName=?, lastName=? WHERE UID=?");
            $stmt1->bind_param("ssi", $firstName, $lastName, $uid);
        }
        $stmt1->execute();

        $stmt2 = $conn->prepare("UPDATE ADMINISTRATOR SET department=?, adminLevel=? WHERE UID=?");
        $stmt2->bind_param("ssi", $department, $adminLevel, $uid);
        $stmt2->execute();

        $conn->commit();
        header("Location: index.php?msg=updated");
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        $error = "Update failed: " . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin | Admin Console</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .edit-card {
            background: var(--card);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; text-align: center; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; text-align: center; }

        .section-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent);
            margin-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 5px;
        }

        .form-row { display: flex; gap: 15px; margin-bottom: 16px; }
        .form-group { flex: 1; text-align: left; margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 6px; color: #475569; }

        input, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-sizing: border-box;
            font-size: 0.95rem;
            color: var(--text);
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .password-note {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 5px;
            font-style: italic;
        }

        .btn-update {
            width: 100%;
            background-color: var(--primary);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 8px;
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

        /* Custom Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(2px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-box {
            background: var(--card);
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 350px;
            text-align: center;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-box h3 { margin: 0 0 10px 0; color: var(--primary); }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }

        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--accent); color: white; }
    </style>
</head>
<body>

<div class="edit-card">
    <h2>Update Administrator</h2>
    <p class="subtitle">Modifying account for <strong><?= htmlspecialchars($admin['firstName']) ?> (AID: <?= $admin['AID'] ?>)</strong></p>

    <form id="editForm" method="POST">
        <span class="section-label">Identity</span>
        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="firstName" value="<?= htmlspecialchars($admin['firstName']) ?>" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lastName" value="<?= htmlspecialchars($admin['lastName']) ?>" required>
            </div>
        </div>

        <span class="section-label" style="margin-top: 20px;">Credentials</span>
        <div class="form-group">
            <label>Security Password</label>
            <input type="password" name="password" placeholder="••••••••">
            <div class="password-note">Leave blank to keep the current password.</div>
        </div>

        <span class="section-label" style="margin-top: 20px;">Role & Department</span>
        <div class="form-row">
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" value="<?= htmlspecialchars($admin['department']) ?>" required>
            </div>
            <div class="form-group">
                <label>Admin Level</label>
                <select name="adminLevel">
                    <option value="Tier 1" <?= $admin['adminLevel'] == 'Tier 1' ? 'selected' : '' ?>>Tier 1</option>
                    <option value="Tier 2" <?= $admin['adminLevel'] == 'Tier 2' ? 'selected' : '' ?>>Tier 2</option>
                    <option value="Super" <?= $admin['adminLevel'] == 'Super' ? 'selected' : '' ?>>Super</option>
                </select>
            </div>
        </div>

        <button type="button" class="btn-update" onclick="openModal()">Save Changes</button>
        <a href="index.php" class="cancel-link">Cancel and Go Back</a>
    </form>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Is this information correct?</h3>
        <p>You are about to update this administrator's profile.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="submitForm()">Yes, proceed</button>
        </div>
    </div>
</div>

<script>
    function openModal() {
        const form = document.getElementById('editForm');
        if (form.checkValidity()) {
            document.getElementById('confirmModal').style.display = 'flex';
        } else {
            form.reportValidity();
        }
    }
    function closeModal() { document.getElementById('confirmModal').style.display = 'none'; }
    function submitForm() { document.getElementById('editForm').submit(); }
</script>

</body>
</html>