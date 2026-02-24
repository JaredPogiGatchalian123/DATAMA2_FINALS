<?php
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['item_name'];
    $cat_id = $_POST['category_id'];
    $price = $_POST['price'];
    $qty = $_POST['initial_stock'];

    try {
        $pdo->beginTransaction();

        // MYSQL: Using Capitalized Table Names
        $sql = "INSERT INTO Inventory (Item_Name, Category_ID, Price_Per_Unit, Min_Stock_Level) VALUES (?, ?, ?, 5)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $cat_id, $price]);
        $item_id = $pdo->lastInsertId();

        $stmtStock = $pdo->prepare("INSERT INTO Stock (Item_ID, Current_Stock) VALUES (?, ?)");
        $stmtStock->execute([$item_id, $qty]);

        // MONGODB: Pointing to the collection shown in your UI
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $logCollection = $client->happypawsvet->inventory_logs;

        $logEntry = [
            'event' => 'NEW_INVENTORY_ADDED',
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'item_name' => $name, // Required for the 'Item Details' column
            'quantity_added' => (int)$qty, // Required for 'Quantity Change' column
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $logCollection->insertOne($logEntry);
        $pdo->commit();

        header("Location: inventory.php?success=added");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Sync Error: " . $e->getMessage());
    }
}