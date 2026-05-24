<?php
require 'db_connect.php';
session_start();

// Security: Only allow Admins (Uncommented for security based on your setup)
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); 
    $aid = $_POST['aid'];
    $department = $_POST['department'];
    $adminLevel = $_POST['adminLevel'];

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("INSERT INTO USER (firstName, lastName, password) VALUES (?, ?, ?)");
        $stmt1->bind_param("sss", $firstName, $lastName, $password);
        $stmt1->execute();
        
        $uid = $conn->insert_id; 

        $stmt2 = $conn->prepare("INSERT INTO ADMINISTRATOR (UID, AID, department, adminLevel) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $uid, $aid, $department, $adminLevel);
        $stmt2->execute();

        $conn->commit();
        header("Location: index.php");
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback(); 
        $error = "Error: " . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin | Admin Console</title>
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
            border-top: 8px solid var(--primary);
        }

        h2 { margin: 0 0 10px 0; color: var(--primary); font-size: 1.5rem; text-align: center; }
        p.subtitle { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; text-align: center; }

        .error-msg {
            background-color: #fee2e2;
            color: var(--danger);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
        }

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

        .btn-update {
            width: 100%;
            background-color: var(--primary);
            color: white;
            padding: 14px;
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
    <h2>Add New Admin</h2>
    <p class="subtitle">Create a new system administrator account.</p>

    <?php if(isset($error)): ?>
        <div class="error-msg"><?= $error ?></div>
    <?php endif; ?>

    <form id="createForm" method="POST">
        <span class="section-label">Identity</span>
        <div class="form-row">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="firstName" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="lastName" required>
            </div>
        </div>

        <span class="section-label" style="margin-top: 10px;">Credentials</span>
        <div class="form-row">
            <div class="form-group">
                <label>Badge Number (AID)</label>
                <input type="text" name="aid" required>
            </div>
            <div class="form-group">
                <label>Security Password</label>
                <input type="password" name="password" required>
            </div>
        </div>

        <span class="section-label" style="margin-top: 10px;">Role & Department</span>
        <div class="form-row">
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" required>
            </div>
            <div class="form-group">
                <label>Admin Level</label>
                <select name="adminLevel">
                    <option value="Tier 1">Tier 1</option>
                    <option value="Tier 2">Tier 2</option>
                    <option value="Super">Super</option>
                </select>
            </div>
        </div>

        <button type="button" class="btn-update" onclick="openModal()">Create Admin</button>
        <a href="index.php" class="cancel-link">Cancel and Go Back</a>
    </form>
</div>

<div id="confirmModal" class="modal-overlay">
    <div class="modal-box">
        <h3>Is this information correct?</h3>
        <p>You are about to add a new administrator to the system.</p>
        <div class="modal-actions">
            <button class="modal-btn-cancel" onclick="closeModal()">Cancel</button>
            <button class="modal-btn-confirm" onclick="submitForm()">Yes, proceed</button>
        </div>
    </div>
</div>

<script>
    function openModal() {
        // Basic HTML5 validation check before opening modal
        const form = document.getElementById('createForm');
        if (form.checkValidity()) {
            document.getElementById('confirmModal').style.display = 'flex';
        } else {
            form.reportValidity(); // Shows default browser required popups if fields are empty
        }
    }
    function closeModal() { document.getElementById('confirmModal').style.display = 'none'; }
    function submitForm() { document.getElementById('createForm').submit(); }
</script>

</body>
</html>