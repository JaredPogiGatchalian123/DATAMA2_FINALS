<?php
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

try {
    // 1. Fetch Upcoming (Pending) Appointments
    $upcoming_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, s.Service_Type 
                     FROM appointment a 
                     JOIN owner o ON a.Owner_ID = o.Owner_ID 
                     JOIN service_type s ON a.Service_ID = s.Service_ID 
                     WHERE a.Status = 'Pending' 
                     ORDER BY a.Appointment_Date ASC LIMIT 5";
    $upcoming_appt = $pdo->query($upcoming_sql);

    // 2. Fetch Appointments Finished TODAY
    $finished_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, s.Service_Type 
                     FROM appointment a 
                     JOIN owner o ON a.Owner_ID = o.Owner_ID 
                     JOIN service_type s ON a.Service_ID = s.Service_ID 
                     WHERE a.Status = 'Completed' 
                     AND DATE(a.Appointment_Date) = CURDATE()
                     ORDER BY a.Appointment_Date DESC LIMIT 5";
    $finished_today = $pdo->query($finished_sql);

    // 3. Global Stats for Cards
    $count_pending = $pdo->query("SELECT COUNT(*) FROM appointment WHERE Status = 'Pending'")->fetchColumn();
    $count_low_stock = $pdo->query("SELECT COUNT(*) FROM stock s JOIN inventory i ON s.Item_ID = i.Item_ID WHERE s.Current_Stock <= i.Min_Stock_Level")->fetchColumn();
    
    // NEW: Calculate Profit (Sum of Amount_Paid for TODAY)
    $today_profit = $pdo->query("SELECT SUM(Amount_Paid) FROM payment WHERE DATE(Payment_Date) = CURDATE()")->fetchColumn() ?? 0;

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Portal | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-wrapper { margin-left: 85px; padding: 40px; background-color: #f8f9fa; min-height: 100vh; }
        
        /* Stats Grid - Updated to 3 Columns */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; border-left: 5px solid #2bcbba; }
        
        /* Card Specific Styling */
        .stat-card.warning { border-left-color: #ff7675; }
        .stat-card.profit { border-left-color: #f1c40f; } /* Gold color for Earnings */
        
        .stat-icon { font-size: 2rem; color: #2bcbba; opacity: 0.3; }
        .stat-card.warning .stat-icon { color: #ff7675; }
        .stat-card.profit .stat-icon { color: #f1c40f; }
        
        .stat-info h3 { margin: 0; font-size: 1.8rem; color: #2d3436; }
        .stat-info p { margin: 0; color: #7f8c8d; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }

        /* Appointments Side-by-Side Layout */
        .appt-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .appt-box { background: white; border-radius: 25px; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.03); }
        .appt-box h2 { font-size: 1.1rem; color: #2d3436; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f3f5; padding-bottom: 15px; }
        
        .appt-list-item { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 15px; border-radius: 15px; background: #fcfdfe; 
            border: 1px solid #f1f3f5; margin-bottom: 12px; transition: 0.3s;
        }
        .appt-list-item:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.05); border-color: #2bcbba; }
        
        .owner-name { font-weight: 700; color: #2d3436; font-size: 0.95rem; display: block; }
        .service-type { font-size: 0.8rem; color: #7f8c8d; font-weight: 600; }
        .appt-time { text-align: right; }
        .time-text { display: block; font-weight: 800; color: #2bcbba; font-size: 0.85rem; }
        .date-text { font-size: 0.7rem; color: #adb5bd; }

        .empty-state { text-align: center; padding: 40px; color: #adb5bd; font-style: italic; font-size: 0.9rem; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="margin-bottom: 30px;">
        <h1 style="color: #2d3436;">Hello, Staff <?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Jared'); ?>!</h1>
        <p style="color: #7f8c8d;">Clinic Overview for <?php echo date('M d, Y'); ?></p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-calendar-alt stat-icon"></i>
            <div class="stat-info">
                <h3><?php echo $count_pending; ?></h3>
                <p>Pending Appointments</p>
            </div>
        </div>

        <div class="stat-card warning">
            <i class="fas fa-box-open stat-icon"></i>
            <div class="stat-info">
                <h3><?php echo $count_low_stock; ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>

        <div class="stat-card profit">
            <i class="fas fa-hand-holding-usd stat-icon"></i>
            <div class="stat-info">
                <h3>₱<?php echo number_format($today_profit, 2); ?></h3>
                <p>Today's Earnings</p>
            </div>
        </div>
    </div>

    <div class="appt-container">
        <div class="appt-box">
            <h2><i class="fas fa-clock" style="color: #f39c12;"></i> Upcoming Appointments</h2>
            <?php if($upcoming_appt->rowCount() > 0): ?>
                <?php while($row = $upcoming_appt->fetch()): ?>
                <div class="appt-list-item">
                    <div>
                        <span class="owner-name"><?php echo $row['Owner_Fname'] . " " . $row['Owner_Lname']; ?></span>
                        <span class="service-type"><?php echo $row['Service_Type']; ?></span>
                    </div>
                    <div class="appt-time">
                        <span class="time-text"><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></span>
                        <span class="date-text"><?php echo date('M d', strtotime($row['Appointment_Date'])); ?></span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">No pending appointments found.</div>
            <?php endif; ?>
        </div>

        <div class="appt-box">
            <h2><i class="fas fa-check-circle" style="color: #2bcbba;"></i> Finished Today</h2>
            <?php if($finished_today->rowCount() > 0): ?>
                <?php while($row = $finished_today->fetch()): ?>
                <div class="appt-list-item" style="border-left: 4px solid #2bcbba;">
                    <div>
                        <span class="owner-name"><?php echo $row['Owner_Fname'] . " " . $row['Owner_Lname']; ?></span>
                        <span class="service-type"><?php echo $row['Service_Type']; ?></span>
                    </div>
                    <div class="appt-time">
                        <span class="time-text" style="color: #adb5bd;">PAID</span>
                        <span class="date-text">Completed</span>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">No appointments completed today.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>