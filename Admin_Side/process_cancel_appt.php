<?php
date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

$appt_id = $_GET['id'] ?? null;

if ($appt_id) {
    try {
        $pdo->beginTransaction();

        // 1. Fetch details for MongoDB Audit
        $stmt = $pdo->prepare("SELECT a.Appointment_Date, o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type,
                                      CONCAT(v.Vet_Fname, ' ', v.Vet_Lname) AS Full_Vet_Name
                               FROM appointment a 
                               JOIN owner o ON a.Owner_ID = o.Owner_ID 
                               JOIN pet p ON a.Pet_ID = p.Pet_ID
                               JOIN service_type s ON a.Service_ID = s.Service_ID
                               LEFT JOIN vet v ON a.Vet_ID = v.Vet_ID
                               WHERE a.Appointment_ID = ?");
        $stmt->execute([$appt_id]);
        $appt = $stmt->fetch();

        if ($appt) {
            // 2. Update Status in MariaDB
            $update = $pdo->prepare("UPDATE appointment SET Status = 'Cancelled' WHERE Appointment_ID = ?");
            $update->execute([$appt_id]);

            // 3. Log to MongoDB Audit
            $client = new MongoDB\Client("mongodb://localhost:27017");
            $client->happypawsvet->appointment_logs->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'owner_name' => $appt['Owner_Fname'] . " " . $appt['Owner_Lname'],
                'pet_name' => $appt['Pet_Name'],
                'status' => 'Cancelled',
                'details' => [
                    'service' => $appt['Service_Type'],
                    'vet_name' => !empty($appt['Full_Vet_Name']) ? "Dr. " . $appt['Full_Vet_Name'] : "Not Assigned",
                    'scheduled_for' => $appt['Appointment_Date'],
                    'event' => 'Staff marked appointment as Cancelled'
                ]
            ]);

            $pdo->commit();
            header("Location: appointments.php?success=1");
            exit();
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Cancellation Error: " . $e->getMessage());
    }
}
?>