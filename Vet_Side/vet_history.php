<?php
include 'config/db.php';
session_start();

// Security: Only Vets
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vet') {
    header("Location: vet_login.php");
    exit();
}

$vet_id = $_SESSION['vet_id'];

try {
    // Fetch History: Completed and Cancelled visits for this doctor
    $history_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, p.Pet_Name, p.Pet_Type, s.Service_Type, m.Diagnosis
                    FROM appointment a
                    JOIN owner o ON a.Owner_ID = o.Owner_ID
                    JOIN pet p ON a.Pet_ID = p.Pet_ID
                    JOIN service_type s ON a.Service_ID = s.Service_ID
                    LEFT JOIN medical_history m ON a.Appointment_ID = m.Appointment_ID
                    WHERE a.Vet_ID = ? AND a.Status IN ('Completed', 'Cancelled')
                    ORDER BY a.Appointment_Date DESC";
    $stmt = $pdo->prepare($history_sql);
    $stmt->execute([$vet_id]);
    $history_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment History | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; margin: 0; }
        .main-wrapper { margin-left: 260px; padding: 40px; min-height: 100vh; }
        
        /* Sidebar Styling */
        .sidebar { width: 260px; background: #2d3436; color: white; height: 100vh; position: fixed; padding: 30px 20px; box-sizing: border-box; }
        .sidebar h2 { color: #2bcbba; font-size: 1.5rem; margin-bottom: 5px; }
        .sidebar p { font-size: 0.85rem; color: #adb5bd; margin-bottom: 30px; }
        .nav-link { display: flex; align-items: center; gap: 15px; color: #f1f3f5; text-decoration: none; padding: 12px 15px; border-radius: 12px; transition: 0.3s; margin-bottom: 10px; }
        .nav-link:hover, .nav-link.active { background: rgba(43, 203, 186, 0.1); color: #2bcbba; }
        
        /* Table Styling */
        .history-card { background: white; border-radius: 24px; padding: 30px; box-shadow: 0 10px 40px rgba(0,0,0,0.03); }
        .history-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .history-table th { text-align: left; background: #f8f9fc; padding: 15px; font-size: 0.75rem; color: #adb5bd; text-transform: uppercase; border-bottom: 2px solid #f1f3f5; }
        .history-table td { padding: 18px 15px; border-bottom: 1px solid #f1f3f5; font-size: 0.9rem; color: #2d3436; }
        
        .status-pill { padding: 5px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-completed { background: #e3fafc; color: #0c8599; }
        .status-cancelled { background: #fff5f5; color: #fa5252; }
        
        .diagnosis-text { font-size: 0.8rem; color: #7f8c8d; font-style: italic; max-width: 250px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>HappyPaws</h2>
        <p>Dr. <?php echo htmlspecialchars($_SESSION['vet_name']); ?></p>
        <a href="vet_dashboard.php" class="nav-link"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="vet_history.php" class="nav-link active"><i class="fas fa-history"></i> Appointment History</a>
        <div style="position: absolute; bottom: 30px; width: calc(100% - 40px);">
            <a href="logout.php" class="nav-link" style="color: #ff7675;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="main-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="color: #2d3436; margin: 0;">Appointment History</h1>
            <p style="color: #7f8c8d;">Review your previous consultations and clinical notes.</p>
        </div>

        <div class="history-card">
            <table class="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Patient & Owner</th>
                        <th>Service</th>
                        <th>Medical Notes / Diagnosis</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($history_data)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 50px; color: #adb5bd;">No past appointments found.</td></tr>
                    <?php else: ?>
                        <?php foreach($history_data as $row): ?>
                            <tr>
                                <td>
                                    <strong><?php echo date('M d, Y', strtotime($row['Appointment_Date'])); ?></strong><br>
                                    <small style="color: #adb5bd;"><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></small>
                                </td>
                                <td>
                                    <span style="font-weight: 700;"><?php echo htmlspecialchars($row['Pet_Name']); ?></span><br>
                                    <small style="color: #7f8c8d;">Owner: <?php echo htmlspecialchars($row['Owner_Fname']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['Service_Type']); ?></td>
                                <td class="diagnosis-text">
                                    <?php echo !empty($row['Diagnosis']) ? htmlspecialchars($row['Diagnosis']) : '—'; ?>
                                </td>
                                <td>
                                    <span class="status-pill <?php echo ($row['Status'] == 'Completed') ? 'status-completed' : 'status-cancelled'; ?>">
                                        <?php echo $row['Status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>