<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('Asia/Manila');

require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['owner_id'])) {

    $vet_id       = $_POST['vet_id'];
    $service_type = $_POST['service_type'];
    $pet_id       = $_POST['pet_id']; 
    $date_input   = $_POST['apt_date'];
    $time_input   = $_POST['apt_time'];
    $owner_id     = $_SESSION['owner_id'];
    
    $full_appt_date = "$date_input $time_input"; 
    $status = 'Pending';

    try {
        /**
         * 1. CLINIC HOURS VALIDATION
         * Check if the requested day and time match the Vet's schedule in MySQL.
         */
        $appt_day = date('l', strtotime($date_input)); 
        $appt_time_only = date('H:i:s', strtotime($time_input));

        $sched_sql = "SELECT COUNT(*) FROM Vet_Schedule 
                      WHERE Vet_ID = ? AND Day_of_Week = ? 
                      AND ? BETWEEN Start_Time AND End_Time";
        $sched_stmt = $pdo->prepare($sched_sql);
        $sched_stmt->execute([$vet_id, $appt_day, $appt_time_only]);

        if ($sched_stmt->fetchColumn() == 0) {
            // Error: Selected time is outside of the doctor's duty hours
            header("Location: book_appointment.php?error=outside_hours&vet_id=" . $vet_id);
            exit();
        }

        /**
         * 2. CONFLICT CHECK (Double-booking)
         */
        $check_sql = "SELECT COUNT(*) FROM Appointment 
                      WHERE Vet_ID = ? AND Appointment_Date = ? AND Status != 'Cancelled'";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$vet_id, $full_appt_date]);
        
        if ($check_stmt->fetchColumn() > 0) {
            header("Location: book_appointment.php?error=taken&vet_id=" . $vet_id);
            exit();
        }

        // 3. Get Service_ID
        $svc_stmt = $pdo->prepare("SELECT Service_ID FROM Service_Type WHERE Service_Type = ?");
        $svc_stmt->execute([$service_type]);
        $service = $svc_stmt->fetch();
        
        if (!$service) {
            die("MySQL Error: The service '$service_type' was not found.");
        }
        $service_id = $service['Service_ID'];

        // 4. Insert Appointment into MySQL
        $sql = "INSERT INTO Appointment (Owner_ID, Vet_ID, Service_ID, Pet_ID, Appointment_Date, Status) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$owner_id, $vet_id, $service_id, $pet_id, $full_appt_date, $status])) {
            
            // 5. MongoDB Audit Log Bridge
            try {
                $info_stmt = $pdo->prepare("
                    SELECT o.Owner_Fname, o.Owner_Lname, v.Vet_Fname, v.Vet_Lname, p.Pet_Name 
                    FROM `Owner` o
                    JOIN Vet v ON v.Vet_ID = ?
                    JOIN Pet p ON p.Pet_ID = ?
                    WHERE o.Owner_ID = ?
                ");
                $info_stmt->execute([$vet_id, $pet_id, $owner_id]);
                $info = $info_stmt->fetch();

                $client = new MongoDB\Client("mongodb://localhost:27017");
                $collection = $client->happypawsvet->appointment_logs;

                $collection->insertOne([
                    'mysql_owner_id' => (int)$owner_id,
                    'owner_name'     => $info ? ($info['Owner_Fname'] . " " . $info['Owner_Lname']) : "Unknown Owner",
                    'pet_name'       => $info ? $info['Pet_Name'] : "Unknown Pet",
                    'status'         => 'Pending',
                    'timestamp'      => new MongoDB\BSON\UTCDateTime(), 
                    'details'        => [
                        'service'       => $service_type,
                        'vet_name'      => $info ? ("Dr. " . $info['Vet_Fname'] . " " . $info['Vet_Lname']) : "Dr. Assigned Vet",
                        'booking_type'  => 'Customer Self-Service',
                        'scheduled_for' => $full_appt_date 
                    ]
                ]);
            } catch (Exception $e) { }

            header("Location: book_appointment.php?success=1&vet_id=" . $vet_id);
            exit();
        }
    } catch (PDOException $e) {
        die("MySQL Error: " . $e->getMessage()); 
    }
}