<?php
require 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hashes the password securely
    $aid = $_POST['aid'];
    $department = $_POST['department'];
    $adminLevel = $_POST['adminLevel'];

    // Start a transaction to insert into both tables
    $conn->begin_transaction();

    try {
        // 1. Insert into the parent USER table
        $stmt1 = $conn->prepare("INSERT INTO USER (firstName, lastName, password) VALUES (?, ?, ?)");
        $stmt1->bind_param("sss", $firstName, $lastName, $password);
        $stmt1->execute();
        
        // Get the UID that MySQL just auto-generated
        $uid = $conn->insert_id; 

        // 2. Insert into the child ADMINISTRATOR table using the new UID
        $stmt2 = $conn->prepare("INSERT INTO ADMINISTRATOR (UID, AID, department, adminLevel) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $uid, $aid, $department, $adminLevel);
        $stmt2->execute();

        // If both succeeded, save the changes to the database
        $conn->commit();
        header("Location: index.php");
        exit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback(); // Cancel if something went wrong
        echo "Error: " . $exception->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<body>
    <h2>Add New Admin</h2>
    <form method="POST">
        <label>First Name:</label> <input type="text" name="firstName" required><br>
        <label>Last Name:</label> <input type="text" name="lastName" required><br>
        <label>Password:</label> <input type="password" name="password" required><br>
        <label>Badge Number (AID):</label> <input type="text" name="aid" required><br>
        <label>Department:</label> <input type="text" name="department" required><br>
        <label>Admin Level:</label> 
        <select name="adminLevel">
            <option value="Tier 1">Tier 1</option>
            <option value="Tier 2">Tier 2</option>
            <option value="Super">Super</option>
        </select><br><br>
        <button type="submit">Create Admin</button>
    </form>
</body>
</html>