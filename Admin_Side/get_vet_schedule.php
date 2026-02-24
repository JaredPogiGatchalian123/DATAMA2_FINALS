<?php
include 'config/db.php';

$vet_id = $_GET['id'] ?? null;

if ($vet_id) {
    try {
        /**
         * UPDATED SQL:
         * Using %p ensures the output is 'AM' or 'PM' instead of just 'A'.
         * Using %h ensures a 12-hour format with leading zeros.
         */
        $stmt = $pdo->prepare("SELECT Day_of_Week, 
                                      TIME_FORMAT(Start_Time, '%h:%i %p') as Start_Time, 
                                      TIME_FORMAT(End_Time, '%h:%i %p') as End_Time 
                               FROM vet_schedule 
                               WHERE Vet_ID = ? 
                               ORDER BY FIELD(Day_of_Week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
        
        $stmt->execute([$vet_id]);
        $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Send the data back as JSON so the JavaScript in appointments.php can read it
        header('Content-Type: application/json');
        echo json_encode($schedule);

    } catch (PDOException $e) {
        // Return a JSON error message if the query fails
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    // Return empty array if no ID provided
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>