<?php
require 'vendor/autoload.php'; // Required for MongoDB
include 'config/db.php';
session_start();

// Security: Only staff can delete items
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if (isset($_GET['id'])) {
    $item_id = $_GET['id'];

    try {
        // --- 1. MONGODB: Log Deletion Before it's gone from MySQL ---
        // We fetch the name first so our log is readable
        $stmtName = $pdo->prepare("SELECT Item_Name FROM inventory WHERE Item_ID = ?");
        $stmtName->execute([$item_id]);
        $item = $stmtName->fetch();
        $item_name = $item ? $item['Item_Name'] : "Unknown Item";

        $client = new MongoDB\Client("mongodb://localhost:27017");
        $logCollection = $client->happypawsvet->system_logs;

        $logEntry = [
            'event' => 'INVENTORY_ITEM_DELETED',
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'details' => [
                'mysql_item_id' => (int)$item_id,
                'name' => $item_name,
                'status' => 'Permanently Removed'
            ],
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];
        $logCollection->insertOne($logEntry);

        // --- 2. MYSQL: Delete from child table first (Stock) ---
        $pdo->prepare("DELETE FROM stock WHERE Item_ID = ?")->execute([$item_id]);

        // --- 3. MYSQL: Delete from parent table (Inventory) ---
        $pdo->prepare("DELETE FROM inventory WHERE Item_ID = ?")->execute([$item_id]);

        // Redirect back with success message
        header("Location: inventory.php?success=deleted");
        exit();

    } catch (Exception $e) {
        die("Hybrid Sync Error: " . $e->getMessage());
    }
}