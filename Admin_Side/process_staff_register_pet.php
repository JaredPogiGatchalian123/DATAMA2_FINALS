<?php
include 'config/db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $existing_id = $_POST['existing_owner_id'] ?? '';
    
    $pet_name = $_POST['pet_name'];
    $pet_type = $_POST['pet_type'];
    $pet_breed = $_POST['pet_breed'];
    $pet_age = $_POST['pet_age'];

    try {
        $pdo->beginTransaction();

        if (!empty($existing_id)) {
            // SCENARIO 1: Owner already exists
            $owner_id = $existing_id;
        } else {
            // SCENARIO 2: Create brand new owner
            $fname = $_POST['owner_fname'];
            $lname = $_POST['owner_lname'];
            $email = $_POST['owner_email'];
            $phone = $_POST['owner_phone'];
            $pass  = password_hash($_POST['owner_password'], PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO owner (Owner_Fname, Owner_Lname, Email, Phone, Password) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$fname, $lname, $email, $phone, $pass]);
            $owner_id = $pdo->lastInsertId();
        }

        // Final Step: Insert the Pet for the owner (new or existing)
        $stmt_pet = $pdo->prepare("INSERT INTO pet (Owner_ID, Pet_Name, Pet_Type, Breed, Age) VALUES (?, ?, ?, ?, ?)");
        $stmt_pet->execute([$owner_id, $pet_name, $pet_type, $pet_breed, $pet_age]);

        $pdo->commit();
        header("Location: staff_portal.php?success=pet_reg");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error: " . $e->getMessage());
    }
}
?>