<?php
include 'config/db.php';
session_start();

// Security check: Only staff can see this
if ($_SESSION['role'] !== 'staff') {
    die("Access Denied.");
}

if (isset($_POST['complete_apt'])) {
    $apt_id = $_POST['appointment_id'];
    $vet_id = $_POST['vet_id'];

    try {
        // 1. MYSQL UPDATE: Persistent state change
        $stmt = $pdo->prepare("UPDATE Appointment SET Status = 'Completed' WHERE Appointment_ID = ?");
        $stmt->execute([$apt_id]);

        // 2. MONGODB LOG: Detailed activity tracking
        if ($m_manager) {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert([
                'event' => 'APPOINTMENT_COMPLETED',
                'appointment_id' => (int)$apt_id,
                'performed_by_vet_id' => (int)$vet_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'author' => $_SESSION['username']
            ]);
            $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);
        }
        echo "<script>alert('Appointment Updated & Logged!');</script>";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>