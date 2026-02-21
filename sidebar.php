<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<div id="sidebar" class="sidebar">

    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>

    <a href="staff_portal.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'staff_portal.php') ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>

    <a href="appointments.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'appointments.php') ? 'active' : ''; ?>">
        <i class="fas fa-calendar-plus"></i>
        <span>Appointments</span>
    </a>

    <a href="inventory.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'inventory.php') ? 'active' : ''; ?>">
        <i class="fas fa-boxes"></i>
        <span>Inventory</span>
    </a>

    <a href="patients.php" class="nav-item <?php echo (basename($_SERVER['PHP_SELF']) == 'patients.php') ? 'active' : ''; ?>">
        <i class="fas fa-user-friends"></i>
        <span>Patients</span>
    </a>

    <div class="nav-item logs-toggle" onclick="toggleLogs()">
        <i class="fas fa-history"></i>
        <span>System Logs</span>
        <i class="fas fa-chevron-down arrow" id="logsArrow"></i>
    </div>

    <div class="dropdown" id="logsMenu">
        <a href="appointment_logs.php">Appointment Logs</a>
        <a href="payment_logs.php">Payment Logs</a>
        <a href="inventory_logs.php">Inventory Logs</a>
    </div>

    <div style="margin-top:auto;">
        <a href="logout.php" class="nav-item logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sign Out</span>
        </a>
    </div>

</div>

<style>
/* ===== SIDEBAR BASE ===== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 85px;
    height: 100vh;
    background: linear-gradient(180deg, #ffffff, #f4f9f9);
    box-shadow: 4px 0 20px rgba(0,0,0,0.05);
    transition: 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 1000;
}

.sidebar.expanded {
    width: 250px;
}

/* MENU TOGGLE */
.menu-toggle {
    text-align: center;
    padding: 20px 0;
    cursor: pointer;
    color: #2bcbba;
    font-size: 1.3rem;
}

/* NAV ITEMS */
.nav-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 25px;
    text-decoration: none;
    color: #7f8c8d;
    font-weight: 500;
    transition: 0.2s;
    position: relative;
    cursor: pointer;
}

.sidebar:not(.expanded) .nav-item {
    justify-content: center;
    padding: 18px 0;
}

.nav-item i {
    min-width: 25px;
    text-align: center;
    font-size: 1.1rem;
}

.nav-item span {
    display: none;
    white-space: nowrap;
}

.sidebar.expanded .nav-item span {
    display: inline;
}

/* HOVER & ACTIVE */
.nav-item:hover {
    background: rgba(43, 203, 186, 0.08);
    color: #2bcbba;
}

.nav-item.active {
    background: rgba(43, 203, 186, 0.12);
    color: #2bcbba;
    border-left: 4px solid #2bcbba;
}

/* ===== ARROW SYSTEM - FIXED SPECIFICITY ===== */
.arrow {
    margin-left: auto; 
    font-size: 0.8rem !important;
    transition: 0.3s;
    display: none !important; /* Force hidden when collapsed */
}

/* This selector is more specific to force the arrow to show */
.sidebar.expanded .nav-item .arrow {
    display: inline-block !important;
}

.rotate {
    transform: rotate(180deg);
}

/* ===== DROPDOWN ===== */
.dropdown {
    display: none;
    flex-direction: column;
    background: #f9fbfb;
}

.dropdown a {
    padding: 12px 25px 12px 65px;
    text-decoration: none;
    color: #7f8c8d;
    font-size: 0.9rem;
    transition: 0.2s;
}

.dropdown a:hover {
    background: rgba(43, 203, 186, 0.08);
    color: #2bcbba;
}

.logout {
    margin-bottom: 30px;
}

/* RESPONSIVE MARGIN */
.main-wrapper {
    margin-left: 85px;
    transition: 0.3s ease;
}

#sidebar.expanded ~ .main-wrapper {
    margin-left: 250px;
}
</style>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const dropdown = document.getElementById('logsMenu');
    const arrow = document.getElementById('logsArrow');

    sidebar.classList.toggle('expanded');

    if (!sidebar.classList.contains('expanded')) {
        dropdown.style.display = "none";
        arrow.classList.remove("rotate");
    }
}

function toggleLogs() {
    const sidebar = document.getElementById('sidebar');
    const menu = document.getElementById('logsMenu');
    const arrow = document.getElementById('logsArrow');

    if (!sidebar.classList.contains('expanded')) {
        toggleSidebar();
    }

    if (menu.style.display === "flex") {
        menu.style.display = "none";
        arrow.classList.remove("rotate");
    } else {
        menu.style.display = "flex";
        arrow.classList.add("rotate");
    }
}
</script>