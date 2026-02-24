<?php
date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php';
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appt_id = $_POST['appt_id'];
    $new_date = $_POST['new_date'];
    $new_service_id = $_POST['new_service_id'];
    $new_vet_id = $_POST['new_vet_id'];

    try {
        $owner_stmt = $pdo->prepare("SELECT Owner_ID FROM appointment WHERE Appointment_ID = ?");
        $owner_stmt->execute([$appt_id]);
        $owner_id = $owner_stmt->fetchColumn();

        $appt_ts = strtotime($new_date);
        $day_name = date('l', $appt_ts);
        $time_only = date('H:i:s', $appt_ts);

        // FIXED: Using Vet_ID for vet_schedule check
        $sched_check = $pdo->prepare("SELECT COUNT(*) FROM vet_schedule 
                                     WHERE Vet_ID = ? AND Day_of_Week = ? 
                                     AND ? BETWEEN Start_Time AND End_Time");
        $sched_check->execute([$new_vet_id, $day_name, $time_only]);

        if ($sched_check->fetchColumn() == 0) {
            header("Location: appointments.php?error=outside_hours");
            exit();
        }

        $durations = [1 => 15, 2 => 30, 3 => 120, 4 => 60];
        $new_svc_duration = $durations[$new_service_id] ?? 30;

        // Conflict Check: Vet Busy
        $check_vet = $pdo->prepare("SELECT COUNT(*) FROM appointment 
                      WHERE Vet_ID = ? AND Appointment_ID != ? AND Status != 'Cancelled'
                      AND (? < DATE_ADD(Appointment_Date, INTERVAL (CASE 
                                WHEN Service_ID = 3 THEN 120 
                                WHEN Service_ID = 4 THEN 60
                                ELSE 30 END) MINUTE)
                      AND DATE_ADD(?, INTERVAL ? MINUTE) > Appointment_Date)");
        $check_vet->execute([$new_vet_id, $appt_id, $new_date, $new_date, $new_svc_duration]);

        if ($check_vet->fetchColumn() > 0) {
            header("Location: appointments.php?error=vet_busy");
            exit();
        }

        // Conflict Check: Owner Busy
        $check_owner = $pdo->prepare("SELECT COUNT(*) FROM appointment 
                        WHERE Owner_ID = ? AND Appointment_ID != ? AND Status != 'Cancelled'
                        AND (? < DATE_ADD(Appointment_Date, INTERVAL 30 MINUTE)
                        AND DATE_ADD(?, INTERVAL ? MINUTE) > Appointment_Date)");
        $check_owner->execute([$owner_id, $appt_id, $new_date, $new_date, $new_svc_duration]);

        if ($check_owner->fetchColumn() > 0) {
            header("Location: appointments.php?error=owner_busy");
            exit();
        }

        $pdo->beginTransaction();

        $update = $pdo->prepare("UPDATE appointment 
                                 SET Appointment_Date = ?, Service_ID = ?, Vet_ID = ? 
                                 WHERE Appointment_ID = ?");
        $update->execute([$new_date, $new_service_id, $new_vet_id, $appt_id]);

        $details_stmt = $pdo->prepare("
            SELECT o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type, e.Lname 
            FROM owner o
            JOIN appointment a ON a.Owner_ID = o.Owner_ID
            JOIN pet p ON p.Pet_ID = a.Pet_ID
            JOIN service_type s ON s.Service_ID = ?
            JOIN employee e ON e.Employee_ID = ?
            WHERE a.Appointment_ID = ?
        ");
        $details_stmt->execute([$new_service_id, $new_vet_id, $appt_id]);
        $info = $details_stmt->fetch();

        $client = new MongoDB\Client("mongodb://localhost:27017");
        $client->happypawsvet->appointment_logs->insertOne([
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'owner_name' => $info['Owner_Fname'] . " " . $info['Owner_Lname'],
            'pet_name' => $info['Pet_Name'],
            'status' => 'Modified',
            'details' => [
                'new_service' => $info['Service_Type'],
                'new_doctor' => "Dr. " . $info['Lname'],
                'new_schedule' => $new_date,
                'event' => 'Rescheduled'
            ]
        ]);

        $pdo->commit();
        header("Location: appointments.php?success=rescheduled");
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Update Failed: " . $e->getMessage());
    }
}
?>