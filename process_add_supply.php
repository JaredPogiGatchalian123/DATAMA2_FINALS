<?php
require 'vendor/autoload.php'; // Required for MongoDB
include 'config/db.php';
session_start();

// Security: Ensure only staff can process inventory
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect data from your Modal form
    $name = $_POST['item_name'];
    $cat_id = $_POST['category_id'];
    $price = $_POST['price'];
    $qty = $_POST['initial_stock'];

    try {
        // --- 1. MYSQL: Insert into Inventory Table ---
        // We use 'Price_Per_Unit' to match your DESCRIBE inventory results
        $sql = "INSERT INTO inventory (Item_Name, Category_ID, Price_Per_Unit, Min_Stock_Level) VALUES (?, ?, ?, 5)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $cat_id, $price]);
        $item_id = $pdo->lastInsertId();

        // --- 2. MYSQL: Create initial Stock Record ---
        // This ensures the stock table tracks the quantity of the new item
        $stmtStock = $pdo->prepare("INSERT INTO stock (Item_ID, Current_Stock) VALUES (?, ?)");
        $stmtStock->execute([$item_id, $qty]);

        // --- 3. MONGODB: Create System Audit Log ---
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $logCollection = $client->happypawsvet->system_logs;

        $logEntry = [
            'event' => 'NEW_INVENTORY_ADDED',
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'item_details' => [
                'mysql_item_id' => (int)$item_id,
                'name' => $name,
                'initial_qty' => (int)$qty,
                'price' => (float)$price
            ],
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $logCollection->insertOne($logEntry);

        // Success: Redirect back to inventory with the success trigger for SweetAlert
        header("Location: inventory.php?success=1");
        exit();

    } catch (Exception $e) {
        // Error handling for the hybrid synchronization process
        die("Hybrid Update Error: " . $e->getMessage());
    }
}
?>