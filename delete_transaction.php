<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || !isset($_GET['id'])) {
    header("Location: user_dashboard.php");
    exit();
}

$tid = $_GET['id'];
$uid = $_SESSION['UID'];

// Soft delete: Toggle the flag and log the exact time
$sql = "UPDATE TRANSACTION SET isDeleted = TRUE, deletedAt = NOW() WHERE TID = ? AND author_UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $tid, $uid);
$stmt->execute();

header("Location: user_dashboard.php");
exit();
?>