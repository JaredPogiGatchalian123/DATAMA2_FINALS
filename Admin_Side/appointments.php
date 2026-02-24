<?php
include 'config/db.php';
require 'vendor/autoload.php'; // Required for MongoDB
session_start();

// 1. SET PHP TIMEZONE
date_default_timezone_set('Asia/Manila');

// Security: Ensure only staff can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

/** * LOGIC: Handle New Booking OR Reschedule with BLOCKERS
 */
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action     = $_POST['action'];
    $service_id = $_POST['service_id'];
    $vet_id     = $_POST['vet_id'];
    $new_date   = $_POST['new_date'];
    $owner_id   = $_POST['owner_id'] ?? null;
    $appt_id    = $_POST['appt_id'] ?? 0;
    
    try {
        $appt_ts    = strtotime($new_date);
        $day_name   = date('l', $appt_ts);
        $time_only  = date('H:i:s', $appt_ts);

        // --- BLOCKER 1: Check Vet Working Hours ---
        $sched_check = $pdo->prepare("SELECT COUNT(*) FROM vet_schedule WHERE Vet_ID = ? AND Day_of_Week = ? AND ? BETWEEN Start_Time AND End_Time");
        $sched_check->execute([$vet_id, $day_name, $time_only]);

        if ($sched_check->fetchColumn() == 0) {
            header("Location: appointments.php?error=outside_hours");
            exit();
        }

        // --- BLOCKER 2: Duplicate Owner Check (1 per day) ---
        $owner_check = $pdo->prepare("SELECT COUNT(*) FROM appointment WHERE Owner_ID = ? AND DATE(Appointment_Date) = DATE(?) AND Status = 'Pending' AND Appointment_ID != ?");
        $owner_check->execute([$owner_id, $new_date, $appt_id]);
        if ($owner_check->fetchColumn() > 0) {
            header("Location: appointments.php?error=duplicate_owner");
            exit();
        }

        // --- BLOCKER 3: Time Deficit Check (30 Minute Window) ---
        $conflict_check = $pdo->prepare("SELECT COUNT(*) FROM appointment 
                                         WHERE Vet_ID = ? AND Status = 'Pending' AND Appointment_ID != ? 
                                         AND (? < DATE_ADD(Appointment_Date, INTERVAL 30 MINUTE) 
                                         AND DATE_ADD(?, INTERVAL 30 MINUTE) > Appointment_Date)");
        $conflict_check->execute([$vet_id, $appt_id, $new_date, $new_date]);

        if ($conflict_check->fetchColumn() > 0) {
            header("Location: appointments.php?error=conflict");
            exit();
        }

        // Initialize MongoDB Client
        $mongoClient = new MongoDB\Client("mongodb://localhost:27017");
        $logCol = $mongoClient->happypawsvet->appointment_logs;

        // SUCCESS: Perform Action
        if ($action == 'add_new') {
            $pet_id   = $_POST['pet_id'];
            $stmt = $pdo->prepare("INSERT INTO appointment (Owner_ID, Pet_ID, Service_ID, Vet_ID, Appointment_Date, Status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$owner_id, $pet_id, $service_id, $vet_id, $new_date]);

            // --- ADDED: Log NEW booking to MongoDB ---
            $info = $pdo->prepare("SELECT o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type, CONCAT(v.Vet_Fname, ' ', v.Vet_Lname) as Vet_Name 
                                   FROM owner o, pet p, service_type s, vet v 
                                   WHERE o.Owner_ID=? AND p.Pet_ID=? AND s.Service_ID=? AND v.Vet_ID=?");
            $info->execute([$owner_id, $pet_id, $service_id, $vet_id]);
            $d = $info->fetch();

            $logCol->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'owner_name' => $d['Owner_Fname'] . ' ' . $d['Owner_Lname'],
                'pet_name' => $d['Pet_Name'],
                'status' => 'Pending',
                'details' => [
                    'event' => 'New Appointment Created',
                    'service' => $d['Service_Type'],
                    'vet_name' => "Dr. " . $d['Vet_Name'],
                    'scheduled_for' => $new_date
                ]
            ]);

        } elseif ($action == 'reschedule') {
            // Fetch current data before updating for the log
            $oldInfo = $pdo->prepare("SELECT o.Owner_Fname, o.Owner_Lname, p.Pet_Name, s.Service_Type, CONCAT(v.Vet_Fname, ' ', v.Vet_Lname) as Vet_Name 
                                      FROM appointment a 
                                      JOIN owner o ON a.Owner_ID = o.Owner_ID JOIN pet p ON a.Pet_ID = p.Pet_ID 
                                      JOIN service_type s ON s.Service_ID = ? JOIN vet v ON v.Vet_ID = ? 
                                      WHERE a.Appointment_ID = ?");
            $oldInfo->execute([$service_id, $vet_id, $appt_id]);
            $d = $oldInfo->fetch();

            $stmt = $pdo->prepare("UPDATE appointment SET Service_ID = ?, Vet_ID = ?, Appointment_Date = ? WHERE Appointment_ID = ?");
            $stmt->execute([$service_id, $vet_id, $new_date, $appt_id]);

            // --- ADDED: Log RESCHEDULE to MongoDB ---
            $logCol->insertOne([
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'owner_name' => $d['Owner_Fname'] . ' ' . $d['Owner_Lname'],
                'pet_name' => $d['Pet_Name'],
                'status' => 'Rescheduled',
                'details' => [
                    'event' => 'Schedule Updated',
                    'service' => $d['Service_Type'],
                    'vet_name' => "Dr. " . $d['Vet_Name'],
                    'new_schedule' => $new_date
                ]
            ]);
        }
        
        header("Location: appointments.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("Database Error: " . $e->getMessage());
    }
}

/** * FETCH DATA
 */
try {
    $appt_data = $pdo->query("SELECT a.*, o.Owner_Fname, o.Owner_Lname, o.Phone, o.Owner_ID, p.Pet_Name, p.Pet_Type, s.Service_Type, CONCAT(v.Vet_Fname, ' ', v.Vet_Lname) AS Vet_Name 
                              FROM appointment a JOIN owner o ON a.Owner_ID = o.Owner_ID JOIN pet p ON a.Pet_ID = p.Pet_ID
                              JOIN service_type s ON a.Service_ID = s.Service_ID JOIN vet v ON a.Vet_ID = v.Vet_ID
                              WHERE a.Status = 'Pending' ORDER BY a.Appointment_Date ASC")->fetchAll(PDO::FETCH_ASSOC);

    $vets_list = $pdo->query("SELECT Vet_ID, Vet_Fname, Vet_Lname FROM vet")->fetchAll(PDO::FETCH_ASSOC);
    $services_list = $pdo->query("SELECT Service_ID, Service_Type FROM service_type")->fetchAll(PDO::FETCH_ASSOC);
    $owners_list = $pdo->query("SELECT Owner_ID, Owner_Fname, Owner_Lname, Phone FROM owner")->fetchAll(PDO::FETCH_ASSOC);
    $inventory_list = $pdo->query("SELECT i.Item_ID, i.Item_Name, s.Current_Stock FROM inventory i JOIN stock s ON i.Item_ID = s.Item_ID WHERE s.Current_Stock > 0")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Center | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root { 
            --primary: #2bcbba; 
            --success: #20c997;
            --danger: #ff7675;
            --bg-light: #f8f9fa; 
            --border-color: #e2e8f0; 
        }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-light); margin: 0; }
        .main-wrapper { margin-left: 90px; padding: 40px; min-height: 100vh; }
        
        .appt-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: visible; }
        .appt-table thead th { background: var(--primary); color: white; padding: 20px; text-align: left; font-size: 0.75rem; text-transform: uppercase; }
        .appt-table td { padding: 15px 20px; border-bottom: 1px solid #f1f3f5; font-size: 0.9rem; }

        /* SEARCH UI ADDED */
        .search-container { position: relative; max-width: 400px; margin-bottom: 25px; }
        .search-container input { width: 100%; padding: 12px 15px 12px 45px; border-radius: 12px; border: 1px solid var(--border-color); outline: none; transition: 0.3s; }
        .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .search-container input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(43, 203, 186, 0.1); }

        .action-btns-container { display: flex; gap: 8px; }
        .btn-action { border: none; padding: 10px 14px; border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.75rem; display: flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-finalize { background: #e6fcf5; color: var(--success); }
        .btn-finalize:hover { background: var(--success); color: white; }
        .btn-cancel-appt { background: #fff5f5; color: var(--danger); }
        .btn-cancel-appt:hover { background: var(--danger); color: white; }
        
        .icon-btn { border: none; background: #f1f5f9; padding: 10px; border-radius: 10px; cursor: pointer; color: #64748b; }
        .icon-btn:hover { background: var(--primary); color: white; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; }
        .modal-card { background: white; padding: 40px; border-radius: 35px; width: 650px; position: relative; box-shadow: 0 25px 50px rgba(0,0,0,0.2); }
        
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1.5px solid var(--primary); border-radius: 14px; max-height: 180px; overflow-y: auto; z-index: 100; box-shadow: 0 10px 25px rgba(0,0,0,0.1); display: none; margin-top: 5px; }
        .search-item { padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f8fafc; display: flex; justify-content: space-between; align-items: center; font-size: 0.9rem; }
        .search-item:hover { background: #f0fdfa; color: var(--primary); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px; }
        .full { grid-column: span 2; }
        label { display: block; font-weight: 800; font-size: 0.75rem; color: #475569; text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px; }
        input, select, textarea { width: 100%; padding: 14px; border: 2px solid var(--border-color); border-radius: 14px; font-size: 0.95rem; outline: none; transition: 0.2s; }
        input:focus, select:focus { border-color: var(--primary); }

        .btn-confirm { background: var(--primary); color: white; border: none; padding: 18px; border-radius: 18px; font-weight: 800; cursor: pointer; width: 100%; margin-top: 25px; text-transform: uppercase; letter-spacing: 1px; }
        
        .brief-row { display: flex; justify-content: space-between; padding: 14px 0; border-bottom: 1px dashed #e2e8f0; font-size: 0.95rem; }
        .brief-label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 0.75rem; }
        .brief-value { font-weight: 600; color: #1e293b; }
        .brief-value-accent { color: var(--primary); font-weight: 800; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 40px;">
            <div>
                <h2 style="margin:0; font-weight: 900; font-size: 2.2rem; letter-spacing: -1px;">Appointment Center</h2>
                <p style="color: #64748b; margin-top: 5px;">Manage patient schedules and treatment flow</p>
            </div>
            <button onclick="openStaffAddModal()" style="background: var(--primary); color: white; padding: 16px 32px; border-radius: 18px; border: none; font-weight: 800; cursor: pointer; box-shadow: 0 10px 20px rgba(43, 203, 186, 0.25);">
                <i class="fas fa-plus-circle"></i> NEW BOOKING
            </button>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="apptSearchInput" placeholder="Search patient, owner, or service..." onkeyup="filterApptTable()">
        </div>

        <table class="appt-table" id="apptQueueTable">
            <thead>
                <tr>
                    <th>Treatment Date</th>
                    <th>Patient & Owner</th>
                    <th>Medical Service</th>
                    <th>Assigned Doctor</th>
                    <th style="text-align: right; padding-right: 40px;">Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($appt_data as $row): ?>
                <tr>
                    <td><strong><?php echo date('M d, Y', strtotime($row['Appointment_Date'])); ?></strong><br><small><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></small></td>
                    <td><strong style="color: var(--primary);"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($row['Pet_Name']); ?></strong><br><small>Owner: <?php echo htmlspecialchars($row['Owner_Fname']); ?></small></td>
                    <td><span style="background: #f1f5f9; padding: 6px 12px; border-radius: 8px; font-weight: 700; color: #475569; font-size: 0.8rem;"><?php echo htmlspecialchars($row['Service_Type']); ?></span></td>
                    <td><strong style="color: #1e293b;">Dr. <?php echo htmlspecialchars($row['Vet_Name']); ?></strong></td>
                    <td align="right">
                        <div class="action-btns-container" style="justify-content: flex-end;">
                            <button class="icon-btn" onclick='viewApptDetails(<?php echo json_encode($row); ?>)' title="View Info"><i class="fas fa-eye"></i></button>
                            <button class="icon-btn" onclick='openEditModal(<?php echo json_encode($row); ?>)' title="Reschedule" style="color: #f39c12;"><i class="fas fa-calendar-alt"></i></button>
                            <button class="btn-action btn-finalize" onclick="openCompleteModal(<?php echo $row['Appointment_ID']; ?>, <?php echo $row['Owner_ID']; ?>)">
                                <i class="fas fa-check-circle"></i> FINALIZE
                            </button>
                            <button class="btn-action btn-cancel-appt" onclick="confirmCancel(<?php echo $row['Appointment_ID']; ?>)">
                                <i class="fas fa-times-circle"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div id="viewDetailModal" class="modal-overlay">
        <div class="modal-card" style="width: 500px;">
            <button class="icon-btn" onclick="closeModal('viewDetailModal')" style="float:right; background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            <h3 style="font-weight:900;"><i class="fas fa-file-invoice" style="color:var(--primary); margin-right:10px;"></i> Appointment Brief</h3>
            <div id="view_content" style="margin-top:20px;"></div>
            <button onclick="closeModal('viewDetailModal')" class="btn-confirm" style="background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; font-weight:800;">CLOSE BRIEF</button>
        </div>
    </div>

    <div id="addBookingModal" class="modal-overlay">
        <div class="modal-card">
            <button class="icon-btn" onclick="closeModal('addBookingModal')" style="float:right; background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8;">&times;</button>
            <h3 style="font-weight: 900; margin-bottom: 25px;"><i class="fas fa-calendar-plus" style="color:var(--primary); margin-right:10px;"></i> Create New Appointment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_new">
                <div class="form-group" style="position:relative; margin-bottom: 20px;">
                    <label>Search Registered Owner</label>
                    <input type="text" id="owner_search_input" placeholder="Type name to find client..." autocomplete="off" style="background:#f8fafc;">
                    <input type="hidden" name="owner_id" id="selected_owner_id" required>
                    <div id="search_results" class="search-results"></div>
                </div>
                <div id="selected_owner_box" style="display:none; background:#f0fdfa; padding:18px; border-radius:18px; margin-bottom:20px; border: 2.5px dashed var(--primary);">
                    <i class="fas fa-user-check" style="margin-right: 8px;"></i> Selected: <strong id="display_name" style="color: #1e293b;"></strong>
                </div>
                <div class="form-grid">
                    <div class="full"><label>Patient (Pet Name)</label><select name="pet_id" id="new_pet_select" required disabled><option value="">-- Select owner first --</option></select></div>
                    <div><label>Medical Service</label><select name="service_id" required><?php foreach($services_list as $s) echo "<option value='{$s['Service_ID']}'>{$s['Service_Type']}</option>"; ?></select></div>
                    <div><label>Assign Doctor</label><select name="vet_id" required><?php foreach($vets_list as $v) echo "<option value='{$v['Vet_ID']}'>Dr. {$v['Vet_Fname']} {$v['Vet_Lname']}</label>"; ?></select></div>
                    <div class="full"><label>Treatment Date & Time</label><input type="datetime-local" name="new_date" required></div>
                </div>
                <button type="submit" class="btn-confirm">CONFIRM BOOKING</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <button class="btn-close-modal" onclick="closeModal('editModal')" style="float:right; border:none; background:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            <h3 style="font-weight:900;"><i class="fas fa-edit" style="color:#f39c12; margin-right:10px;"></i> Reschedule Appointment</h3>
            <form method="POST">
                <input type="hidden" name="action" value="reschedule">
                <input type="hidden" name="appt_id" id="edit_appt_id">
                <div style="background: #fffbeb; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1.5px solid #fef3c7;">
                    Patient: <strong id="edit_pet_display" style="color: #d97706;"></strong>
                </div>
                <div class="form-grid">
                    <div><label>Change Service</label><select name="service_id" id="edit_service_id"><?php foreach($services_list as $s) echo "<option value='{$s['Service_ID']}'>{$s['Service_Type']}</option>"; ?></select></div>
                    <div><label>Change Doctor</label><select name="vet_id" id="edit_vet_id"><?php foreach($vets_list as $v) echo "<option value='{$v['Vet_ID']}'>Dr. {$v['Vet_Fname']} {$v['Vet_Lname']}</option>"; ?></select></div>
                    <div class="full"><label>New Schedule Date & Time</label><input type="datetime-local" name="new_date" id="edit_appt_date" required></div>
                </div>
                <button type="submit" class="btn-confirm" style="background:#f39c12;">SAVE MODIFICATIONS</button>
            </form>
        </div>
    </div>

    <div id="completeModal" class="modal-overlay">
        <div class="modal-card">
            <button class="btn-close-modal" onclick="closeModal('completeModal')" style="float:right; border:none; background:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            <h3 style="font-weight:900; color:var(--success);"><i class="fas fa-notes-medical" style="margin-right:10px;"></i> Visit Finalization</h3>
            <form action="process_complete_appointment.php" method="POST">
                <input type="hidden" name="appt_id" id="modal_appt_id">
                <input type="hidden" name="owner_id" id="modal_owner_id">
                <div class="form-grid">
                    <div><label>Confirm Patient</label><select name="pet_id" id="pet_confirm_select" required></select></div>
                    <div><label>Payment Selection</label><select name="payment_method" required><option value="Cash">Cash</option><option value="E-Wallet">E-Wallet (GCash/Maya)</option></select></div>
                </div>
                <div style="margin-top:20px;">
                    <label>Materials Used</label>
                    <div id="inventory_rows_container">
                        <div class="inventory-item-row" style="display:flex; gap:10px; margin-bottom:10px; background: #f8fafc; padding:12px; border-radius:12px; border: 1px solid #e2e8f0;">
                            <select name="items[]" style="flex:2;"><option value="">-- No Supplies Used --</option><?php foreach($inventory_list as $inv): ?><option value="<?php echo $inv['Item_ID']; ?>"><?php echo htmlspecialchars($inv['Item_Name']); ?> (Available: <?php echo $inv['Current_Stock']; ?>)</option><?php endforeach; ?></select>
                            <input type="number" name="item_qty[]" placeholder="Qty" min="1" style="flex:1;">
                        </div>
                    </div>
                    <button type="button" onclick="addInventoryRow()" style="background:none; border:none; color:var(--primary); font-weight:800; cursor:pointer; font-size:0.75rem;">+ ADD ANOTHER ITEM</button>
                </div>
                <div style="margin-top:20px;"><label>Diagnosis findings</label><textarea name="medical_notes" rows="4" style="width:100%; border-radius:14px; border:2px solid var(--border-color); padding:10px; box-sizing:border-box;" required placeholder="Describe the treatment and pet's condition..."></textarea></div>
                <button type="submit" class="btn-confirm" style="background:var(--success);">GENERATE RECORD</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const owners = <?php echo json_encode($owners_list); ?>;

        function closeModal(id) { $('#' + id).fadeOut(200); }
        function openStaffAddModal() { $('#addBookingModal').css('display', 'flex').hide().fadeIn(200); }

        // RESEARCH FUNCTION: Real-time table filtering
        function filterApptTable() {
            let input = document.getElementById("apptSearchInput");
            let filter = input.value.toLowerCase();
            let table = document.getElementById("apptQueueTable");
            let tr = table.getElementsByTagName("tr");

            for (let i = 1; i < tr.length; i++) {
                let text = tr[i].textContent || tr[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    tr[i].style.display = "";
                } else {
                    tr[i].style.display = "none";
                }
            }
        }

        $('#owner_search_input').on('input', function() {
            let val = $(this).val().toLowerCase();
            let results = $('#search_results');
            results.empty();
            if (val.length > 0) {
                let matches = owners.filter(o => (o.Owner_Fname + ' ' + o.Owner_Lname).toLowerCase().includes(val));
                if (matches.length > 0) {
                    matches.forEach(m => results.append(`<div class="search-item" onclick="selectOwner(${m.Owner_ID}, '${m.Owner_Fname} ${m.Owner_Lname}')"><span>${m.Owner_Fname} ${m.Owner_Lname}</span> <span style="background:#f1f5f9; font-size:0.7rem; padding:4px 10px; border-radius:8px; font-weight:700;">ID: ${m.Owner_ID}</span></div>`));
                    results.show();
                } else { results.hide(); }
            } else { results.hide(); }
        });

        function selectOwner(id, name) {
            $('#owner_search_input').val(name); $('#selected_owner_id').val(id); $('#search_results').hide();
            $('#selected_owner_box').fadeIn(); $('#display_name').text(name);
            $.getJSON('get_owner_pets.php?owner_id=' + id, function(data) {
                let d = $('#new_pet_select').empty().prop('disabled', false);
                if (data.length > 0) data.forEach(p => d.append(`<option value="${p.Pet_ID}">${p.Pet_Name}</option>`));
                else d.append(`<option value="">No pets found</option>`).prop('disabled', true);
            });
        }

        function viewApptDetails(data) {
            const formattedDate = new Date(data.Appointment_Date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
            let content = `
                <div class="brief-row"><span class="brief-label">Client Name</span><span class="brief-value">${data.Owner_Fname} ${data.Owner_Lname}</span></div>
                <div class="brief-row"><span class="brief-label">Owner ID</span><span class="brief-value">#${data.Owner_ID}</span></div>
                <div class="brief-row"><span class="brief-label">Phone</span><span class="brief-value">${data.Phone}</span></div>
                <div class="brief-row"><span class="brief-label">Patient</span><span class="brief-value brief-value-accent"><i class="fas fa-paw"></i> ${data.Pet_Name} (${data.Pet_Type})</span></div>
                <div class="brief-row"><span class="brief-label">Service</span><span class="brief-value">${data.Service_Type}</span></div>
                <div class="brief-row"><span class="brief-label">Assigned Vet</span><span class="brief-value">Dr. ${data.Vet_Name}</span></div>
                <div class="brief-row" style="border:none;"><span class="brief-label">Scheduled For</span><strong class="brief-value-accent">${formattedDate}</strong></div>`;
            $('#view_content').html(content);
            $('#viewDetailModal').css('display', 'flex').hide().fadeIn(200);
        }

        function openEditModal(data) {
            $('#edit_appt_id').val(data.Appointment_ID); $('#edit_pet_display').text(data.Pet_Name);
            $('#edit_service_id').val(data.Service_ID); $('#edit_vet_id').val(data.Vet_ID);
            $('#edit_appt_date').val(data.Appointment_Date.replace(' ', 'T').substring(0, 16));
            $('#editModal').css('display', 'flex').hide().fadeIn(200);
        }

        function openCompleteModal(apptId, ownerId) {
            $('#modal_appt_id').val(apptId); $('#modal_owner_id').val(ownerId);
            $.getJSON('get_owner_pets.php?owner_id=' + ownerId, function(data) {
                let d = $('#pet_confirm_select').empty();
                data.forEach(p => d.append(`<option value="${p.Pet_ID}">${p.Pet_Name}</option>`));
                $('#completeModal').css('display', 'flex').hide().fadeIn(300);
            });
        }

        function addInventoryRow() {
            let row = $('.inventory-item-row:first').clone();
            row.find('input, select').val('');
            $('#inventory_rows_container').append(row);
        }

        function confirmCancel(id) {
            Swal.fire({ title: 'Cancel Booking?', text: "Are you sure you want to remove this appointment?", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ff7675', confirmButtonText: 'Yes, Cancel' })
            .then(r => { if (r.isConfirmed) window.location.href = `process_cancel_appt.php?id=${id}`; });
        }

        $(document).ready(function() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('success')) Swal.fire({ icon: 'success', title: 'Task Completed', text: 'Database updated.', timer: 2000, showConfirmButton: false });
            if (params.get('error') === 'conflict') Swal.fire({ icon: 'error', title: 'Overlap Error', text: 'This doctor has another appointment within this 30-minute time frame.' });
            if (params.get('error') === 'duplicate_owner') Swal.fire({ icon: 'warning', title: 'Duplicate Found', text: 'This owner already has a pending appointment on this date.' });
            if (params.get('error') === 'outside_hours') Swal.fire({ icon: 'error', title: 'Shift Warning', text: 'The veterinarian is not on duty during this time.' });
        });
    </script>
</body>
</html>