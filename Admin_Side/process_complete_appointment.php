<?php
date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'])) {
    $appt_id = $_POST['appt_id'];
    $pet_id = $_POST['pet_id']; 
    
    // Arrays from the dynamic UI rows
    $items_used = $_POST['items'] ?? []; 
    $item_qtys = $_POST['item_qty'] ?? []; 
    
    $payment_method = $_POST['payment_method']; 
    $medical_notes = $_POST['medical_notes'];

    try {
        // 1. Fetch full details including Service Price and Vet Name
        $sql = "SELECT a.*, s.Service_Type, s.Base_Price, o.Owner_Fname, o.Owner_Lname, p.Pet_Name,
                       CONCAT(v.Vet_Fname, ' ', v.Vet_Lname) AS Full_Vet_Name
                FROM appointment a 
                JOIN service_type s ON a.Service_ID = s.Service_ID 
                JOIN owner o ON a.Owner_ID = o.Owner_ID
                LEFT JOIN vet v ON a.Vet_ID = v.Vet_ID
                JOIN pet p ON p.Pet_ID = a.Pet_ID
                WHERE a.Appointment_ID = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$appt_id]);
        $appt = $stmt->fetch();

        if ($appt) {
            $pdo->beginTransaction();

            $base_service_price = (float)$appt['Base_Price'];
            $items_total_cost = 0.0;

            // 2. Process Inventory and deduct stock
            foreach ($items_used as $index => $item_id) {
                if (empty($item_id)) continue; 

                $qty = isset($item_qtys[$index]) ? (int)$item_qtys[$index] : 1;
                if ($qty <= 0) $qty = 1;

                // Get item price
                $itemQuery = $pdo->prepare("SELECT Price_Per_Unit FROM inventory WHERE Item_ID = ?");
                $itemQuery->execute([$item_id]);
                $itemData = $itemQuery->fetch();

                if ($itemData) {
                    $items_total_cost += ((float)$itemData['Price_Per_Unit'] * $qty);
                    
                    // Deduct quantity from stock
                    $deductStock = $pdo->prepare("UPDATE stock SET Current_Stock = Current_Stock - ? WHERE Item_ID = ?");
                    $deductStock->execute([$qty, $item_id]);
                }
            }

            $total_combined_price = $base_service_price + $items_total_cost;

            // 3. Update MySQL Records (Status, History, Payment)
            $updateAppt = $pdo->prepare("UPDATE appointment SET Status = 'Completed' WHERE Appointment_ID = ?");
            $updateAppt->execute([$appt_id]);

            $pdo->prepare("INSERT INTO medical_history (Pet_ID, Appointment_ID, Diagnosis, Date_Recorded) VALUES (?, ?, ?, NOW())")
                ->execute([$pet_id, $appt_id, $medical_notes]);

            $pdo->prepare("INSERT INTO payment (Appointment_ID, Amount_Paid, Payment_Date, Payment_Method) VALUES (?, ?, NOW(), ?)")
                ->execute([$appt_id, $total_combined_price, $payment_method]);

            // 4. Log to MongoDB Audit
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $db = $client->happypawsvet;

            // Determine Vet Name for logs
            $doctor_name = !empty($appt['Full_Vet_Name']) ? "Dr. " . $appt['Full_Vet_Name'] : "Not Assigned";

            // 4a. Log to Appointment History
            $db->appointment_logs->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'owner_name' => $appt['Owner_Fname'] . " " . $appt['Owner_Lname'],
                'pet_name' => $appt['Pet_Name'],
                'status' => 'Completed',
                'details' => [
                    'service' => $appt['Service_Type'],
                    'vet_name' => $doctor_name,
                    'scheduled_for' => $appt['Appointment_Date'],
                    'total_paid' => $total_combined_price,
                    'event' => 'Visit Finalized'
                ]
            ]);

            // 4b. Log to Payment History (Ensuring Financial Logs work correctly)
            $db->payment_logs->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'owner_name' => $appt['Owner_Fname'] . " " . $appt['Owner_Lname'],
                'amount_paid' => $total_combined_price,
                'method' => $payment_method,
                'details' => [
                    'service' => $appt['Service_Type'],
                    'vet_name' => $doctor_name
                ]
            ]);

            $pdo->commit();
            header("Location: appointments.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Critical Finalization Error: " . $e->getMessage());
    }
}
?>