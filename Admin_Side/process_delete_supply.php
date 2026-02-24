<?php
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

if (isset($_GET['id'])) {
    $item_id = $_GET['id'];

    try {
        $pdo->beginTransaction();

        // Fetch details before deletion so we have data for the log
        $stmtInfo = $pdo->prepare("SELECT Item_Name FROM Inventory WHERE Item_ID = ?");
        $stmtInfo->execute([$item_id]);
        $item = $stmtInfo->fetch();

        if ($item) {
            // 1. MySQL: Remove from Stock and Inventory
            // Note: Ensure your DB has ON DELETE CASCADE or delete Stock first
            $pdo->prepare("DELETE FROM Stock WHERE Item_ID = ?")->execute([$item_id]);
            $pdo->prepare("DELETE FROM Inventory WHERE Item_ID = ?")->execute([$item_id]);

            // 2. MongoDB: Create a "Delete" Audit Log
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $collection = $client->happypawsvet->inventory_logs;

            $collection->insertOne([
                'event' => 'INVENTORY_ITEM_DELETED',
                'item_name' => $item['Item_Name'],
                'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'details' => ['mysql_id_at_deletion' => (int)$item_id]
            ]);

            $pdo->commit();
            header("Location: inventory.php?success=deleted");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Delete Sync Error: " . $e->getMessage());
    }
}