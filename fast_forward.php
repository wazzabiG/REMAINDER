<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'End User') {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];

// This SQL command takes the user's current cycleStartDate and pushes it exactly 1 day into the past.
// This tricks the dashboard math into thinking 24 hours have elapsed.
$sql = "UPDATE END_USER SET cycleStartDate = DATE_SUB(cycleStartDate, INTERVAL 1 DAY) WHERE UID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $uid);

if ($stmt->execute()) {
    header("Location: user_dashboard.php");
    exit();
} else {
    echo "Error time traveling.";
}
?>