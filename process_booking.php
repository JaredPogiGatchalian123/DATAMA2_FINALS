<?php
require 'vendor/autoload.php'; // Required for MongoDB
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['owner_id'])) {

    // 1. Collect and Sanitize Data
    $vet_id       = $_POST['vet_id'];
    $service_type = $_POST['service_type'];
    $pet_id       = $_POST['pet_id']; // IMPORTANT: Ensure your form has a 'pet_id' select
    $date_input   = $_POST['apt_date'];
    $time_input   = $_POST['apt_time'];
    $owner_id     = $_SESSION['owner_id'];
    
    $full_appt_date = "$date_input $time_input";
    $status = 'Pending';
    $day_name = date('l', strtotime($date_input));

    try {
        // 1️⃣ Get Service_ID
        $svc_stmt = $pdo->prepare("SELECT Service_ID FROM Service_Type WHERE Service_Type = ?");
        $svc_stmt->execute([$service_type]);
        $service = $svc_stmt->fetch();
        $service_id = $service['Service_ID'] ?? null;

        // 2️⃣ Check Vet general availability
        $sched_stmt = $pdo->prepare("
            SELECT * FROM Vet_Schedule 
            WHERE Vet_ID = ? 
            AND Day_of_Week = ? 
            AND ? BETWEEN Start_Time AND End_Time
        ");
        $sched_stmt->execute([$vet_id, $day_name, $time_input]);
        $is_available = $sched_stmt->fetch();

        if (!$is_available) {
            $error_msg = "Doctor is not available at " . date('h:i A', strtotime($time_input)) . " on $day_name.";
            header("Location: book_appointment.php?vet_id=$vet_id&error=" . urlencode($error_msg));
            exit();
        }

        // 3️⃣ Check for Double-Booking
        $conflict_stmt = $pdo->prepare("
            SELECT * FROM Appointment 
            WHERE Vet_ID = ? 
            AND Appointment_Date = ? 
            AND Status != 'Cancelled'
        ");
        $conflict_stmt->execute([$vet_id, $full_appt_date]);
        if ($conflict_stmt->fetch()) {
            $error_msg = "This time slot is already booked. Please choose a different time.";
            header("Location: book_appointment.php?vet_id=$vet_id&error=" . urlencode($error_msg));
            exit();
        }

        if ($service_id) {
            // 4️⃣ Insert Appointment into MySQL
            // We include Pet_ID and Owner_ID. Staff_ID is left NULL as it's a self-booking.
            $sql = "INSERT INTO Appointment (Owner_ID, Vet_ID, Service_ID, Pet_ID, Appointment_Date, Status) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$owner_id, $vet_id, $service_id, $pet_id, $full_appt_date, $status])) {
                
                // 5️⃣ BRIDGE TO MONGODB: System Audit Log
                try {
                    // Fetch names for the log - Updated to Vet_Lname to match your 2-column Vet table
                    $info_stmt = $pdo->prepare("
                        SELECT o.Owner_Fname, o.Owner_Lname, v.Vet_Lname 
                        FROM Owner o, Vet v 
                        WHERE o.Owner_ID = ? AND v.Vet_ID = ?
                    ");
                    $info_stmt->execute([$owner_id, $vet_id]);
                    $info = $info_stmt->fetch();

                    $client = new MongoDB\Client("mongodb://localhost:27017");
                    $collection = $client->happypawsvet->appointment_logs;

                    $collection->insertOne([
                        'mysql_owner_id' => (int)$owner_id,
                        'owner_name' => $info['Owner_Fname'] . " " . $info['Owner_Lname'],
                        'status' => 'Pending',
                        'timestamp' => new MongoDB\BSON\UTCDateTime(),
                        'details' => [
                            'service'  => $service_type,
                            'vet_name' => "Dr. " . ($info['Vet_Lname'] ?? 'Staff'),
                            'booking_type' => 'Customer Self-Service'
                        ]
                    ]);
                } catch (Exception $mongo_e) {
                    // Silent fail for MongoDB so the user still sees their success
                }

                header("Location: book_appointment.php?success=1&vet_id=" . $vet_id);
                exit();
            }
        }

    } catch (PDOException $e) {
        // Output actual error for debugging during your setup
        die("Database Error: " . $e->getMessage()); 
    }
} else {
    header("Location: customer_site.php");
    exit();
}