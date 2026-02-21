<?php
include 'config/db.php';
session_start();

// Connection for NoSQL data
$m_manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

$pet_id = $_GET['pet_id'] ?? null;
if (!$pet_id) {
    header("Location: patients.php");
    exit();
}

// 1. Fetch Pet and Owner Details (Relational Data)
$pet_sql = "SELECT p.*, o.Owner_Fname, o.Owner_Lname 
            FROM Pet p 
            JOIN Owner o ON p.Owner_ID = o.Owner_ID 
            WHERE p.Pet_ID = ?";
$pet_stmt = $pdo->prepare($pet_sql);
$pet_stmt->execute([$pet_id]);
$pet = $pet_stmt->fetch();

// 2. Fetch Medical Records (NoSQL Collection: pet_medical_history)
$history_filter = ['pet_id' => (string)$pet_id];
$history_query = new MongoDB\Driver\Query($history_filter, ['sort' => ['date' => -1]]);

try {
    $cursor = $m_manager->executeQuery('happypawsvet.pet_medical_history', $history_query);
    // Convert cursor to array to ensure accurate count after a database wipe
    $medical_history = $cursor->toArray(); 
} catch (Exception $e) {
    $medical_history = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Medical History | <?php echo htmlspecialchars($pet['Pet_Name']); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="staff-dashboard-body">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div style="margin-bottom: 30px;">
            <a href="patients.php" style="text-decoration: none; color: #2bcbba; font-weight: 700;">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>

        <div class="metric-card" style="display: flex; gap: 30px; align-items: center; background: white; margin-bottom: 30px;">
            <div style="background: #e3fafc; padding: 25px; border-radius: 20px; color: #2bcbba;">
                <i class="fas fa-paw fa-3x"></i>
            </div>
            <div>
                <h1 style="margin: 0;"><?php echo htmlspecialchars($pet['Pet_Name']); ?></h1>
                <p style="margin: 5px 0; color: #7f8c8d;">
                    <strong><?php echo htmlspecialchars($pet['Pet_Type']); ?></strong> • <?php echo htmlspecialchars($pet['Breed']); ?> • <?php echo htmlspecialchars($pet['Age']); ?> yrs old
                </p>
                <small>Owner: <?php echo htmlspecialchars($pet['Owner_Fname'] . " " . $pet['Owner_Lname']); ?></small>
            </div>
        </div>

        <div class="metric-card" style="background: white; padding: 30px;">
            <h3><i class="fas fa-history"></i> Medical Timeline</h3>
            <div class="timeline-container" style="margin-top: 20px;">
                <?php 
                // Check if the array is empty after your wipe
                if (!empty($medical_history)): 
                    foreach ($medical_history as $record): 
                        $recordDate = isset($record->date) ? $record->date : (isset($record->date_finalized) ? $record->date_finalized : 'Unknown Date');
                ?>
                    <div class="metric-card" style="margin-bottom: 15px; border-left: 5px solid #2bcbba; background: #fdfdfd; box-shadow: none; border-top: 1px solid #eee; border-right: 1px solid #eee; border-bottom: 1px solid #eee;">
                        <div style="display: flex; justify-content: space-between;">
                            <strong><?php echo htmlspecialchars($recordDate); ?></strong>
                            <span style="color: #2bcbba; font-weight: 800; font-size: 0.7rem; text-transform: uppercase;">
                                Ref: #<?php echo htmlspecialchars($record->mysql_ref ?? 'Manual'); ?>
                            </span>
                        </div>
                        <h4 style="margin: 10px 0; color: #2bcbba;"><?php echo htmlspecialchars($record->treatment ?? 'General Treatment'); ?></h4>
                        <p style="font-size: 0.85rem; color: #4b4b4b;">
                            <strong>Notes:</strong> <?php echo htmlspecialchars($record->notes ?? 'No clinical notes provided.'); ?>
                        </p>
                        <div style="margin-top: 10px; border-top: 1px dashed #eee; padding-top: 10px;">
                             <small style="color: #adb5bd;">Recorded by: <?php echo htmlspecialchars($record->staff ?? 'System'); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php else: ?>
                    <div style="text-align: center; padding: 60px; opacity: 0.5;">
                        <i class="fas fa-folder-open fa-3x" style="margin-bottom: 15px;"></i>
                        <p>No treatments found in medical history for this patient.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>