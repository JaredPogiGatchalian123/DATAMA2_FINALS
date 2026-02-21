<?php
require 'vendor/autoload.php'; // Required for MongoDB
include 'config/db.php';
session_start();

// Security: Ensure only staff can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and Collect data
    $owner_id = $_POST['owner_id'];
    $pet_id   = $_POST['pet_id'];
    $svc_id   = $_POST['service_id'];
    $vet_id   = $_POST['vet_id'];
    $date     = $_POST['appt_date'];
    
    // Safety: Ensure staff_id exists to prevent JOIN errors in appointments.php
    $staff_id = $_SESSION['staff_id'] ?? null; 

    try {
        // --- 2. MYSQL: Save Operational Data ---
        // We ensure the status is 'Pending' so it shows up in your main queue
        $sql = "INSERT INTO appointment (Owner_ID, Pet_ID, Service_ID, Vet_ID, Appointment_Date, Status, Created_By) 
                VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $pet_id, $svc_id, $vet_id, $date, $staff_id]);

        // --- 3. MONGODB: Create System Audit Log ---
        // This provides the non-relational proof for your hybrid architecture
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $logCollection = $client->happypawsvet->system_logs;

        $logEntry = [
            'event' => 'STAFF_MANUAL_BOOKING',
            'staff_name' => $_SESSION['staff_name'] ?? 'Jared',
            'staff_id' => (int)$staff_id,
            'details' => [
                'owner_id' => (int)$owner_id,
                'pet_id' => (int)$pet_id,
                'type' => 'Walk-in/Call Entry'
            ],
            'timestamp' => new MongoDB\BSON\UTCDateTime()
        ];
        
        $logCollection->insertOne($logEntry);

        // Redirect with success trigger for SweetAlert2
        header("Location: appointments.php?success=1");
        exit();

    } catch (Exception $e) {
        // Error handling for the panel to see
        die("Hybrid Sync Failed: " . $e->getMessage());
    }
}