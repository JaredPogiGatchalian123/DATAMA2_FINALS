<?php
include 'config/db.php';
session_start();

$m_manager = new MongoDB\Driver\Manager("mongodb://localhost:27017");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['role'])) {
    $owner_id = $_POST['owner_id'];
    $pet_name = $_POST['pet_name'];
    $pet_type = $_POST['species']; 
    $breed    = $_POST['breed'];
    $age      = $_POST['age'];

    try {
        // 1. MySQL: Save basic pet profile
        $sql = "INSERT INTO Pet (Owner_ID, Pet_Name, Pet_Type, Breed, Age) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$owner_id, $pet_name, $pet_type, $breed, $age])) {

            // 2. MongoDB: Save Log to 'logs' collection
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert([
                'action'  => 'PET_REGISTRATION',
                'details' => "Registered $pet_name ($pet_type)",
                'pet_id'  => $pdo->lastInsertId(), // Link to MySQL ID
                'user'    => $_SESSION['staff_name'],
                'time'    => date('Y-m-d H:i:s')
            ]);
            $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);

            header("Location: staff_portal.php?status=pet_added");
            exit();
        }
    } catch (Exception $e) {
        header("Location: staff_portal.php?error=" . urlencode($e->getMessage()));
        exit();
    }
}