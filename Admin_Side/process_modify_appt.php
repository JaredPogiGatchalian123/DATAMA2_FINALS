<?php
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appt_id = $_POST['appt_id'];
    $vet_id = $_POST['vet_id'];
    $service_id = $_POST['service_id'];
    $new_date = $_POST['new_date'];

    try {
        // 1. Validation: Is the Vet working on this specific day/time?
        $day_name = date('l', strtotime($new_date));
        $time_only = date('H:i:s', strtotime($new_date));

        $sched_check = $pdo->prepare("SELECT COUNT(*) FROM vet_schedule WHERE Vet_ID = ? AND Day_of_Week = ? AND ? BETWEEN Start_Time AND End_Time");
        $sched_check->execute([$vet_id, $day_name, $time_only]);

        if ($sched_check->fetchColumn() == 0) {
            header("Location: appointments.php?error=vet_busy"); 
            exit();
        }

        // 2. Conflict Check: Is the doctor already booked for someone else at this time?
        $conflict = $pdo->prepare("SELECT COUNT(*) FROM appointment 
                                   WHERE Vet_ID = ? AND Appointment_Date = ? 
                                   AND Appointment_ID != ? AND Status = 'Pending'");
        $conflict->execute([$vet_id, $new_date, $appt_id]);

        if ($conflict->fetchColumn() > 0) {
            header("Location: appointments.php?error=vet_busy");
            exit();
        }

        // 3. Save the Changes
        $sql = "UPDATE appointment SET Vet_ID = ?, Service_ID = ?, Appointment_Date = ? WHERE Appointment_ID = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$vet_id, $service_id, $new_date, $appt_id]);

        header("Location: appointments.php?success=1");
    } catch (Exception $e) {
        die("System Error: " . $e->getMessage());
    }
}