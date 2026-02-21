<?php
include 'config/db.php';
session_start();

// Security: Ensure only staff can access this
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

/** * UPDATED QUERY: 
 * 1. Uses Capitalized Table Names (Appointment, Owner, etc.)
 * 2. Links to Staff table via Staff_ID instead of Created_By
 */
$sql = "SELECT a.*, 
               o.Owner_Fname, o.Owner_Lname, 
               s.Service_Type, s.Base_Price, 
               v.Vet_Name, 
               st.First_Name AS Staff_Fname 
        FROM Appointment a 
        JOIN Owner o ON a.Owner_ID = o.Owner_ID 
        JOIN Service_Type s ON a.Service_ID = s.Service_ID 
        JOIN Vet v ON a.Vet_ID = v.Vet_ID
        LEFT JOIN Staff st ON a.Staff_ID = st.Staff_ID
        ORDER BY a.Appointment_Date DESC";

try {
    $appointments = $pdo->query($sql);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Appointments | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .appt-table { width: 100%; border-collapse: separate; border-spacing: 0; background: white; }
        .appt-table thead th { background: #fdfdfd; color: #7f8c8d; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 20px 15px; border-bottom: 2px solid #f1f3f5; text-align: left;}
        .appt-table td { padding: 18px 15px; color: #2d3436; font-size: 0.9rem; border-bottom: 1px solid #f1f3f5; }
        .status-pill { display: inline-block; padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; }
        .status-completed { background: #e3fafc; color: #0c8599; }
        .status-pending { background: #fff9db; color: #f08c00; }
        .btn-complete { background: #20c997; color: white; padding: 8px 16px; border-radius: 8px; font-weight: 700; border: none; cursor: pointer; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 1000; align-items: center; justify-content: center; }
        .modal-card { background: white; padding: 30px; border-radius: 20px; width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.9rem; }
        
        .btn-add-appt { background: #2bcbba; color: white; padding: 10px 20px; border-radius: 10px; border: none; font-weight: 700; cursor: pointer; float: right; transition: 0.3s; }
        .btn-add-appt:hover { background: #20bf6b; transform: translateY(-2px); }

        .search-container { display: flex; gap: 8px; margin-bottom: 10px; }
        .search-input { flex: 1; padding: 10px; border: 1px solid #eee; border-radius: 8px; outline: none; }
        #search_results { max-height: 200px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; display: none; margin-bottom: 15px; background: #fff; }
        .owner-result { padding: 10px; cursor: pointer; border-bottom: 1px solid #f9f9f9; transition: 0.2s; border-left: 4px solid transparent; }
        .owner-result:hover { background: #f1fcfb; border-left-color: #2bcbba; }
        .selected-badge { background: #e8f8f7; color: #2bcbba; padding: 12px; border-radius: 10px; margin-bottom: 15px; display: none; font-weight: 700; border: 1px dashed #2bcbba; }
    </style>
</head>
<body class="staff-dashboard-body">
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div style="margin-bottom: 25px; overflow: hidden;">
            <button class="btn-add-appt" onclick="openStaffAddModal()"><i class="fas fa-plus"></i> ADD APPOINTMENT</button>
            <h2>Hello, Staff <?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Jared'); ?>!</h2>
            <p style="color: #7f8c8d; font-size: 0.9rem;">Clinic Appointment Management</p>
        </div>

        <div class="metric-card" style="width: 100%; padding: 0; background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
            <table class="appt-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Owner & Staff</th>
                        <th>Service</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $appointments->fetch()): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700;"><?php echo date('M d', strtotime($row['Appointment_Date'])); ?></div>
                            <div style="font-size: 0.75rem; color: #adb5bd;"><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 700;"><?php echo htmlspecialchars($row['Owner_Fname'] . " " . $row['Owner_Lname']); ?></div>
                            <div style="font-size: 0.7rem; color: #adb5bd;">By: <?php echo htmlspecialchars($row['Staff_Fname'] ?? 'Self-Booked'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($row['Service_Type']); ?></td>
                        <td>Dr. <?php echo htmlspecialchars($row['Vet_Name']); ?></td>
                        <td>
                            <?php $isComp = ($row['Status'] == 'Completed'); ?>
                            <span class="status-pill <?php echo $isComp ? 'status-completed' : 'status-pending'; ?>"><?php echo strtoupper($row['Status']); ?></span>
                        </td>
                        <td>
                            <?php if(!$isComp): ?>
                                <button onclick="openCompleteModal(<?php echo $row['Appointment_ID']; ?>, <?php echo $row['Owner_ID']; ?>)" class="btn-complete">COMPLETE</button>
                            <?php else: ?>
                                <span style="color: #20c997; font-weight: 700;"><i class="fas fa-check-double"></i> Done</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="staffAddModal" class="modal-overlay">
        <div class="modal-card">
            <h3><i class="fas fa-calendar-plus" style="color:#2bcbba"></i> Add New Appointment</h3>
            
            <div class="form-group">
                <label>Find Owner</label>
                <div class="search-container">
                    <input type="text" id="ownerQuery" class="search-input" placeholder="Type name (e.g. Jared)...">
                    <button type="button" onclick="searchOwner()" style="padding:10px 15px; background:#2bcbba; color:white; border:none; border-radius:8px; cursor:pointer;"><i class="fas fa-search"></i></button>
                </div>
                <div id="search_results"></div>
            </div>

            <form action="process_staff_appt.php" method="POST">
                <div id="selected_owner_box" class="selected-badge">
                    <i class="fas fa-user-check"></i> Selected: <span id="display_name"></span>
                </div>
                <input type="hidden" name="owner_id" id="owner_id_hidden" required>

                <div class="form-group">
                    <label>Pet Name</label>
                    <select name="pet_id" id="add_pet_dropdown" style="width:100%;" required>
                        <option value="">-- Search Owner First --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Service & Vet</label>
                    <div style="display:flex; gap:10px;">
                        <select name="service_id" required style="flex:1; padding:10px; border-radius:8px; border:1px solid #eee;">
                            <?php
                            $services = $pdo->query("SELECT * FROM Service_Type");
                            while($s = $services->fetch()) echo "<option value='{$s['Service_ID']}'>{$s['Service_Type']}</option>";
                            ?>
                        </select>
                        <select name="vet_id" required style="flex:1; padding:10px; border-radius:8px; border:1px solid #eee;">
                            <?php
                            $vets = $pdo->query("SELECT * FROM Vet");
                            while($v = $vets->fetch()) echo "<option value='{$v['Vet_ID']}'>Dr. {$v['Vet_Name']}</option>";
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="appt_date" required style="width:100%; padding:10px; border-radius:8px; border:1px solid #eee;">
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-complete" style="flex: 1;">SAVE APPOINTMENT</button>
                    <button type="button" onclick="closeStaffAddModal()" style="flex: 1; background: #adb5bd; color: white; border: none; border-radius: 8px;">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <div id="completeModal" class="modal-overlay">
        <div class="modal-card">
            <h3><i class="fas fa-file-medical"></i> Finalize Visit & Stock</h3>
            <form action="process_complete_appointment.php" method="POST">
                <input type="hidden" name="appt_id" id="modal_appt_id">
                <input type="hidden" name="owner_id" id="modal_owner_id">
                
                <div class="form-group">
                    <label>Supplies Used (Multi-Select Update)</label>
                    <select name="items_used[]" id="stock_select" style="width: 100%;" multiple="multiple">
                        <?php
                        // Fetch inventory items with active stock from Inventory/Stock tables
                        $stock = $pdo->query("SELECT i.Item_ID, i.Item_Name, s.Current_Stock 
                                             FROM Inventory i 
                                             JOIN Stock s ON i.Item_ID = s.Item_ID 
                                             WHERE s.Current_Stock > 0");
                        while($s = $stock->fetch()) {
                            echo "<option value='{$s['Item_ID']}'>{$s['Item_Name']} ({$s['Current_Stock']} left)</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Confirm Pet</label>
                    <select name="pet_id" id="pet_select" style="width: 100%;" required></select>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required style="width:100%; padding:12px; border:2px solid #f0f0f0; border-radius:10px;">
                        <option value="Cash">Cash</option>
                        <option value="E-Wallet">GCash / Maya</option>
                        <option value="Card">Card</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Medical Notes (Saves to MongoDB)</label>
                    <textarea name="medical_notes" rows="3" style="width:100%; padding:12px; border:2px solid #f0f0f0; border-radius:10px;" placeholder="Diagnosis and Treatment..." required></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-complete" style="flex: 1;">SAVE & BILL</button>
                    <button type="button" onclick="closeCompleteModal()" style="flex: 1; background: #adb5bd; color: white; border: none; border-radius: 8px;">CANCEL</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('#stock_select').select2({ placeholder: "Search and select multiple supplies...", allowClear: true });
            $('#pet_select, #add_pet_dropdown').select2({ placeholder: "-- Select --" });
            
            if (new URLSearchParams(window.location.search).has('success')) {
                Swal.fire({ title: 'Success!', text: 'Appointment Completed and Stock Updated.', icon: 'success', confirmButtonColor: '#2bcbba' });
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function openStaffAddModal() { document.getElementById('staffAddModal').style.display = 'flex'; }
        function closeStaffAddModal() { document.getElementById('staffAddModal').style.display = 'none'; }
        
        function searchOwner() {
            let q = document.getElementById('ownerQuery').value;
            if(q.length < 2) return alert("Please type at least 2 letters");
            
            fetch('search_owner.php?query=' + q)
                .then(r => r.json()).then(data => {
                    let res = document.getElementById('search_results');
                    res.innerHTML = ''; res.style.display = 'block';
                    
                    if(data.length === 0) {
                        res.innerHTML = '<div class="owner-result">No results found.</div>';
                    } else {
                        data.forEach(o => {
                            res.innerHTML += `
                                <div class="owner-result" onclick="selectOwner(${o.Owner_ID}, '${o.Owner_Fname} ${o.Owner_Lname}')">
                                    <div style="font-weight: 700;">${o.Owner_Fname} ${o.Owner_Lname} <span style="color:#2bcbba; font-size:0.75rem;">ID: #${o.Owner_ID}</span></div>
                                    <div style="font-size: 0.75rem; color: #7f8c8d;">
                                        <i class="fas fa-phone"></i> ${o.Phone || 'N/A'}
                                    </div>
                                </div>`;
                        });
                    }
                });
        }

        function selectOwner(id, name) {
            document.getElementById('owner_id_hidden').value = id;
            document.getElementById('display_name').innerText = name;
            document.getElementById('selected_owner_box').style.display = 'block';
            document.getElementById('search_results').style.display = 'none';
            fetch('get_owner_pets.php?owner_id=' + id)
                .then(r => r.json()).then(data => {
                    let d = $('#add_pet_dropdown').empty();
                    if(data.length === 0) {
                        d.append(new Option("No pets registered", ""));
                    } else {
                        data.forEach(p => d.append(new Option(`${p.Pet_Name} (${p.Breed})`, p.Pet_ID)));
                    }
                });
        }

        function openCompleteModal(apptId, ownerId) {
            document.getElementById('modal_appt_id').value = apptId;
            document.getElementById('modal_owner_id').value = ownerId;
            fetch('get_owner_pets.php?owner_id=' + ownerId)
                .then(r => r.json()).then(data => {
                    let s = $('#pet_select').empty();
                    data.forEach(p => s.append(new Option(p.Pet_Name, p.Pet_ID)));
                    document.getElementById('completeModal').style.display = 'flex';
                });
        }
        function closeCompleteModal() { document.getElementById('completeModal').style.display = 'none'; }
    </script>
</body> 
</html>