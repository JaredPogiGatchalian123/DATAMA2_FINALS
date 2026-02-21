<?php
require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'])) {
    $appt_id = $_POST['appt_id'];
    $pet_id = $_POST['pet_id']; 
    // Capture multiple supplies as an array
    $items_used = $_POST['items_used'] ?? []; 
    $payment_method = $_POST['payment_method']; 
    $medical_notes = $_POST['medical_notes'];
    $amount_paid = 500.00; 

    try {
        // Fetch details to bridge the NoSQL logs
        $sql = "SELECT a.*, s.Service_Type, o.Owner_ID, o.Owner_Fname, o.Owner_Lname, v.Vet_Lname 
                FROM Appointment a 
                JOIN Service_Type s ON a.Service_ID = s.Service_ID 
                JOIN Owner o ON a.Owner_ID = o.Owner_ID
                JOIN Vet v ON a.Vet_ID = v.Vet_ID
                WHERE a.Appointment_ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$appt_id]);
        $appt = $stmt->fetch();

        if ($appt) {
            $pdo->beginTransaction(); // Start MySQL Transaction

            // ACTION 1: Update Appointment status and Pet ID
            $updateAppt = $pdo->prepare("UPDATE Appointment SET Status = 'Completed', Notes = ?, Pet_ID = ? WHERE Appointment_ID = ?");
            $updateAppt->execute([$medical_notes, $pet_id, $appt_id]);

            // ACTION 2: Financial Transaction in MySQL
            $insertPay = $pdo->prepare("INSERT INTO payment (Appointment_ID, Amount_Paid, Payment_Date, Payment_Method) VALUES (?, ?, NOW(), ?)");
            $insertPay->execute([$appt_id, $amount_paid, $payment_method]);

            // ACTION 3: Multi-Stock Deduction
            $item_names_list = [];
            if (!empty($items_used)) {
                foreach ($items_used as $item_id) {
                    // Deduct 1 unit for each selected item in MySQL
                    $deductStock = $pdo->prepare("UPDATE stock SET Current_Stock = Current_Stock - 1 WHERE Item_ID = ? AND Current_Stock > 0");
                    $deductStock->execute([$item_id]);
                    
                    // Fetch the item name to store in the MongoDB array
                    $itemQuery = $pdo->prepare("SELECT Item_Name FROM inventory WHERE Item_ID = ?");
                    $itemQuery->execute([$item_id]);
                    $name = $itemQuery->fetchColumn();
                    if ($name) $item_names_list[] = $name;
                }
            }

            $pdo->commit(); // Commit all MySQL changes

            // ACTION 4: MongoDB Audit Logs
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $db = $client->happypawsvet;
            $owner_full_name = $appt['Owner_Fname'] . " " . $appt['Owner_Lname'];

            // Log A: Clinical History with the full list of supplies
            $db->appointment_logs->insertOne([
                'mysql_owner_id' => (int)$appt['Owner_ID'],
                'owner_name'     => $owner_full_name,
                'status'         => 'Completed',
                'timestamp'      => new MongoDB\BSON\UTCDateTime(),
                'details' => [
                    'service'      => $appt['Service_Type'],
                    'vet_name'     => $appt['Vet_Lname'],
                    'notes'        => $medical_notes,
                    'supplies_used'=> $item_names_list // MongoDB stores this as a clean array
                ]
            ]);

            // Log B: Financial Audit
            $db->payment_logs->insertOne([
                'invoice_no'   => "PAY-" . strtoupper(substr(md5(uniqid()), 0, 6)),
                'owner_name'   => $owner_full_name,
                'amount_paid'  => $amount_paid,
                'method'       => $payment_method,
                'timestamp'    => new MongoDB\BSON\UTCDateTime()
            ]);

            header("Location: appointments.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Hybrid Architecture Error: " . $e->getMessage());
    }
}
?>