<?php
// 1. Setup Timezone and Error Reporting
date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

// Security: Ensure only staff can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect POST data with fallback to null
    $owner_id = $_POST['owner_id'] ?? null;
    $pet_id   = $_POST['pet_id'] ?? null;
    $svc_id   = $_POST['service_id'] ?? null; // Captured from form
    $vet_id   = $_POST['vet_id'] ?? null; 
    $date     = $_POST['appt_date'] ?? null; 

    // Validation: Ensure no required fields are empty
    if (!$owner_id || !$pet_id || !$svc_id || !$vet_id || !$date) {
        die("Error: All fields are required. Please go back and fill the form completely.");
    }

    try {
        // 2. Service Duration Mapping
        $durations = [1 => 15, 2 => 30, 3 => 120, 4 => 60];
        $new_svc_duration = $durations[$svc_id] ?? 30;

        $appt_ts = strtotime($date);
        $day_name = date('l', $appt_ts);
        $time_only = date('H:i:s', $appt_ts);

        // 3. Clinic Hours Validation (Vet Shift Check)
        $sched_check = $pdo->prepare("SELECT COUNT(*) FROM vet_schedule 
                                     WHERE Vet_ID = ? AND Day_of_Week = ? 
                                     AND ? BETWEEN Start_Time AND End_Time");
        $sched_check->execute([$vet_id, $day_name, $time_only]);

        if ($sched_check->fetchColumn() == 0) {
            header("Location: appointments.php?error=outside_hours");
            exit();
        }

        // 4. Strict Conflict Checker (Checks Pending Only)
        // Check if VET is busy
        $check_vet = $pdo->prepare("SELECT COUNT(*) FROM appointment 
                      WHERE Vet_ID = ? AND Status = 'Pending'
                      AND (
                          (? < DATE_ADD(Appointment_Date, INTERVAL 30 MINUTE)) 
                          AND (DATE_ADD(?, INTERVAL ? MINUTE) > Appointment_Date)
                      )");
        $check_vet->execute([$vet_id, $date, $date, $new_svc_duration]);
        
        if ($check_vet->fetchColumn() > 0) {
            header("Location: appointments.php?error=vet_busy");
            exit();
        }

        $pdo->beginTransaction(); 

        // 5. MySQL Save
        $sql = "INSERT INTO appointment (Owner_ID, Pet_ID, Service_ID, Vet_ID, Appointment_Date, Status) 
                VALUES (?, ?, ?, ?, ?, 'Pending')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$owner_id, $pet_id, $svc_id, $vet_id, $date]);

        // 6. Fetch details for MongoDB Audit Log
        $details_stmt = $pdo->prepare("
            SELECT o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type, v.Vet_Fname, v.Vet_Lname 
            FROM owner o
            JOIN pet p ON p.Pet_ID = ?
            JOIN service_type s ON s.Service_ID = ?
            JOIN vet v ON v.Vet_ID = ?
            WHERE o.Owner_ID = ?
        ");
        $details_stmt->execute([$pet_id, $svc_id, $vet_id, $owner_id]);
        $info = $details_stmt->fetch();

        // 7. MongoDB Log
        $client = new MongoDB\Client("mongodb://localhost:27017");
        $collection = $client->happypawsvet->appointment_logs;

        $logEntry = [
            'mysql_owner_id' => (int)$owner_id,
            'owner_name'     => ($info['Owner_Fname'] . " " . $info['Owner_Lname']),
            'pet_name'       => $info['Pet_Name'] ?? 'Unknown Pet',
            'status'         => 'Pending',
            'mysql_action'   => 'Staff Created Appointment',
            'timestamp'      => new MongoDB\BSON\UTCDateTime(),
            'details' => [
                'service' => $info['Service_Type'],
                'vet'     => "Dr. " . $info['Vet_Fname'] . " " . $info['Vet_Lname'],
                'date'    => $date
            ]
        ];
        
        $collection->insertOne($logEntry);
        $pdo->commit(); 

        header("Location: appointments.php?success=1");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Sync Failed: " . $e->getMessage());
    }
}