<?php
include 'config/db.php'; 
session_start();

// SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Records | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-wrapper { padding: 40px; }
        .section-card { background: white; padding: 35px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); }
        .appt-table { width: 100%; border-collapse: collapse; }
        .appt-table th { text-align: left; padding: 15px; color: #636e72; font-size: 0.8rem; text-transform: uppercase; border-bottom: 2px solid #f8fafc; }
        .appt-table td { padding: 20px 15px; border-bottom: 1px solid #f8fafc; font-size: 0.95rem; }
        .status-pill { padding: 6px 14px; border-radius: 50px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; background: #e3fafc; color: #0c8599; }
    </style>
</head>
<body class="staff-dashboard-body">

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div style="margin-bottom: 30px;">
            <h2>Billing & Revenue Records</h2>
            <p style="color: #636e72;">Reliable financial transactions anchored to clinical appointments.</p>
        </div>

        <div class="section-card">
            <table class="appt-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Owner</th>
                        <th>Service</th>
                        <th>Amount Paid</th>
                        <th>Method</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Ensure $pdo is available before query
                    if (!isset($pdo)) { include 'config/db.php'; }

                    try {
                        // Using 'Payment' table and 'Amount_Paid' as per ERD
                        $query = $pdo->query("
                            SELECT p.*, o.Owner_Fname, o.Owner_Lname, s.Service_Type 
                            FROM Payment p
                            JOIN Appointment a ON p.Appointment_ID = a.Appointment_ID
                            JOIN Owner o ON a.Owner_ID = o.Owner_ID
                            JOIN Service_Type s ON a.Service_ID = s.Service_ID
                            ORDER BY p.Payment_Date DESC
                        ");

                        while($row = $query->fetch()):
                    ?>
                    <tr>
                        <td style="font-weight: 700;">#PAY-<?php echo $row['Payment_ID']; ?></td>
                        <td><?php echo htmlspecialchars($row['Owner_Fname'] . " " . $row['Owner_Lname']); ?></td>
                        <td><?php echo htmlspecialchars($row['Service_Type']); ?></td>
                        <td style="font-weight: 800; color: #2bcbba;">₱<?php echo number_format($row['Amount_Paid'], 2); ?></td>
                        <td><small><?php echo htmlspecialchars($row['Payment_Method'] ?? 'Cash'); ?></small></td>
                        <td><?php echo date('M d, Y', strtotime($row['Payment_Date'])); ?></td>
                    </tr>
                    <?php 
                        endwhile; 
                    } catch (Exception $e) {
                        echo "<tr><td colspan='6' style='text-align:center; padding:20px;'>No billing records found. Complete an appointment to generate a bill.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>