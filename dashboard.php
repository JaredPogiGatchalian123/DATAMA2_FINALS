<?php
include 'config/db.php';
session_start();
if (!isset($_SESSION['username'])) { header("Location: index.php"); exit(); }

// Aggregates for MySQL
$upcoming = $pdo->query("SELECT COUNT(*) FROM Appointment WHERE Status != 'Completed' OR Status IS NULL")->fetchColumn();
$finished = $pdo->query("SELECT COUNT(*) FROM Appointment WHERE Status = 'Completed'")->fetchColumn();
$revenue = $pdo->query("SELECT SUM(s.Base_Price) FROM Appointment a JOIN Service_Type s ON a.Service_ID = s.Service_ID WHERE a.Status = 'Completed'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>HappyPaws Portal</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="icon-nav">
        <i class="fas fa-home active"></i>
        <i class="fas fa-calendar-alt"></i>
        <i class="fas fa-paw"></i>
        <i class="fas fa-cog"></i>
        <div style="margin-top:auto;">
            <a href="logout.php"><i class="fas fa-sign-out-alt" style="color:#ff7675;"></i></a>
        </div>
    </div>

    <div class="main-wrapper">
        <div class="search-bar-container">
            <i class="fas fa-search" style="color:var(--text-muted)"></i>
            <input type="text" placeholder="Search for patients or medicine...">
        </div>

        <h2>Hello, <?php echo $_SESSION['username']; ?>!</h2>

        <div class="metrics-grid">
            <div class="metric-card">
                <small style="color:var(--text-muted)"><i class="fas fa-circle" style="color:var(--accent-purple)"></i> Upcoming Appt.</small>
                <h1><?php echo $upcoming; ?></h1>
            </div>
            <div class="metric-card">
                <small style="color:var(--text-muted)"><i class="fas fa-circle" style="color:#2bcbba"></i> Finished Appt.</small>
                <h1><?php echo $finished; ?></h1>
            </div>
            <div class="metric-card">
                <small style="color:var(--text-muted)"><i class="fas fa-circle" style="color:var(--accent-orange)"></i> Finance</small>
                <h1>₱<?php echo number_format($revenue); ?></h1>
            </div>
        </div>

        <div class="action-grid">
            <div class="metric-card" style="grid-row: span 2; overflow-y: auto;">
                <h3>Clinical Schedule</h3>
                <?php 
                    if($_SESSION['role'] == 'staff') { include 'staff_dash.php'; } 
                    else { include 'customer_dash.php'; } 
                ?>
            </div>
            <div class="action-tile" onclick="openBooking(1)">
                <i class="fas fa-plus-square fa-2x"></i>
                <p>Create Appointment</p>
            </div>
            <div class="action-tile" style="background:#2d3436;">
                <i class="fas fa-file-alt fa-2x"></i>
                <p>Generate Report</p>
            </div>
        </div>
    </div>

    <div class="log-sidebar">
        <h3>Today, <?php echo date('d M'); ?></h3>
        <p><small style="color:var(--text-muted)">Live Security Audit (MongoDB)</small></p>
        
        <?php
        if ($m_manager) {
            $query = new MongoDB\Driver\Query([], ['sort' => ['time' => -1], 'limit' => 8]);
            $rows = $m_manager->executeQuery('happypawsvet.logs', $query);
            foreach ($rows as $row) {
                $staffInfo = isset($row->staff) ? $row->staff : (isset($row->user) ? $row->user : "System");
                $time = date('H:i', strtotime($row->time));
                echo "<div class='log-bubble'>
                        <small style='color:var(--primary-teal)'>$time</small>
                        <p style='margin:5px 0; font-size:0.9rem;'><b>{$staffInfo}</b> performed {$row->action}</p>
                      </div>";
            }
        }
        ?>
    </div>

    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <h2 style="color:var(--primary-teal)">Book Appointment</h2>
            <form action="process_booking.php" method="POST">
                <input type="hidden" name="vet_id" id="modal_vet_id" value="1">
                <p>Select a preferred time slot:</p>
                <div class="slot-grid">
                    <label class="slot-btn"><input type="radio" name="time" value="08:00:00" required> 08:00 AM</label>
                    <label class="slot-btn"><input type="radio" name="time" value="10:30:00"> 10:30 AM</label>
                    <label class="slot-btn"><input type="radio" name="time" value="14:00:00"> 02:00 PM</label>
                    <label class="slot-btn"><input type="radio" name="time" value="16:30:00"> 04:30 PM</label>
                </div>
                <button type="submit" class="auth-btn">Confirm Booking</button>
                <button type="button" onclick="closeModal()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; margin-top:10px;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function openBooking(id) { 
            document.getElementById('modal_vet_id').value = id;
            document.getElementById('bookingModal').style.display = 'block'; 
        }
        function closeModal() { document.getElementById('bookingModal').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('bookingModal')) { closeModal(); }
        }
    </script>
</body>
</html>