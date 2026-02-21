<?php
require 'vendor/autoload.php';
try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->appointment_logs;

    $test_data = [
        [
            'mysql_owner_id' => 101,
            'status' => 'Completed',
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'details' => ['pet_name' => 'Buddy', 'service' => 'Vaccination', 'vet_name' => 'Smith']
        ],
        [
            'mysql_owner_id' => 105,
            'status' => 'Completed',
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'details' => ['pet_name' => 'Luna', 'service' => 'General Checkup', 'vet_name' => 'Gatchalian']
        ],
        [
            'mysql_owner_id' => 109,
            'status' => 'Completed',
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'details' => ['pet_name' => 'Max', 'service' => 'Grooming', 'vet_name' => 'Santos']
        ]
    ];

    $collection->insertMany($test_data);
    echo "Successfully added 3 logs! <a href='appointment_logs.php'>Check your table now.</a>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>