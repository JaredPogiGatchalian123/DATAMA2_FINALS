<?php
// 1. Setup Timezone
date_default_timezone_set('Asia/Manila');
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

try {
    // 1. Fetch ALL Upcoming (Pending) Appointments
    $upcoming_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, s.Service_Type, p.Pet_Name, p.Pet_Type, v.Vet_Lname 
                     FROM appointment a 
                     JOIN owner o ON a.Owner_ID = o.Owner_ID 
                     JOIN service_type s ON a.Service_ID = s.Service_ID 
                     LEFT JOIN pet p ON a.Pet_ID = p.Pet_ID
                     JOIN vet v ON a.Vet_ID = v.Vet_ID
                     WHERE a.Status = 'Pending' 
                     ORDER BY a.Appointment_Date ASC";
    $upcoming_appt = $pdo->query($upcoming_sql);

    // 2. Fetch ALL Finished Appointments
    $finished_sql = "SELECT a.*, o.Owner_Fname, o.Owner_Lname, s.Service_Type, p.Pet_Name, p.Pet_Type 
                     FROM appointment a 
                     JOIN owner o ON a.Owner_ID = o.Owner_ID 
                     JOIN service_type s ON a.Service_ID = s.Service_ID 
                     LEFT JOIN pet p ON a.Pet_ID = p.Pet_ID
                     WHERE a.Status = 'Completed' 
                     ORDER BY a.Appointment_Date DESC LIMIT 20";
    $finished_today = $pdo->query($finished_sql);

    // 3. Global Stats
    $count_pending = $pdo->query("SELECT COUNT(*) FROM appointment WHERE Status = 'Pending'")->fetchColumn();
    $count_low_stock = $pdo->query("SELECT COUNT(*) FROM stock s JOIN inventory i ON s.Item_ID = i.Item_ID WHERE s.Current_Stock <= i.Min_Stock_Level")->fetchColumn();
    
    $today_revenue_sql = "SELECT SUM(Amount_Paid) FROM payment WHERE DATE(Payment_Date) = CURDATE()";
    $today_earnings = $pdo->query($today_revenue_sql)->fetchColumn() ?? 0;

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .main-wrapper { margin-left: 85px; padding: 40px; background-color: #f8f9fa; min-height: 100vh; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 25px; margin-bottom: 40px; }
        .stat-card { background: white; padding: 25px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 20px; border-left: 5px solid #2bcbba; }
        .stat-card.warning { border-left-color: #ff7675; }
        .stat-card.profit { border-left-color: #f1c40f; }
        .stat-info h3 { margin: 0; font-size: 1.8rem; color: #2d3436; }
        .stat-info p { margin: 0; color: #7f8c8d; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }
        
        .appt-container { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .appt-box { background: white; border-radius: 25px; padding: 25px; box-shadow: 0 8px 30px rgba(0,0,0,0.03); }
        .appt-box h2 { font-size: 1.1rem; color: #2d3436; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f1f3f5; padding-bottom: 15px; }
        
        .appt-list-item { display: flex; justify-content: space-between; align-items: center; padding: 18px; border-radius: 18px; background: #fcfdfe; border: 1px solid #f1f3f5; margin-bottom: 15px; }
        .appt-main-info { display: flex; flex-direction: column; gap: 2px; }
        .owner-name-text { font-weight: 800; color: #2d3436; font-size: 1rem; }
        .pet-details { color: #2bcbba; font-size: 0.8rem; font-weight: 600; }
        .service-vet { color: #7f8c8d; font-size: 0.75rem; margin-top: 4px; display: flex; align-items: center; gap: 8px; }
        .vet-badge { background: #e3fafc; color: #0c8599; padding: 2px 8px; border-radius: 6px; font-weight: 700; font-size: 0.65rem; }
        
        .appt-time-status { text-align: right; }
        .time-text { font-weight: 800; color: #2bcbba; font-size: 0.85rem; display: block; }
        .date-subtext { font-size: 0.7rem; color: #adb5bd; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-card { background: white; padding: 35px; border-radius: 24px; width: 550px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); max-height: 90vh; overflow-y: auto; }
        .input-styled { width: 100%; padding: 12px; border: 1.5px solid #f1f5f9; border-radius: 10px; outline: none; box-sizing: border-box; }
        .section-label { font-weight: 800; color: #adb5bd; font-size: 0.75rem; text-transform: uppercase; margin: 20px 0 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }

        /* Search Box styling inside modal */
        .search-results-box { border: 1px solid #e2e8f0; border-radius: 10px; margin-top: 5px; display: none; background: white; max-height: 150px; overflow-y: auto; }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; }
        .search-item:hover { background: #f0fdfa; color: #2bcbba; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Hello, Staff <?php echo htmlspecialchars($_SESSION['staff_name'] ?? 'Staff'); ?>!</h1>
            <p style="color: #7f8c8d;">Clinic Appointment & Sale Overview</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="openSaleModal()" style="background: #f1c40f; color: white; padding: 15px 25px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer;">
                <i class="fas fa-shopping-cart"></i> QUICK SALE
            </button>
            <button onclick="openRegModal()" style="background: #2bcbba; color: white; padding: 15px 25px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer;">
                <i class="fas fa-user-plus"></i> REGISTER NEW PATIENT
            </button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-calendar-alt" style="color:#2bcbba; font-size:2rem;"></i>
            <div class="stat-info"><h3><?php echo $count_pending; ?></h3><p>Total Pending</p></div>
        </div>
        <div class="stat-card warning">
            <i class="fas fa-box-open" style="color:#ff7675; font-size:2rem;"></i>
            <div class="stat-info"><h3><?php echo $count_low_stock; ?></h3><p>Low Stock</p></div>
        </div>
        <div class="stat-card profit">
            <i class="fas fa-coins" style="color:#f1c40f; font-size:2rem;"></i>
            <div class="stat-info">
                <h3>₱<?php echo number_format($today_earnings, 2); ?></h3>
                <p>Today's Revenue</p>
            </div>
        </div>
    </div>

    <div class="appt-container">
        <div class="appt-box">
            <h2><i class="fas fa-clock" style="color: #f39c12;"></i> All Upcoming</h2>
            <?php while($row = $upcoming_appt->fetch()): ?>
                <div class="appt-list-item">
                    <div class="appt-main-info">
                        <span class="owner-name-text"><?php echo htmlspecialchars($row['Owner_Fname']." ".$row['Owner_Lname']); ?></span>
                        <span class="pet-details"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($row['Pet_Type'] ?? 'N/A'); ?>: <?php echo htmlspecialchars($row['Pet_Name'] ?? 'No Pet'); ?></span>
                        <span class="service-vet"><?php echo htmlspecialchars($row['Service_Type']); ?> <span class="vet-badge">Dr. <?php echo htmlspecialchars($row['Vet_Lname']); ?></span></span>
                    </div>
                    <div class="appt-time-status">
                        <span class="time-text"><?php echo date('h:i A', strtotime($row['Appointment_Date'])); ?></span>
                        <span class="date-subtext"><?php echo date('M d', strtotime($row['Appointment_Date'])); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="appt-box">
            <h2><i class="fas fa-check-circle" style="color: #2bcbba;"></i> Recently Finished</h2>
            <?php while($row = $finished_today->fetch()): ?>
                <div class="appt-list-item" style="border-left: 4px solid #2bcbba;">
                    <div class="appt-main-info">
                        <span class="owner-name-text"><?php echo htmlspecialchars($row['Owner_Fname']." ".$row['Owner_Lname']); ?></span>
                        <span class="pet-details"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($row['Pet_Type'] ?? 'N/A'); ?>: <?php echo htmlspecialchars($row['Pet_Name'] ?? 'No Pet'); ?></span>
                        <span class="service-vet"><?php echo htmlspecialchars($row['Service_Type']); ?></span>
                    </div>
                    <div class="appt-time-status">
                        <span class="time-text" style="color: #adb5bd;">COMPLETED</span>
                        <span class="date-subtext"><?php echo date('M d', strtotime($row['Appointment_Date'])); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<div id="regModal" class="modal-overlay">
    <div class="modal-card">
        <h2 style="margin-top: 0;"><i class="fas fa-paw" style="color: #2bcbba;"></i> Patient Registration</h2>
        
        <div class="form-group" style="position: relative; margin-bottom: 20px;">
            <label class="section-label">Find Existing Owner (Search First)</label>
            <div style="display:flex; gap:8px;">
                <input type="text" id="regOwnerSearch" class="input-styled" placeholder="Search name or phone...">
                <button type="button" onclick="searchOwnerForReg()" style="background:#2bcbba; color:white; border:none; border-radius:10px; padding:0 15px; cursor:pointer;"><i class="fas fa-search"></i></button>
            </div>
            <div id="reg_results" class="search-results-box"></div>
        </div>

        <form action="process_staff_register_pet.php" method="POST" id="regForm">
            <input type="hidden" name="existing_owner_id" id="existing_owner_id">

            <div id="new_owner_fields">
                <div class="section-label">New Owner Information</div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <input type="text" name="owner_fname" id="rfname" placeholder="First Name" class="input-styled" required>
                    <input type="text" name="owner_lname" id="rlname" placeholder="Last Name" class="input-styled" required>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <input type="email" name="owner_email" id="remail" placeholder="Email" class="input-styled" required>
                    <input type="text" name="owner_phone" id="rphone" placeholder="Phone" class="input-styled" required>
                </div>
                <input type="password" name="owner_password" id="rpass" placeholder="Account Password" class="input-styled" required style="margin-bottom:15px;">
            </div>

            <div id="owner_selected_box" style="display:none; background:#f0fdfa; padding:15px; border-radius:12px; margin-bottom:20px; color:#2bcbba; font-weight:700; border:1px dashed #2bcbba;">
                Linking pet to: <span id="selected_owner_name"></span>
                <button type="button" onclick="resetRegForm()" style="float:right; border:none; background:none; color:#ff7675; cursor:pointer; font-size:0.7rem;">RESET</button>
            </div>

            <div class="section-label">Pet Details</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <input type="text" name="pet_name" placeholder="Pet Name" class="input-styled" required>
                <select name="pet_type" class="input-styled" required>
                    <option value="Dog">Dog</option>
                    <option value="Cat">Cat</option>
                    <option value="Bird">Bird</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 80px; gap:15px; margin-bottom:15px;">
                <input type="text" name="pet_breed" placeholder="Breed" class="input-styled" required>
                <input type="number" name="pet_age" placeholder="Age" class="input-styled" required>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" style="flex: 2; background: #2bcbba; color: white; padding: 15px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">SAVE REGISTRATION</button>
                <button type="button" onclick="closeRegModal()" style="flex: 1; background: #f1f3f5; color: #7f8c8d; padding: 15px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<div id="saleModal" class="modal-overlay">
    <div class="modal-card" style="width: 400px;">
        <h2 style="margin-top: 0;"><i class="fas fa-shopping-tag" style="color: #f1c40f;"></i> Walk-in Sale</h2>
        <form action="process_quick_sale.php" method="POST">
            <div class="section-label">Select Item & Quantity</div>
            <select name="item_id" class="input-styled" required style="margin-bottom: 15px;">
                <?php
                $query = "SELECT i.Item_ID, i.Item_Name, s.Current_Stock FROM inventory i JOIN stock s ON i.Item_ID = s.Item_ID WHERE i.Category_ID IN (2, 3) AND s.Current_Stock > 0";
                $items = $pdo->query($query);
                while($it = $items->fetch()) echo "<option value='{$it['Item_ID']}'>{$it['Item_Name']} ({$it['Current_Stock']} left)</option>";
                ?>
            </select>
            <input type="number" name="quantity" value="1" min="1" class="input-styled" required style="margin-bottom: 15px;">
            <select name="payment_method" class="input-styled" required>
                <option value="Cash">Cash</option>
                <option value="E-Wallet">E-Wallet</option>
                <option value="Card">Card</option>
            </select>
            <div style="display: flex; gap: 10px; margin-top: 25px;">
                <button type="submit" style="flex: 2; background: #f1c40f; color: white; padding: 15px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">CONFIRM SALE</button>
                <button type="button" onclick="closeSaleModal()" style="flex: 1; background: #f1f3f5; color: #7f8c8d; padding: 15px; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function openRegModal() { document.getElementById('regModal').style.display = 'flex'; }
    function closeRegModal() { document.getElementById('regModal').style.display = 'none'; resetRegForm(); }
    function openSaleModal() { document.getElementById('saleModal').style.display = 'flex'; }
    function closeSaleModal() { document.getElementById('saleModal').style.display = 'none'; }

    // AJAX: Search Owner for Registration
    function searchOwnerForReg() {
        let q = document.getElementById('regOwnerSearch').value;
        if(q.length < 2) return Swal.fire('Note', 'Type at least 2 characters', 'info');
        
        fetch('search_owner.php?query=' + q)
            .then(r => r.json())
            .then(data => {
                let res = document.getElementById('reg_results');
                res.innerHTML = ''; res.style.display = 'block';
                if(data.length === 0) res.innerHTML = '<div class="search-item">No results. Proceed as new owner.</div>';
                data.forEach(o => {
                    res.innerHTML += `<div class="search-item" onclick="selectOwnerForReg(${o.Owner_ID}, '${o.Owner_Fname} ${o.Owner_Lname}')">
                        ${o.Owner_Fname} ${o.Owner_Lname} (${o.Phone})
                    </div>`;
                });
            });
    }

    function selectOwnerForReg(id, name) {
        document.getElementById('existing_owner_id').value = id;
        document.getElementById('selected_owner_name').innerText = name;
        document.getElementById('owner_selected_box').style.display = 'block';
        document.getElementById('new_owner_fields').style.display = 'none';
        document.getElementById('reg_results').style.display = 'none';
        
        // Remove 'required' from hidden fields so form can submit
        ['rfname', 'rlname', 'remail', 'rphone', 'rpass'].forEach(id => {
            document.getElementById(id).required = false;
        });
    }

    function resetRegForm() {
        document.getElementById('existing_owner_id').value = '';
        document.getElementById('owner_selected_box').style.display = 'none';
        document.getElementById('new_owner_fields').style.display = 'block';
        ['rfname', 'rlname', 'remail', 'rphone', 'rpass'].forEach(id => {
            document.getElementById(id).required = true;
        });
        document.getElementById('regForm').reset();
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('success') === 'sale') Swal.fire({ icon: 'success', title: 'Sale Completed!' });
    if (urlParams.get('success') === 'pet_reg') Swal.fire({ icon: 'success', title: 'Pet Registered!' });
</script>
</body>
</html>