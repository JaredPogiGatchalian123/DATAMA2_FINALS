<?php
require 'config/db.php'; 
require 'vendor/autoload.php'; 
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->system_logs;
    
    // Fetch staff booking events from MongoDB
    $logs = $collection->find(['event' => 'STAFF_MANUAL_BOOKING'], ['sort' => ['timestamp' => -1]]);
} catch (Exception $e) {
    die("MongoDB Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Appointment Logs | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-wrapper { margin-left: 85px; padding: 40px; transition: 0.3s ease; }
        .log-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .log-table th { background: #2bcbba; color: white; padding: 15px; text-align: left; font-size: 0.85rem; text-transform: uppercase; }
        .log-table td { padding: 15px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .status-pill { padding: 5px 12px; border-radius: 20px; font-weight: bold; font-size: 0.75rem; text-transform: uppercase; background: #fff3cd; color: #856404; }
        .status-completed { background: #e3fafc; color: #0c8599; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="margin-bottom: 30px;">
        <h2><i class="fas fa-history" style="color: #2bcbba;"></i> Appointment Audit Logs</h2>
        <p style="color: #7f8c8d; font-size: 0.9rem;">Source: <b>MongoDB system_logs</b> (Hybrid Architecture)</p>
    </div>

    <table class="log-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Owner ID</th>
                <th>Owner Name</th>
                <th>Service</th>
                <th>Vet</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hasLogs = false;
            foreach ($logs as $log): 
                $hasLogs = true;
                
                // --- HYBRID LOOKUP LOGIC ---
                // 1. Get Owner Name from MySQL
                $ownerID = $log['details']['owner_id'] ?? 0;
                $ownerQuery = $pdo->prepare("SELECT Owner_Fname, Owner_Lname FROM owner WHERE Owner_ID = ?");
                $ownerQuery->execute([$ownerID]);
                $owner = $ownerQuery->fetch();
                $ownerFullName = $owner ? $owner['Owner_Fname'] . " " . $owner['Owner_Lname'] : "Unknown Owner";

                // 2. Get the Actual Vet and Service from MySQL
                // We search for the latest appointment created for this owner
                $detailsQuery = $pdo->prepare("
                    SELECT s.Service_Type, v.Vet_Lname, a.Status 
                    FROM appointment a
                    JOIN service_type s ON a.Service_ID = s.Service_ID
                    JOIN vet v ON a.Vet_ID = v.Vet_ID
                    WHERE a.Owner_ID = ? 
                    ORDER BY a.Appointment_ID DESC LIMIT 1
                ");
                $detailsQuery->execute([$ownerID]);
                $details = $detailsQuery->fetch();

                $serviceName = $details['Service_Type'] ?? 'Manual Booking';
                $vetName = $details['Vet_Lname'] ?? 'Assigned Vet';
                $status = $details['Status'] ?? 'Pending';
            ?>
            <tr>
                <td>
                    <?php 
                        if(isset($log['timestamp']) && $log['timestamp'] instanceof MongoDB\BSON\UTCDateTime) {
                            echo $log['timestamp']->toDateTime()->format('M d, Y | h:i A'); 
                        } else { echo "N/A"; }
                    ?>
                </td>
                <td><strong>#<?php echo htmlspecialchars($ownerID); ?></strong></td>
                <td><?php echo htmlspecialchars($ownerFullName); ?></td>
                <td><?php echo htmlspecialchars($serviceName); ?></td>
                <td>Dr. <?php echo htmlspecialchars($vetName); ?></td> <td>
                    <span class="status-pill <?php echo (strtolower($status) == 'completed') ? 'status-completed' : ''; ?>">
                        <?php echo htmlspecialchars(strtoupper($status)); ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>

            <?php if (!$hasLogs): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 50px; color: #ccc;">No audit logs found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>