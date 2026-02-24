<?php
include 'config/db.php';
require 'vendor/autoload.php'; // Required for MongoDB
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $item_id = $_POST['item_id'];
    $item_name = $_POST['item_name'];
    $new_price = $_POST['price'];

    try {
        // 1. Fetch current data to compare old price
        $old_stmt = $pdo->prepare("SELECT Price_Per_Unit FROM inventory WHERE Item_ID = ?");
        $old_stmt->execute([$item_id]);
        $old_price = $old_stmt->fetchColumn();

        // 2. Update MySQL
        $stmt = $pdo->prepare("UPDATE inventory SET Item_Name = ?, Price_Per_Unit = ? WHERE Item_ID = ?");
        $stmt->execute([$item_name, $new_price, $item_id]);

        // 3. Log Price Change to MongoDB inventory_logs
        if ($old_price != $new_price) {
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $collection = $client->happypawsvet->inventory_logs;

            $collection->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'event' => 'PRICE_UPDATE',
                'item_name' => $item_name,
                'old_price' => (float)$old_price,
                'new_price' => (float)$new_price,
                'staff_member' => $_SESSION['staff_name'] ?? 'Charles'
            ]);
        }

        header("Location: inventory.php?success=1");
        exit();
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}
?>