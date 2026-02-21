<?php
include 'config/db.php';

// Set header to JSON for proper JavaScript handling
header('Content-Type: application/json');

$query = $_GET['query'] ?? '';

try {
    // Fetching Phone and Email is critical to distinguish identical names
    $stmt = $pdo->prepare("SELECT Owner_ID, Owner_Fname, Owner_Lname, Phone, Email 
                           FROM owner 
                           WHERE Owner_Fname LIKE ? OR Owner_Lname LIKE ? 
                           LIMIT 10");
    
    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm]);
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($results);
} catch (PDOException $e) {
    // Return error as JSON so the frontend doesn't crash
    echo json_encode(['error' => $e->getMessage()]);
}
?>