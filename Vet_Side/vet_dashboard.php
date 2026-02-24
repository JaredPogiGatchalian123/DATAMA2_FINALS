<?php
include 'config/db.php';
session_start();

// Security Check: Only allow Vets to access this portal
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'vet') {
    header("Location: vet_login.php");
    exit();
}

$vet_id = $_SESSION['vet_id'];

try {
    // 1. Fetch ALL Pending Appointments specifically for this Vet (Today and Future)
    // Removed strict CURDATE() to ensure you see what staff just added
    $today_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, p.Pet_Name, p.Pet_Type, s.Service_Type 
                  FROM appointment a
                  JOIN owner o ON a.Owner_ID = o.Owner_ID
                  JOIN pet p ON a.Pet_ID = p.Pet_ID
                  JOIN service_type s ON a.Service_ID = s.Service_ID
                  WHERE a.Status = 'Pending' 
                  AND a.Vet_ID = ? 
                  ORDER BY a.Appointment_Date ASC";
    $stmt_today = $pdo->prepare($today_sql);
    $stmt_today->execute([$vet_id]);
    $today_appts = $stmt_today->fetchAll();

    // 2. Fetch the Vet's Weekly Shift Schedule
    $sched_sql = "SELECT Day_of_Week, 
                         TIME_FORMAT(Start_Time, '%h:%i %p') as Start_Time, 
                         TIME_FORMAT(End_Time, '%h:%i %p') as End_Time 
                  FROM vet_schedule 
                  WHERE Vet_ID = ? 
                  ORDER BY FIELD(Day_of_Week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')";
    $stmt_sched = $pdo->prepare($sched_sql);
    $stmt_sched->execute([$vet_id]);
    $my_schedule = $stmt_sched->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vet Dashboard | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { 
            --primary: #2bcbba; 
            --bg-light: #f8f9fa; 
            --border-color: #e2e8f0; 
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); margin: 0; }
        
        /* Updated Slim Sidebar to match Staff UI */
        .sidebar { width: 90px; background: #2d3436; color: white; height: 100vh; position: fixed; display: flex; flex-direction: column; align-items: center; padding: 30px 0; z-index: 100; }
        .sidebar-logo { color: var(--primary); font-size: 1.5rem; margin-bottom: 40px; }
        .nav-link { color: #b2bec3; font-size: 1.4rem; margin-bottom: 25px; transition: 0.3s; position: relative; }
        .nav-link:hover, .nav-link.active { color: var(--primary); }
        .logout-link { color: #ff7675 !important; margin-top: auto; font-size: 1.4rem; }

        .main-wrapper { margin-left: 90px; padding: 50px; min-height: 100vh; }
        
        .dashboard-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 30px; }
        .card { background: white; border-radius: 30px; padding: 35px; box-shadow: 0 10px 40px rgba(0,0,0,0.04); margin-bottom: 20px; }
        
        .section-header { display: flex; align-items: center; gap: 12px; margin-bottom: 25px; border-bottom: 2px solid #f8f9fa; padding-bottom: 15px; }
        .section-header i { color: var(--primary); font-size: 1.2rem; }
        .section-header h3 { margin: 0; font-size: 0.8rem; color: #636e72; text-transform: uppercase; letter-spacing: 1px; }

        .appt-item { display: flex; justify-content: space-between; align-items: center; padding: 20px; background: #fff; border: 1px solid #f1f3f5; border-radius: 20px; margin-bottom: 15px; transition: 0.2s; }
        .appt-item:hover { border-color: var(--primary); transform: translateX(5px); }
        .appt-info h4 { margin: 0; color: #2d3436; font-size: 1.1rem; font-weight: 800; }
        .appt-info p { margin: 5px 0 0; color: #7f8c8d; font-size: 0.85rem; font-weight: 600; }
        
        .badge-service { background: #f0fdfa; color: var(--primary); padding: 8px 14px; border-radius: 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .date-label { display: block; font-size: 0.75rem; color: #2bcbba; font-weight: 800; margin-bottom: 4px; }

        .sched-table { width: 100%; border-collapse: collapse; }
        .sched-day { font-weight: 700; color: #2d3436; padding: 15px 0; border-bottom: 1px solid #f8f9fa; }
        .sched-time { color: #636e72; font-weight: 600; text-align: right; border-bottom: 1px solid #f8f9fa; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-logo"><i class="fas fa-paw"></i></div>
        <a href="vet_dashboard.php" class="nav-link active" title="Dashboard"><i class="fas fa-th-large"></i></a>
        <a href="vet_history.php" class="nav-link" title="History"><i class="fas fa-history"></i></a>
        <a href="logout.php" class="logout-link" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
    </div>

    <div class="main-wrapper">
        <div style="margin-bottom: 45px;">
            <h1 style="color: #2d3436; margin: 0; font-size: 2.5rem; font-weight: 900; letter-spacing: -1px;">Welcome, Dr. <?php echo explode(' ', $_SESSION['vet_name'])[0]; ?>!</h1>
            <p style="color: #7f8c8d; font-size: 1.1rem; margin-top: 5px;">You have <b><?php echo count($today_appts); ?></b> total pending appointments.</p>
        </div>

        <div class="dashboard-grid">
            <div class="card">
                <div class="section-header">
                    <i class="fas fa-list-ul"></i>
                    <h3>Upcoming Patient Queue</h3>
                </div>

                <?php if(empty($today_appts)): ?>
                    <div style="text-align: center; padding: 80px 20px;">
                        <img src="https://cdn-icons-png.flaticon.com/512/4076/4076402.png" style="width: 80px; opacity: 0.2; margin-bottom: 20px;">
                        <p style="color: #adb5bd; font-weight: 600;">No appointments assigned yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($today_appts as $row): 
                        $isToday = (date('Y-m-d', strtotime($row['Appointment_Date'])) == date('Y-m-d'));
                    ?>
                        <div class="appt-item" <?php echo $isToday ? 'style="border-left: 5px solid #2bcbba;"' : ''; ?>>
                            <div class="appt-info">
                                <span class="date-label"><?php echo date('M d, Y', strtotime($row['Appointment_Date'])); ?></span>
                                <h4><?php echo htmlspecialchars($row['Pet_Name']); ?> <small style="color: #adb5bd; font-weight: 500;">(<?php echo $row['Pet_Type']; ?>)</small></h4>
                                <p><i class="far fa-user"></i> Owner: <?php echo htmlspecialchars($row['Owner_Fname']." ".$row['Owner_Lname']); ?></p>
                                <p><i class="far fa-clock"></i> <b><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></b></p>
                            </div>
                            <div>
                                <span class="badge-service"><?php echo $row['Service_Type']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="section-header">
                    <i class="far fa-calendar-alt"></i>
                    <h3>My Shift Schedule</h3>
                </div>

                <table class="sched-table">
                    <tbody>
                        <?php if(empty($my_schedule)): ?>
                            <tr><td colspan="2" style="text-align: center; padding: 20px; color: #adb5bd;">Schedule not set.</td></tr>
                        <?php else: ?>
                            <?php foreach($my_schedule as $s): 
                                $isTodaySched = (trim($s['Day_of_Week']) == date('l'));
                            ?>
                            <tr>
                                <td class="sched-day" <?php echo $isTodaySched ? 'style="color: var(--primary);"' : ''; ?>>
                                    <?php echo $s['Day_of_Week']; ?>
                                    <?php if($isTodaySched) echo ' <small>(Today)</small>'; ?>
                                </td>
                                <td class="sched-time"><?php echo $s['Start_Time']; ?> - <?php echo $s['End_Time']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                
            </div>
        </div>
    </div>

</body>
</html>