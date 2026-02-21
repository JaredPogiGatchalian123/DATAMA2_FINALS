<?php
require 'vendor/autoload.php';
try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $db = $client->happypaws;
    $collection = $db->appointment_logs;
    $count = $collection->countDocuments();
    echo "Success! Found $count logs in the database.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>