<?php
require 'db_connect.php';
session_start();

// SECURITY CHECK: Only allow logged-in Administrators to delete posts
if (!isset($_SESSION['UID']) || $_SESSION['role'] !== 'Admin') {
    header("Location: forum.php");
    exit();
}

// Ensure an ID was actually passed in the URL
if (isset($_GET['id'])) {
    $fpid = $_GET['id'];

    // Delete the post from the database
    $stmt = $conn->prepare("DELETE FROM FORUM_POST WHERE FPID = ?");
    $stmt->bind_param("i", $fpid);
    
    if ($stmt->execute()) {
        // Redirect back to the forum with a success signal if you want to add one later
        header("Location: forum.php?msg=deleted");
        exit();
    } else {
        echo "Error deleting post: " . $conn->error;
    }
} else {
    // If no ID is found, just go back
    header("Location: forum.php");
    exit();
}
?>