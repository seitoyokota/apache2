<?php
$host = 'localhost';
$db   = 'notesapp';
$dbUser = 'seitoyokota';
$dbPass = 'Sy-01525315253!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
