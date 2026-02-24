<?php
// 1. Set header first so the browser knows JSON is coming
header('Content-Type: application/json');

include 'config/db.php';

// 2. Default to empty array
$pets = [];
$owner_id = isset($_GET['owner_id']) ? (int)$_GET['owner_id'] : 0;

if ($owner_id > 0) {
    try {
        /** * SQL QUERY: 
         * Using Capitalized table names to match your schema
         */
        $stmt = $pdo->prepare("
            SELECT p.Pet_ID, p.Pet_Name 
            FROM Pet p 
            WHERE p.Owner_ID = ?
        ");
        $stmt->execute([$owner_id]);
        
        // Always fetch as an array
        $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Log error server-side if needed, but return empty array to UI
        $pets = []; 
    }
}

// 3. Final output MUST be an array []
echo json_encode($pets);
exit();