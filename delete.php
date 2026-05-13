<?php
require 'db_connect.php';

if (isset($_GET['id'])) {
    $uid = $_GET['id'];
    
    // Deleting from USER automatically deletes from ADMINISTRATOR due to ON DELETE CASCADE
    $stmt = $conn->prepare("DELETE FROM USER WHERE UID = ?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    
    header("Location: index.php");
    exit();
}
?>