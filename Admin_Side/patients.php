<?php
include 'config/db.php';
session_start();

// SECURITY CHECK: Ensures only logged-in staff can access the directory
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

/** * SQL QUERY: Explicitly selecting Age to ensure it is fetched
 * Joined with Owner's name for a true "Patient" list
 */
$sql = "SELECT p.Pet_ID, p.Pet_Name, p.Pet_Type, p.Breed, p.Age, 
               o.Owner_Fname, o.Owner_Lname, o.Phone 
        FROM Pet p 
        JOIN Owner o ON p.Owner_ID = o.Owner_ID 
        ORDER BY p.Pet_Name ASC";

try {
    $patients = $pdo->query($sql);
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Patient Directory | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="staff-dashboard-body">
    
    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="search-bar-container">
            <i class="fas fa-search" style="color:var(--text-muted)"></i>
            <input type="text" placeholder="Search for pets by name, breed, or owner...">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><i class="fas fa-paw"></i> Patient Directory</h2>
            <p style="color: var(--text-muted);">Total Patients: <?php echo $patients->rowCount(); ?></p>
        </div>

        <div class="metric-card" style="width: 100%; padding: 0; overflow: hidden; background: white; border-radius: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead style="background: #f8f9fc; border-bottom: 2px solid #eee;">
                    <tr>
                        <th style="padding: 15px;">Pet Name</th>
                        <th style="padding: 15px;">Species/Breed</th>
                        <th style="padding: 15px;">Age</th> 
                        <th style="padding: 15px;">Owner</th>
                        <th style="padding: 15px;">Contact</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $patients->fetch()): ?>
                    <tr style="border-bottom: 1px solid #eee;">
                        <td style="padding: 15px; font-weight: 700; color: var(--primary-teal);">
                            <?php echo htmlspecialchars($row['Pet_Name']); ?>
                        </td>
                        <td style="padding: 15px;">
                            <?php echo htmlspecialchars($row['Pet_Type'] . " (" . $row['Breed'] . ")"); ?>
                        </td>
                        <td style="padding: 15px;">
                            <strong><?php echo htmlspecialchars($row['Age'] ?? '0'); ?></strong> yrs
                        </td>
                        <td style="padding: 15px;">
                            <?php echo htmlspecialchars($row['Owner_Fname'] . " " . $row['Owner_Lname']); ?>
                        </td>
                        <td style="padding: 15px; font-weight: 600;">
                            <?php echo htmlspecialchars($row['Phone']); ?>
                        </td>
                        <td style="padding: 15px;">
                            <a href="medical_history.php?pet_id=<?php echo $row['Pet_ID']; ?>" style="text-decoration: none;">
                                <button class="auth-btn" style="padding: 8px 12px; font-size: 0.7rem; background: #6c5ce7; border: none; color: white; border-radius: 5px; cursor: pointer;">
                                    <i class="fas fa-file-medical"></i> MEDICAL HISTORY
                                </button>
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>