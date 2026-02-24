<?php
// 1. Setup Environment and Libraries
date_default_timezone_set('Asia/Manila');
require 'vendor/autoload.php'; // FIX: This solves the Class "MongoDB\Client" not found error
include 'config/db.php';
session_start();

// 2. Initialize MongoDB Connection
try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $db = $client->happypawsvet;
} catch (Exception $e) {
    die("NoSQL Connection Failed: " . $e->getMessage());
}

// 3. Security & Parameter Validation
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

$pet_id = $_GET['pet_id'] ?? null;
if (!$pet_id) { 
    header("Location: patients.php"); 
    exit(); 
}

// 4. Fetch Relational Pet Details (MariaDB)
$pet_sql = "SELECT p.*, o.Owner_Fname, o.Owner_Lname 
            FROM pet p 
            JOIN owner o ON p.Owner_ID = o.Owner_ID 
            WHERE p.Pet_ID = ?";
$pet_stmt = $pdo->prepare($pet_sql);
$pet_stmt->execute([$pet_id]);
$pet = $pet_stmt->fetch();

// 5. Fetch NoSQL Timeline (MongoDB)
// Note: Ensure pet_id is cast to string to match how it's stored in process_complete_appointment.php
$medical_history = $db->pet_medical_history->find(
    ['pet_id' => (string)$pet_id], 
    ['sort' => ['_id' => -1]] // Display newest records at the top
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical History | <?php echo htmlspecialchars($pet['Pet_Name'] ?? 'Patient'); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="staff-dashboard-body">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div style="margin-bottom: 30px;">
            <a href="patients.php" style="color: #2bcbba; font-weight: 700; text-decoration:none;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <div class="metric-card" style="display: flex; gap: 30px; align-items: center; background: white; margin-bottom: 30px; padding: 25px; border-radius: 20px;">
            <div style="background: #e3fafc; padding: 25px; border-radius: 20px; color: #2bcbba;">
                <i class="fas fa-paw fa-3x"></i>
            </div>
            <div>
                <h1 style="margin: 0; color: #2d3436;"><?php echo htmlspecialchars($pet['Pet_Name'] ?? 'Unknown Patient'); ?></h1>
                <p style="margin: 5px 0; color: #636e72;">
                    <strong><?php echo htmlspecialchars($pet['Pet_Type'] ?? 'N/A'); ?></strong> • 
                    <?php echo htmlspecialchars($pet['Breed'] ?? 'Unknown Breed'); ?> • 
                    <?php echo htmlspecialchars($pet['Age'] ?? '0'); ?> yrs old
                </p>
                <small style="color: #adb5bd;">Owner: <?php echo htmlspecialchars(($pet['Owner_Fname'] ?? '') . " " . ($pet['Owner_Lname'] ?? '')); ?></small>
            </div>
        </div>

        <div class="metric-card" style="background: white; padding: 30px; border-radius: 20px;">
            <h3><i class="fas fa-history" style="color: #2bcbba; margin-right: 10px;"></i> Medical Timeline</h3>
            
            <div class="timeline-container" style="margin-top: 25px;">
                <?php 
                $hasHistory = false;
                foreach ($medical_history as $record): 
                    $hasHistory = true;
                ?>
                <div class="metric-card" style="margin-bottom: 20px; border-left: 5px solid #2bcbba; background: #fff; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #f1f3f5; border-left: 5px solid #2bcbba; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.85rem; color: #636e72; font-weight: 600;">
                            <i class="far fa-calendar-alt"></i> <?php echo htmlspecialchars($record['date']); ?>
                        </span>
                        <span style="background: #e3fafc; color: #0c8599; padding: 4px 12px; border-radius: 50px; font-size: 0.65rem; font-weight: 800;">
                            REF #<?php echo htmlspecialchars($record['mysql_ref'] ?? 'N/A'); ?>
                        </span>
                    </div>
                    
                    <h4 style="margin: 15px 0 10px 0; color: #2d3436; font-size: 1.15rem;"><?php echo htmlspecialchars($record['treatment']); ?></h4>
                    
                    <p style="font-size: 0.95rem; color: #4b4b4b; line-height: 1.6; background: #f8f9fa; padding: 15px; border-radius: 10px;">
                        <strong>Diagnosis & Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($record['notes'])); ?>
                    </p>
                    
                    <div style="margin-top: 15px; border-top: 1px dashed #eee; padding-top: 12px; display: flex; justify-content: space-between; align-items: center;">
                         <small style="color: #adb5bd;">Recorded by: <strong><?php echo htmlspecialchars($record['staff'] ?? 'System'); ?></strong></small>
                         <i class="fas fa-check-circle" style="color: #2bcbba; font-size: 1.1rem;"></i>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (!$hasHistory): ?>
                    <div style="text-align: center; padding: 80px 0; color: #b2bec3;">
                        <i class="fas fa-folder-open fa-4x" style="margin-bottom: 20px; opacity: 0.3;"></i>
                        <p style="font-size: 1.1rem;">No clinical treatments found in the medical history.</p>
                        <small>Complete an appointment to see records here.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>