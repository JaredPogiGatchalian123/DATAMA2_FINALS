<?php
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $reduce_qty = (int)$_POST['reduce_qty'];

    try {
        $pdo->beginTransaction();

        // Fetch name for the audit log
        $stmtName = $pdo->prepare("SELECT Item_Name FROM Inventory WHERE Item_ID = ?");
        $stmtName->execute([$item_id]);
        $item = $stmtName->fetch();

        // 1. MySQL: Update Stock Table
        $sql = "UPDATE Stock SET Current_Stock = Current_Stock - ? WHERE Item_ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$reduce_qty, $item_id]);

        // 2. MongoDB: Log the deduction
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $collection = $client->happypawsvet->inventory_logs;

        $collection->insertOne([
            'event' => 'STOCK_DEDUCTION',
            'item_name' => $item['Item_Name'],
            'quantity_deducted' => $reduce_qty,
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ]);

        $pdo->commit();
        header("Location: inventory.php?success=deducted");
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Sync Error: " . $e->getMessage());
    }
}