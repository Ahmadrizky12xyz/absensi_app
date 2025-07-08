<?php
require_once "config.php";

// Buat user admin default
$username = "admin";
$password = password_hash("admin123", PASSWORD_DEFAULT);
$role = "admin";

$sql = "INSERT INTO users (username, password, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE password = VALUES(password)";

if($stmt = $mysqli->prepare($sql)){
    $stmt->bind_param("sss", $username, $password, $role);
    
    if($stmt->execute()){
        echo "Admin user berhasil dibuat/diperbarui.<br>";
        echo "Username: admin<br>";
        echo "Password: admin123<br>";
    } else{
        echo "ERROR: " . $stmt->error;
    }
    
    $stmt->close();
}

$mysqli->close();
?>

