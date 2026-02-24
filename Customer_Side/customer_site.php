<?php
// 1. SET PHP TIMEZONE TO PHILIPPINES
date_default_timezone_set('Asia/Manila');

require 'vendor/autoload.php'; 
include 'config/db.php';
session_start();

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: customer_login.php");
    exit();
}

$owner_name = $_SESSION['owner_name'] ?? 'Guest';
$owner_id   = $_SESSION['owner_id'];

// --- UPDATED PET REGISTRATION LOGIC ---
if (isset($_POST['register_pet'])) {
    $p_name = $_POST['pet_name'];
    $p_type = $_POST['pet_type'];
    $p_breed = $_POST['pet_breed'];
    $p_age = (int)$_POST['pet_age']; // Capturing the new age input
    
    // UPDATED SQL: Including 'Age' in the fields
    $stmt = $pdo->prepare("INSERT INTO Pet (Pet_Name, Pet_Type, Breed, Age, Owner_ID) VALUES (?, ?, ?, ?, ?)");
    if($stmt->execute([$p_name, $p_type, $p_breed, $p_age, $owner_id])) {
        header("Location: customer_site.php?status=pet_added#pets");
        exit();
    }
}

// NOTE: Cancellation is now handled via cancel_appointment.php to support MongoDB logs
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Portal | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-teal: #2bcbba;
            --bg-mint: #f0fdfa;
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --white: #ffffff;
        }

        html { scroll-behavior: smooth; }
        body { background-color: var(--bg-mint); font-family: 'Inter', sans-serif; margin: 0; color: var(--text-dark); }
        
        .customer-nav { background: var(--white); padding: 15px 50px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 100; }
        .nav-brand { font-size: 1.5rem; font-weight: 800; color: var(--text-dark); }
        
        .customer-container { display: flex; max-width: 1300px; margin: 30px auto; gap: 30px; padding: 0 20px; }

        .customer-sidebar { width: 280px; background: var(--white); border-radius: 24px; padding: 30px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); height: fit-content; }
        .profile-section { text-align: center; margin-bottom: 35px; }
        .large-avatar { width: 90px; height: 90px; border-radius: 50%; margin-bottom: 15px; border: 4px solid var(--bg-mint); }

        .side-menu { list-style: none; padding: 0; }
        .side-menu li { margin-bottom: 10px; }
        .side-menu a { text-decoration: none; color: var(--text-muted); display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 15px; font-weight: 600; transition: 0.3s; }
        .side-menu a:hover, .side-menu li.active a { color: var(--primary-teal); background: rgba(43, 203, 186, 0.05); }

        .booking-content { flex: 1; }
        .section-card { background: var(--white); border-radius: 24px; padding: 35px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); margin-bottom: 30px; }
        
        .form-control { width: 100%; padding: 12px; border-radius: 10px; border: 1.5px solid #f1f5f9; margin-bottom: 15px; outline: none; box-sizing: border-box; }
        .form-control:focus { border-color: var(--primary-teal); }

        .vet-item { padding: 25px; border: 1px solid #f1f5f9; border-radius: 20px; margin-bottom: 20px; transition: 0.3s; }
        .vet-item:hover { border-color: var(--primary-teal); background: #fafdfd; transform: translateY(-2px); }
        .vet-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
        .vet-initials { background: var(--bg-mint); width: 60px; height: 60px; border-radius: 15px; display: flex; align-items: center; justify-content: center; color: var(--primary-teal); font-weight: 800; font-size: 1.2rem; }
        
        .btn-teal { background: var(--primary-teal); color: white; padding: 12px 25px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; text-decoration: none; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 8px; transition: 0.3s; }
        .btn-teal:hover { background: #24b0a2; box-shadow: 0 5px 15px rgba(43, 203, 186, 0.3); }

        .slot-pill { background: #f8f9fa; border: 1px solid #eee; padding: 4px 10px; border-radius: 8px; font-size: 0.75rem; color: var(--text-dark); margin-right: 5px; }
        .see-more { color: var(--primary-teal); font-size: 0.8rem; font-weight: 700; cursor: pointer; text-decoration: none; margin-left: 10px; }

        .modal-state { display: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 30px; border-radius: 20px; width: 450px; position: relative; }
        .modal-state:checked + .see-more + .modal { display: flex; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: #aaa; }

        .appt-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .appt-table th { text-align: left; padding: 15px; color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid #f8fafc; }
        .appt-table td { padding: 20px 15px; border-bottom: 1px solid #f8fafc; font-size: 0.95rem; }
        .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: #fff9db; color: #f08c00; }
        .status-completed { background: #e3fafc; color: #0c8599; }
        .status-cancelled { background: #fff5f5; color: #c92a2a; }
    </style>
</head>
<body>

<nav class="customer-nav">
    <div class="nav-brand">🐾 HappyPaws</div>
    <div style="font-weight: 700; color: var(--text-dark);">
        Welcome back, <?php echo htmlspecialchars($owner_name); ?>
    </div>
</nav>

<div class="customer-container">
    <aside class="customer-sidebar">
        <div class="profile-section">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($owner_name); ?>&background=2bcbba&color=fff" class="large-avatar" alt="Profile">
            <h3><?php echo htmlspecialchars($owner_name); ?></h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Pet Owner Account</p>
        </div>
        <ul class="side-menu">
            <li><a href="#pets"><i class="fas fa-paw"></i> My Pets</a></li>
            <li><a href="#booking"><i class="far fa-calendar-plus"></i> Book Appointment</a></li>
            <li><a href="#appointments"><i class="fas fa-history"></i> My Appointments</a></li>
            <li style="margin-top: 30px;"><a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Sign Out</a></li>
        </ul>
    </aside>

    <main class="booking-content">
        <section id="pets" class="section-card">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-plus-circle" style="color:var(--primary-teal);"></i> Register a New Pet</h3>
            <form method="POST" style="display: grid; grid-template-columns: 1fr 1fr 1fr 100px auto; gap: 15px; align-items: start;">
                <input type="text" name="pet_name" placeholder="Pet Name" class="form-control" required>
                <select name="pet_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="Dog">Dog</option>
                    <option value="Cat">Cat</option>
                    <option value="Bird">Bird/Other</option>
                </select>
                <input type="text" name="pet_breed" placeholder="Breed" class="form-control" required>
                <input type="number" name="pet_age" placeholder="Age" class="form-control" required min="0" max="35">
                <button type="submit" name="register_pet" class="btn-teal" style="height: 45px;">REGISTER</button>
            </form>

            <div style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <h4 style="color: var(--text-muted); font-size: 0.9rem;">Registered Pets:</h4>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <?php
                    // Fetching Pets including Age
                    $pets_query = $pdo->prepare("SELECT * FROM Pet WHERE Owner_ID = ?");
                    $pets_query->execute([$owner_id]);
                    while($p = $pets_query->fetch()):
                    ?>
                    <div style="background: #f8fafc; padding: 10px 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
                        <i class="fas fa-paw" style="color: var(--primary-teal);"></i> 
                        <strong><?php echo htmlspecialchars($p['Pet_Name']); ?></strong> 
                        <span style="font-size: 0.75rem; color: #adb5bd;">
                            (<?php echo htmlspecialchars($p['Pet_Type']); ?> | <?php echo htmlspecialchars($p['Age'] ?? '0'); ?> yrs)
                        </span>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>

        <section id="booking" class="section-card">
            <header style="margin-bottom: 30px;">
                <h1 style="margin:0 0 10px; font-size: 1.8rem;">Choose your Vet</h1>
                <p style="color: var(--text-muted);">Select your preferred veterinarian to get started.</p>
            </header>

            <div class="vet-cards-list">
                <?php
                $vets = $pdo->query("SELECT Vet_ID, Vet_Fname, Vet_Lname FROM Vet ORDER BY Vet_ID ASC");
                while($v = $vets->fetch()):
                    $vetID = $v['Vet_ID'];
                ?>
                <div class="vet-item">
                    <div class="vet-header">
                        <div style="display:flex; align-items:center; gap:20px;">
                            <div class="vet-initials">
                                <?php echo strtoupper(substr($v['Vet_Fname'],0,1) . substr($v['Vet_Lname'],0,1)); ?>
                            </div>
                            <div>
                                <h4 style="margin:0;">Dr. <?php echo htmlspecialchars($v['Vet_Fname'] . " " . $v['Vet_Lname']); ?></h4>
                                <span style="color:#fab005; font-size: 0.8rem;">★★★★★ 5.0 Rating</span>
                            </div>
                        </div>
                        <a href="book_appointment.php?vet_id=<?php echo $vetID; ?>" class="btn-teal">BOOK NOW</a>
                    </div>
                    
                    <div style="font-size: 0.85rem; border-top: 1px solid #f1f5f9; padding-top: 15px;">
                        <strong>Today's Availability:</strong>
                        <?php
                        $schedPreview = $pdo->prepare("SELECT DISTINCT Start_Time FROM Vet_Schedule WHERE Vet_ID = ? LIMIT 3");
                        $schedPreview->execute([$vetID]);
                        $hasSlots = false;
                        while($s = $schedPreview->fetch()) {
                            $hasSlots = true;
                            echo "<span class='slot-pill'>" . date('h:i A', strtotime($s['Start_Time'])) . "</span>";
                        }
                        if(!$hasSlots) echo "<span style='color:#999;'>No slots today</span>";
                        ?>

                        <input type="checkbox" id="sched-toggle-<?php echo $vetID; ?>" class="modal-state">
                        <label for="sched-toggle-<?php echo $vetID; ?>" class="see-more">
                            See full schedule <i class="fas fa-chevron-right"></i>
                        </label>

                        <div class="modal">
                            <div class="modal-content">
                                <label for="sched-toggle-<?php echo $vetID; ?>" class="close-btn">&times;</label>
                                <h2 style="color:var(--primary-teal); margin-bottom: 20px;">Clinic Hours: Dr. <?php echo htmlspecialchars($v['Vet_Lname']); ?></h2>
                                <table style="width:100%; border-collapse: collapse;">
                                    <?php
                                    $fullSched = $pdo->prepare("SELECT Day_of_Week, MIN(Start_Time) as Start_Time, MAX(End_Time) as End_Time FROM Vet_Schedule WHERE Vet_ID = ? GROUP BY Day_of_Week ORDER BY FIELD(Day_of_Week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
                                    $fullSched->execute([$vetID]);
                                    while($row = $fullSched->fetch()) {
                                        echo "<tr style='border-bottom:1px solid #eee;'>
                                                <td style='padding:12px; font-weight:600;'>{$row['Day_of_Week']}</td>
                                                <td style='padding:12px;'>" . date('h:i A', strtotime($row['Start_Time'])) . " - " . date('h:i A', strtotime($row['End_Time'])) . "</td>
                                              </tr>";
                                    }
                                    ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>

        <section id="appointments" class="section-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                <h3 style="margin:0;"><i class="far fa-calendar-check" style="color:var(--primary-teal); margin-right: 10px;"></i> Your Appointments</h3>
                <a href="#booking" class="btn-teal" style="font-size: 0.8rem; padding: 10px 20px;">
                    <i class="fas fa-plus"></i> ADD AN APPOINTMENT
                </a>
            </div>
            <table class="appt-table">
                <thead>
                    <tr><th>Veterinarian</th><th>Date</th><th>Time</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php
                    $appointments = $pdo->prepare("SELECT a.*, v.Vet_Lname FROM Appointment a JOIN Vet v ON a.Vet_ID = v.Vet_ID WHERE a.Owner_ID = ? ORDER BY a.Appointment_Date DESC");
                    $appointments->execute([$owner_id]);
                    if($appointments->rowCount() > 0):
                        while($appt = $appointments->fetch()):
                    ?>
                    <tr>
                        <td style="font-weight: 600;">Dr. <?php echo htmlspecialchars($appt['Vet_Lname']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($appt['Appointment_Date'])); ?></td>
                        <td><?php echo date('h:i A', strtotime($appt['Appointment_Date'])); ?></td>
                        <td><span class="status-pill status-<?php echo strtolower($appt['Status']); ?>"><?php echo htmlspecialchars($appt['Status']); ?></span></td>
                        <td>
                            <?php if($appt['Status'] === 'Pending'): ?>
                                <a href="cancel_appointment.php?id=<?php echo $appt['Appointment_ID']; ?>" 
                                   onclick="return confirm('Are you sure you want to cancel this appointment?');" 
                                   style="color:#ff6b6b; font-weight:700; text-decoration:none;">Cancel</a>
                            <?php else: ?>
                                <span style="color:#cbd5e1;">---</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="5" style="text-align:center; padding: 50px; color: var(--text-muted);">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

</body>
</html>