<?php
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $add_qty = $_POST['add_qty'];

    try {
        // 1. MYSQL: Update the stock table
        $stmt = $pdo->prepare("UPDATE stock SET Current_Stock = Current_Stock + ? WHERE Item_ID = ?");
        $stmt->execute([$add_qty, $item_id]);

        // 2. MONGODB: Log the restock event
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $log = [
            'event' => 'STOCK_RESTOCKED',
            'mysql_item_id' => (int)$item_id,
            'quantity_added' => (int)$add_qty,
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];
        $client->happypawsvet->system_logs->insertOne($log);

        header("Location: inventory.php?success=restocked");
    } catch (Exception $e) {
        die("Restock Error: " . $e->getMessage());
    }
}