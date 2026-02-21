<?php
include 'config/db.php';

// Handle completion logic (Polyglot: MySQL + MongoDB)
if(isset($_POST['complete_apt'])) {
    $id = $_POST['apt_id'];
    // 1. Update the primary record in MySQL
    $pdo->prepare("UPDATE Appointment SET Status = 'Completed' WHERE Appointment_ID = ?")->execute([$id]);
    
    // 2. Log the action in MongoDB for the live sidebar timeline
    if($m_manager) {
        $bulk = new MongoDB\Driver\BulkWrite;
        $bulk->insert([
            'action' => 'APPOINTMENT_COMPLETED',
            'id' => $id,
            'staff' => $_SESSION['username'],
            'time' => date('Y-m-d H:i:s')
        ]);
        $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);
    }
}
?>

<div class="dashboard-grid" style="display: grid; grid-template-columns: 1fr 350px; gap: 30px; padding: 20px;">
    
    <div class="main-content">
        <div class="glass-card" style="background: white; padding: 30px; border-radius: 25px; box-shadow: var(--shadow);">
            <h2 style="margin-top:0; color: var(--primary-teal); display: flex; align-items: center; gap: 10px;">
                <i class="far fa-calendar-check"></i> Clinical Schedule
            </h2>
            <table class="modern-table" style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="color: var(--text-muted); font-size: 0.85rem; text-align: left; border-bottom: 2px solid var(--bg-mint);">
                        <th style="padding: 15px;">TIME/DATE</th>
                        <th style="padding: 15px;">PET NAME</th>
                        <th style="padding: 15px;">DOCTOR</th>
                        <th style="padding: 15px;">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Fetch pending appointments from MySQL
                    $stmt = $pdo->query("SELECT a.Appointment_ID, a.Appointment_Date, p.Pet_Name, v.Vet_Lname 
                                        FROM Appointment a 
                                        JOIN Pet p ON a.Pet_ID = p.Pet_ID 
                                        JOIN Vet v ON a.Vet_ID = v.Vet_ID
                                        WHERE a.Status != 'Completed'
                                        ORDER BY a.Appointment_Date ASC");
                    
                    while($row = $stmt->fetch()) {
                        $time = date('h:i A', strtotime($row['Appointment_Date']));
                        $date = date('M d', strtotime($row['Appointment_Date']));
                        echo "<tr style='border-bottom: 1px solid #f5f5f5;'>
                                <td style='padding: 15px;'><b>$time</b><br><small style='color:var(--text-muted)'>$date</small></td>
                                <td style='padding: 15px;'><span style='color: var(--primary-teal); font-weight:700;'>{$row['Pet_Name']}</span></td>
                                <td style='padding: 15px;'>Dr. {$row['Vet_Lname']}</td>
                                <td style='padding: 15px;'>
                                    <form method='POST'>
                                        <input type='hidden' name='apt_id' value='{$row['Appointment_ID']}'>
                                        <button name='complete_apt' class='btn-mint' style='background: var(--primary-teal); color: white; border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: 600;'>Complete</button>
                                    </form>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="log-sidebar" style="background: white; padding: 30px; border-radius: 25px; box-shadow: var(--shadow); height: fit-content;">
        <h3 style="margin-bottom:25px; color: var(--text-dark); font-size: 1.2rem;">
            Today, <?php echo date('d M'); ?>
        </h3>
        
        <div class="timeline-wrapper" style="max-height: 70vh; overflow-y: auto; padding-right: 5px;">
            <?php
            if ($m_manager) {
                // MongoDB: Fetch only today's activity logs
                $query = new MongoDB\Driver\Query([], ['sort' => ['time' => -1], 'limit' => 10]);
                $rows = $m_manager->executeQuery('happypawsvet.logs', $query);
                
                foreach ($rows as $row) {
                    $logTime = date('h:i A', strtotime($row->time));
                    $staff = $row->staff ?? 'System';
                    
                    // The "Log Bubble" UI for the sidebar
                    echo "
                    <div class='log-bubble' style='border-left: 4px solid var(--primary-teal); background: #f9fdfd; padding: 15px; border-radius: 12px; margin-bottom: 15px; position: relative;'>
                        <small style='color: var(--text-muted); font-weight: 700; font-size: 0.75rem; display: block; margin-bottom: 5px;'>$logTime</small>
                        <p style='margin: 0; font-size: 0.85rem; line-height: 1.4;'>
                            <b style='color: var(--text-dark);'>$staff</b> performed action:<br>
                            <span style='color: var(--primary-teal); font-weight: 600; font-size: 0.8rem;'>".str_replace('_', ' ', $row->action)."</span>
                        </p>
                    </div>";
                }   
            }
            ?>
        </div>
    </div>
</div>