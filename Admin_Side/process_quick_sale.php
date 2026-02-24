<?php
include 'config/db.php';
require 'vendor/autoload.php'; 
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'];
    $qty = (int)$_POST['quantity'];
    $method = $_POST['payment_method'];

    try {
        $pdo->beginTransaction();

        // 1. Get Price & Name (Using lowercase table names for V2)
        $stmt = $pdo->prepare("SELECT i.Item_Name, i.Price_Per_Unit, s.Current_Stock 
                                FROM inventory i 
                                JOIN stock s ON i.Item_ID = s.Item_ID 
                                WHERE i.Item_ID = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();

        if ($item && $item['Current_Stock'] >= $qty) {
            $total = (float)($item['Price_Per_Unit'] * $qty);
            $new_stock = $item['Current_Stock'] - $qty;

            // 2. MYSQL: Deduct Current Stock
            $update = $pdo->prepare("UPDATE stock SET Current_Stock = ? WHERE Item_ID = ?");
            $update->execute([$new_stock, $item_id]);

            // 3. MYSQL: Record Payment
            $pay = $pdo->prepare("INSERT INTO payment (Amount_Paid, Payment_Method, Appointment_ID) 
                                  VALUES (?, ?, NULL)");
            $pay->execute([$total, $method]);
            
            // Capture the exact MySQL ID for standardized numbering
            $last_id = $pdo->lastInsertId(); 

            // --- 4. MONGODB LOGGING ---
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $timestamp = new MongoDB\BSON\UTCDateTime();

            // A. Inventory Audit Log
            $logCollection = $client->happypawsvet->inventory_logs;
            $logCollection->insertOne([
                'event' => 'WALK_IN_SALE',
                'item_name' => $item['Item_Name'],
                'quantity_change' => -$qty, 
                'staff_member' => $_SESSION['staff_name'] ?? 'Staff',
                'timestamp' => $timestamp
            ]);

            // B. Financial Payment Log (Standardized Format: #SALE-00000)
            $payCollection = $client->happypawsvet->payment_logs;
            
            // Standardizing the ID to 5 digits to match medical logs
            $padded_id = str_pad($last_id, 5, '0', STR_PAD_LEFT);

            $payCollection->insertOne([
                'invoice_no' => '#SALE-' . $padded_id, // Matches #MED-00005 style
                'owner_name' => 'Walk-in Customer',   // Matches the alignment in your dashboard
                'service'    => 'Retail: ' . $item['Item_Name'],
                'method'     => $method,
                'amount_paid'=> $total,
                'timestamp'  => $timestamp,
                'details'    => ['item_sold' => $item['Item_Name'], 'qty' => $qty]
            ]);

            $pdo->commit();
            header("Location: staff_portal.php?success=sale");
            exit();
        } else {
            die("Error: Insufficient stock.");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Sale Error: " . $e->getMessage());
    }
}