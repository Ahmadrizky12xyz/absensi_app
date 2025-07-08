<?php
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

require_once "../config.php";

header('Content-Type: application/json');

$teachers_sql = "SELECT id, nama, face_encoding FROM teachers WHERE face_encoding IS NOT NULL";
$teachers_result = $mysqli->query($teachers_sql);
$teachers = [];

while ($teacher = $teachers_result->fetch_assoc()) {
    $teachers[] = [
        'id' => $teacher['id'],
        'nama' => $teacher['nama'],
        'face_encoding' => $teacher['face_encoding']
    ];
}

echo json_encode($teachers);
?>