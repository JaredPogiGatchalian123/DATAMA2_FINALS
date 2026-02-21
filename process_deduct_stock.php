<?php
require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = $_POST['item_id'];
    // Dictated amount from user input
    $reduce_qty = (int)$_POST['reduce_qty']; 

    try {
        // 1. MYSQL: Deduct the dictated amount
        // The WHERE clause ensures we don't go below zero
        $stmt = $pdo->prepare("UPDATE stock SET Current_Stock = Current_Stock - ? WHERE Item_ID = ? AND Current_Stock >= ?");
        $stmt->execute([$reduce_qty, $item_id, $reduce_qty]);

        // Check if the update actually happened (enough stock was available)
        if ($stmt->rowCount() > 0) {
            
            // Fetch name for the MongoDB log
            $nameStmt = $pdo->prepare("SELECT Item_Name FROM inventory WHERE Item_ID = ?");
            $nameStmt->execute([$item_id]);
            $itemName = $nameStmt->fetchColumn();

            // 2. MONGODB: Log the bulk reduction event
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $client->happypawsvet->system_logs->insertOne([
                'event' => 'BULK_STOCK_DEDUCTION',
                'item_name' => $itemName,
                'quantity_deducted' => $reduce_qty,
                'staff_member' => $_SESSION['staff_name'] ?? 'Jared',
                'timestamp' => new MongoDB\BSON\UTCDateTime()
            ]);

            header("Location: inventory.php?success=deducted");
            exit();
        } else {
            // Error handling if user tries to reduce more than available
            die("Error: Not enough stock available to reduce by " . $reduce_qty . " units.");
        }

    } catch (Exception $e) {
        die("Hybrid Sync Error: " . $e->getMessage());
    }
} else {
    header("Location: inventory.php");
    exit();
}