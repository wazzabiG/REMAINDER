<?php
require 'db_connect.php';
session_start();

// Security: Only allow Admins (Uncommented for security based on your setup)
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$sql = "SELECT U.UID, U.firstName, U.lastName, A.AID, A.department, A.adminLevel 
        FROM USER U 
        JOIN ADMINISTRATOR A ON U.UID = A.UID";
$result = $conn->query($sql);

$totalAdmins = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Console | Lutong Bahay</title>
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

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background-color: var(--primary);
            color: white;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
        }

        .sidebar h1 {
            font-size: 1.25rem;
            margin-bottom: 40px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar nav a {
            color: #94a3b8;
            text-decoration: none;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 5px;
            display: block;
            transition: 0.2s;
        }

        .sidebar nav a:hover, .sidebar nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .main-content {
            flex-grow: 1;
            padding: 40px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: 0.2s;
            display: inline-block;
            cursor: pointer;
        }

        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: #2563eb; }

        .table-container {
            background: var(--card);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid var(--border);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #f8fafc;
            padding: 16px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
        }

        tr:hover { background-color: #f1f5f9; }

        .badge {
            padding: 4px 10px;
            border-radius: 99px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .level-high { background: #fee2e2; color: #991b1b; } 
        .level-low { background: #e0f2fe; color: #075985; }  

        .actions a {
            color: var(--accent);
            text-decoration: none;
            margin-right: 15px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }
        .actions a.delete { color: var(--danger); }

        .stats-summary { margin-bottom: 20px; color: #64748b; font-size: 0.9rem; }

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

        .modal-box h3 { margin: 0 0 10px 0; color: var(--danger); }
        .modal-box p { font-size: 0.9rem; color: #64748b; margin-bottom: 25px; }

        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; }
        .modal-btn-cancel { background: #f1f5f9; color: #475569; }
        .modal-btn-confirm { background: var(--danger); color: white; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h1>🛠 Admin Console</h1>
        <nav>
            <a href="index.php" class="active">Admin Management</a>
            <a href="forum.php">Forum Moderation</a>
            <a href="user_dashboard.php">View as User</a>
            <div style="margin-top: auto;">
                <a href="logout.php" style="color: #fca5a5;">Logout</a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <header>
            <div>
                <h2 style="margin:0;">System Administrators</h2>
                <div class="stats-summary">Total Active Admins: <?= $totalAdmins ?></div>
            </div>
            <a href="create.php" class="btn btn-primary">+ Add New Admin</a>
        </header>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Badge (AID)</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="color: #94a3b8;">#<?= $row['UID'] ?></td>
                        <td><strong><?= htmlspecialchars($row['AID']) ?></strong></td>
                        <td><?= htmlspecialchars($row['firstName'] . " " . $row['lastName']) ?></td>
                        <td><?= htmlspecialchars($row['department']) ?></td>
                        <td>
                            <span class="badge <?= $row['adminLevel'] == 'Super' ? 'level-high' : 'level-low' ?>">
                                <?= htmlspecialchars($row['adminLevel']) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="edit.php?id=<?= $row['UID'] ?>">Edit Details</a>
                            <a class="delete" onclick="openDeleteModal('delete.php?id=<?= $row['UID'] ?>')">Delete</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="deleteModal" class="modal-overlay">
        <div class="modal-box">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to completely remove this administrator from the system?</p>
            <div class="modal-actions">
                <button class="modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button class="modal-btn-confirm" onclick="executeDelete()">Yes, Delete</button>
            </div>
        </div>
    </div>

    <script>
        let deleteUrl = '';

        function openDeleteModal(url) {
            deleteUrl = url;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function executeDelete() {
            if(deleteUrl) {
                window.location.href = deleteUrl;
            }
        }
    </script>

</body>
</html>