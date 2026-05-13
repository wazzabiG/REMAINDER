<?php
require 'db_connect.php';

$firstName = "Colyn";
$lastName = "Pacaldo";
$password = password_hash("123", PASSWORD_DEFAULT); 
$department = "BSCS";
$adminLevel = "Super";

$conn->begin_transaction();

try {
    $stmt1 = $conn->prepare("INSERT INTO USER (firstName, lastName, password) VALUES (?, ?, ?)");
    $stmt1->bind_param("sss", $firstName, $lastName, $password);
    $stmt1->execute();
    $uid = $conn->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO ADMINISTRATOR (UID, department, adminLevel) VALUES (?, ?, ?)");
    $stmt2->bind_param("iss", $uid, $department, $adminLevel);
    $stmt2->execute();

    $conn->commit();
    echo "Default Admin created successfully! <br> Login with First Name: <b>Colyn</b> and Password: <b>123</b>";
} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>