<?php
include 'config/db.php';
session_start();

// 1. Establish high-speed connection to the NoSQL historical repository
$m_manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appt_id'])) {
    $appt_id = $_POST['appt_id'];
    $pet_id = $_POST['pet_id'];
    $owner_id = $_POST['owner_id'];
    $notes = $_POST['notes'];
    $pay_method = $_POST['payment_method'];

    try {
        /* =====================================================
           2. DATA RETRIEVAL: Fetch Service Metrics from MySQL
        ====================================================== */
        // We pull the price and service type to ensure data integrity across both databases
        $stmt = $pdo->prepare("SELECT s.Service_Type, s.Base_Price FROM Appointment a JOIN Service_Type s ON a.Service_ID = s.Service_ID WHERE a.Appointment_ID = ?");
        $stmt->execute([$appt_id]);
        $service = $stmt->fetch();

        /* =====================================================
           3. TRANSACTIONAL WRITES: MySQL Payment & Status Sync
        ====================================================== */
        // Transaction starts to guarantee financial and operational integrity
        $pdo->beginTransaction(); 

        // A: Update Appointment status to 'Completed'
        $pdo->prepare("UPDATE Appointment SET Status = 'Completed', Pet_ID = ? WHERE Appointment_ID = ?")->execute([$pet_id, $appt_id]);

        // B: Generate Reliable Payment Record (Linked to Appointment_ID)
        $pdo->prepare("INSERT INTO Payment (Appointment_ID, Amount_Paid, Payment_Method) VALUES (?, ?, ?)")->execute([$appt_id, $service['Base_Price'], $pay_method]);

        $pdo->commit();

        /* =====================================================
           4. HISTORICAL ARCHIVE: Insert Record into MongoDB (NoSQL)
        ====================================================== */
        // Qualitative clinical findings are routed to the flexible repository
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->insert([
            'pet_id'    => (string)$pet_id,
            'treatment' => $service['Service_Type'],
            
            // UPDATED: Added current date to the MongoDB document
            'date'      => date('Y-m-d'), 
            
            'notes'     => $notes,
            'staff'     => $_SESSION['staff_name'] ?? 'Staff', // Optional: captures who finalized it
            'mysql_ref' => (string)$appt_id // THE LINK: Logical bridge for clinical auditing
        ]);
        
        $m_manager->executeBulkWrite('happypawsvet.pet_medical_history', $bulk);

        /* =====================================================
           5. SUCCESS HANDLER: Redirect to Management Hub
        ====================================================== */
        header("Location: appointments.php?success=1");
        exit();

    } catch (Exception $e) {
        // Rollback ensures no partial or inaccurate records exist in MySQL
        if ($pdo->inTransaction()) $pdo->rollBack();
        die("Critical Sync Error: " . $e->getMessage());
    }
}
?>