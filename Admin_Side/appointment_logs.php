<?php
// 1. SET PHP TIMEZONE TO PHILIPPINES
date_default_timezone_set('Asia/Manila');

require 'vendor/autoload.php'; 
include 'config/db.php'; 
session_start();

// Security: Ensure only staff can access audit trails
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: login.php");
    exit();
}

try {
    // Connect to MongoDB
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->appointment_logs;

    // Fetch all logs, newest first
    $cursor = $collection->find([], ['sort' => ['timestamp' => -1]]);
} catch (Exception $e) {
    die("Error connecting to MongoDB: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointment Audit Logs | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-teal: #2bcbba;
            --bg-light: #f8f9fa;
            --text-dark: #2d3436;
            --text-muted: #636e72;
            --white: #ffffff;
        }

        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; margin: 0; }
        .main-wrapper { margin-left: 90px; padding: 40px; min-height: 100vh; }
        .logs-title-container { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logs-title { display: flex; align-items: center; gap: 15px; color: var(--text-dark); }
        .logs-title i { color: var(--primary-teal); font-size: 1.8rem; }
        .search-container { margin-bottom: 25px; max-width: 500px; position: relative; }
        .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #adb5bd; }
        .search-container input { width: 100%; padding: 12px 12px 12px 45px; border-radius: 12px; border: 1px solid #e2e8f0; outline: none; transition: 0.3s; }
        .log-table-container { background: var(--white); border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table thead th { background: var(--primary-teal); color: white; text-align: left; padding: 18px 20px; font-size: 0.75rem; text-transform: uppercase; }
        .log-table tbody td { padding: 18px 20px; border-bottom: 1px solid #f1f3f5; font-size: 0.85rem; color: var(--text-dark); }
        
        .status-badge { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; }
        .status-pending { background: #fff9db; color: #f08c00; }
        .status-cancelled { background: #fff5f5; color: #c92a2a; }
        .status-completed { background: #e3fafc; color: #0c8599; }
        .status-rescheduled { background: #e7f5ff; color: #1971c2; }

        .sched-time { color: #0984e3; font-weight: 700; display: block; margin-bottom: 4px; }
        .log-time { color: var(--text-muted); font-size: 0.75rem; display: block; }
        .pet-name { color: var(--primary-teal); font-weight: 700; display: flex; align-items: center; gap: 5px; margin-top: 4px; }
        .icon-btn { background: none; border: none; cursor: pointer; font-size: 1.1rem; padding: 5px; transition: 0.2s; color: #3498db; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 10000; align-items: center; justify-content: center; }
        .modal-card { background: white; padding: 40px; border-radius: 35px; width: 550px; box-shadow: 0 25px 50px rgba(0,0,0,0.2); position: relative; }
        .btn-close-modal { position: absolute; top: 25px; right: 25px; background: #f1f5f9; border: none; font-size: 1.2rem; color: #64748b; cursor: pointer; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .detail-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px dashed #e2e8f0; }
        .detail-label { font-weight: 700; color: #64748b; text-transform: uppercase; font-size: 0.75rem; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="logs-title-container">
            <div class="logs-title">
                <i class="fas fa-history"></i>
                <h2 style="margin:0;">Appointment Audit Logs</h2>
            </div>
        </div>

        <div class="search-container">
            <i class="fas fa-search"></i>
            <input type="text" id="logSearch" placeholder="Search events, pets, or owners..." onkeyup="filterLogs()">
        </div>

        <div class="log-table-container">
            <table class="log-table" id="auditTable">
                <thead>
                    <tr>
                        <th>Timeline</th>
                        <th>Owner & Pet</th>
                        <th>Event Description</th>
                        <th>Veterinarian</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursor as $log): 
                        $details = (array)$log['details'];
                        $sched = $details['scheduled_for'] ?? $details['new_schedule'] ?? $details['date'] ?? '---';
                        $vet = $details['vet_name'] ?? $details['vet'] ?? 'N/A';
                    ?>
                    <tr>
                        <td>
                            <span class="sched-time">
                                <?php echo ($sched !== '---') ? date('M d, Y | h:i A', strtotime($sched)) : '---'; ?>
                            </span>
                            <span class="log-time">
                                Logged: <?php 
                                    $date = $log['timestamp']->toDateTime();
                                    $date->setTimezone(new DateTimeZone('Asia/Manila'));
                                    echo $date->format('M d, Y | h:i A'); 
                                ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-weight: 700;"><?php echo htmlspecialchars($log['owner_name']); ?></div>
                            <span class="pet-name"><i class="fas fa-paw"></i> <?php echo htmlspecialchars($log['pet_name'] ?? 'Pet'); ?></span>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($details['event'] ?? 'System Action'); ?></div>
                            <small style="color:var(--text-muted);"><?php echo htmlspecialchars($details['service'] ?? 'Consultation'); ?></small>
                        </td>
                        <td>
                            <i class="fas fa-user-md" style="color: var(--primary-teal); margin-right: 5px;"></i>
                            <span style="font-weight: 600;"><?php echo htmlspecialchars($vet); ?></span>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($log['status']); ?>">
                                <?php echo htmlspecialchars($log['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button class="icon-btn" onclick='viewLogDetails(<?php 
                                $tempLog = (array)$log;
                                $tempLog['id_str'] = (string)$log['_id'];
                                $tempLog['formatted_sched'] = ($sched !== '---') ? date('F d, Y at h:i A', strtotime($sched)) : 'No date';
                                $tempLog['formatted_vet'] = $vet;
                                echo json_encode($tempLog); 
                            ?>)'><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="viewLogModal" class="modal-overlay">
        <div class="modal-card">
            <button class="btn-close-modal" onclick="closeModal('viewLogModal')"><i class="fas fa-times"></i></button>
            <h3><i class="fas fa-file-invoice" style="color:var(--primary-teal)"></i> Action Details</h3>
            <div id="log_view_content" style="margin-top:20px;"></div>
            <button onclick="closeModal('viewLogModal')" style="width:100%; margin-top:25px; padding:18px; background:var(--bg-light); border:1px solid #e2e8f0; border-radius:18px; font-weight:800; cursor:pointer;">CLOSE</button>
        </div>
    </div>

    <script>
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function viewLogDetails(log) {
            const details = log.details || {};
            let content = `
                <div class="detail-row"><span class="detail-label">Client</span><span style="font-weight:700;">${log.owner_name}</span></div>
                <div class="detail-row"><span class="detail-label">Patient</span><span>${log.pet_name || 'N/A'}</span></div>
                <div class="detail-row"><span class="detail-label">Service</span><span>${details.service || 'N/A'}</span></div>
                <div class="detail-row"><span class="detail-label">Action</span><span style="color:var(--primary-teal); font-weight:700;">${details.event || 'System Log'}</span></div>
                <div class="detail-row"><span class="detail-label">Status</span><span class="status-badge status-${log.status.toLowerCase()}">${log.status}</span></div>
                <div class="detail-row" style="border:none;"><span class="detail-label">Schedule Slot</span><strong>${log.formatted_sched}</strong></div>
            `;
            document.getElementById('log_view_content').innerHTML = content;
            document.getElementById('viewLogModal').style.display = 'flex';
        }

        function filterLogs() {
            let input = document.getElementById("logSearch").value.toLowerCase();
            let rows = document.querySelector("#auditTable tbody").getElementsByTagName("tr");
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = rows[i].textContent.toLowerCase().includes(input) ? "" : "none";
            }
        }
    </script>
</body>
</html>