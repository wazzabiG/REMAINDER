<?php
require 'db_connect.php';
session_start();

// Security check: Ensure they are logged in and an ID was actually passed in the URL
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User' || !isset($_GET['id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$wid = $_GET['id'];
$uid = $_SESSION['UID'];

// Update the warning to Acknowledged (1)
// Notice we also check "target_UID = ?" to prevent users from typing random IDs in the URL and deleting other people's warnings!
$sql = "UPDATE WARNING SET isAcknowledged = 1 WHERE WID = ? AND target_UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $wid, $uid);
$stmt->execute();

// Instantly bounce them back to the dashboard where the red banner will now be gone
header("Location: user_dashboard.php");
exit();
?>