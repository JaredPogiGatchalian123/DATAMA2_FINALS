<?php
// NO SPACES OR LINES ABOVE THIS TAG
$host = "127.0.0.1:3307"; 
$user = "root";
$pass = ""; 
$db   = "happypawsvet_v2";

// 1. SET PHP TIMEZONE (Ensures date() functions match Manila time)
date_default_timezone_set('Asia/Manila');

try {
    // MySQL for Business Records (Consistent & Persistent)
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 2. SET MYSQL TIMEZONE (Ensures NOW() and Appointment_Date match Manila time)
    $pdo->exec("SET time_zone = '+08:00'");

    // MongoDB for Logs (Fast & Flexible)
    $m_manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");

} catch (Exception $e) {
    // If the database fails, we stop to prevent session corruption
    die("Database Connection Failed: " . $e->getMessage());
}
// DO NOT ADD A CLOSING PHP TAG HERE TO PREVENT WHITESPACE ERRORS