<?php
// 1. Setup Timezone and Error Reporting
date_default_timezone_set('Asia/Manila');
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

// 2. Security Check
if (!isset($_SESSION['owner_id']) || !isset($_GET['id'])) {
    header("Location: customer_site.php");
    exit();
}

$appt_id = $_GET['id'];
$owner_id = $_SESSION['owner_id'];

try {
    /**
     * 3. CRITICAL FETCH: Retrieve all details BEFORE updating
     * We need the original Date and Vet details for the MongoDB log.
     */
    $info_stmt = $pdo->prepare("
        SELECT o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type, 
               a.Appointment_Date, v.Vet_Fname, v.Vet_Lname
        FROM Appointment a
        JOIN `Owner` o ON a.Owner_ID = o.Owner_ID
        LEFT JOIN Pet p ON a.Pet_ID = p.Pet_ID
        JOIN Service_Type s ON a.Service_ID = s.Service_ID
        JOIN Vet v ON a.Vet_ID = v.Vet_ID
        WHERE a.Appointment_ID = ? AND a.Owner_ID = ?
    ");
    $info_stmt->execute([$appt_id, $owner_id]);
    $info = $info_stmt->fetch();

    if ($info) {
        // 4. Update MySQL Status
        $sql = "UPDATE Appointment SET Status = 'Cancelled' WHERE Appointment_ID = ? AND Owner_ID = ?";
        $update_stmt = $pdo->prepare($sql);
        
        if ($update_stmt->execute([$appt_id, $owner_id])) {
            
            // 5. Bridge to MongoDB
            if (class_exists('MongoDB\Client')) {
                try {
                    $client = new MongoDB\Client("mongodb://localhost:27017");
                    $collection = $client->happypawsvet->appointment_logs;

                    $collection->insertOne([
                        'mysql_owner_id' => (int)$owner_id,
                        'owner_name'     => $info['Owner_Fname'] . " " . $info['Owner_Lname'],
                        'pet_name'       => $info['Pet_Name'] ?? "Unknown Pet",
                        'status'         => 'Cancelled',
                        'timestamp'      => new MongoDB\BSON\UTCDateTime(),
                        'details'        => [
                            'scheduled_for' => $info['Appointment_Date'], // FIXES "---"
                            'vet_name'      => "Dr. " . $info['Vet_Fname'] . " " . $info['Vet_Lname'], // FIXES "Not Assigned"
                            'service'       => $info['Service_Type'] ?? 'N/A',
                            'event'         => 'Customer Cancelled Appointment',
                            'booking_type'  => 'Customer Self-Service'
                        ]
                    ]);
                } catch (Exception $e) {
                    // Fail silently for user redirect
                }
            }

            header("Location: customer_site.php?cancelled=1#appointments");
            exit();
        }
    }
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}