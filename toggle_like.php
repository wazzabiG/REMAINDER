<?php
require 'db_connect.php';
session_start();

if (!isset($_SESSION['UID'])) {
    header("Location: login.php");
    exit();
}

$uid = $_SESSION['UID'];
$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);
$return_url = $_GET['return'] ?? 'forum.php';

if ($type === 'post' && $id > 0) {
    $check = $conn->prepare("SELECT * FROM post_like WHERE UID = ? AND FPID = ?");
    $check->bind_param("ii", $uid, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM post_like WHERE UID = ? AND FPID = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO post_like (UID, FPID) VALUES (?, ?)");
    }
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
} elseif ($type === 'comment' && $id > 0) {
    $check = $conn->prepare("SELECT * FROM comment_like WHERE UID = ? AND FCID = ?");
    $check->bind_param("ii", $uid, $id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        $stmt = $conn->prepare("DELETE FROM comment_like WHERE UID = ? AND FCID = ?");
    } else {
        $stmt = $conn->prepare("INSERT INTO comment_like (UID, FCID) VALUES (?, ?)");
    }
    $stmt->bind_param("ii", $uid, $id);
    $stmt->execute();
}

header("Location: " . $return_url);
exit();
?>