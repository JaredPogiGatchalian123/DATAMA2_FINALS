<?php
include 'config/db.php';
$owner_id = $_GET['owner_id'] ?? 0;

try {
    // Joining with the Owner table to get the full name
    $stmt = $pdo->prepare("
        SELECT p.Pet_ID, p.Pet_Name, p.Breed, p.Pet_Type, p.Age, o.Owner_Fname, o.Owner_Lname 
        FROM Pet p 
        JOIN Owner o ON p.Owner_ID = o.Owner_ID 
        WHERE p.Owner_ID = ?
    ");
    $stmt->execute([$owner_id]);
    $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($pets);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>