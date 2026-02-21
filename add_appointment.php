<?php 
include 'config/db.php'; 
$status_msg = "";

if (isset($_POST['submit'])) {
    if ($pdo) { // Only run if MySQL is actually connected
        try {
            $pet_id = $_POST['pet_id'];
            $date = $_POST['date'];

            // MySQL: Store core business data
            $stmt = $pdo->prepare("INSERT INTO Appointments (Pet_ID, App_Date) VALUES (?, ?)");
            $stmt->execute([$pet_id, $date]);
            $mysql_id = $pdo->lastInsertId();

            // MongoDB: Store the log (Teacher's requirement)
            if ($m_manager) {
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->insert([
                    'event' => 'APPOINTMENT_CREATED',
                    'mysql_id' => (int)$mysql_id,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'author' => 'Jared Christian Gatchalian'
                ]);
                $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);
            }
            $status_msg = "<p style='color:green;'>Success! Record saved to MySQL and Logged in MongoDB.</p>";
        } catch (Exception $e) {
            $status_msg = "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        $status_msg = "<p style='color:red;'>MySQL is offline. Check XAMPP Port 3307.</p>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Appointment</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h2>Schedule New Appointment</h2>
        <?php echo $status_msg; ?>
        <form method="POST">
            <label>Pet ID:</label><br>
            <input type="number" name="pet_id" required><br><br>
            <label>Date:</label><br>
            <input type="datetime-local" name="date" required><br><br>
            <button type="submit" name="submit">Save Hybrid Record</button>
        </form>
        <br><a href="index.php">Back to Dashboard</a>
    </div>
</body>
</html>