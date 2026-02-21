<?php
require 'vendor/autoload.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

try {
    // 1. Connect to MongoDB
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->system_logs;

    // 2. Fetch all inventory-related events (Add, Restock, Reduce, Delete)
    $logs = $collection->find(
        ['event' => ['$regex' => 'STOCK|INVENTORY|ITEM']], 
        ['sort' => ['timestamp' => -1]]
    );
} catch (Exception $e) {
    die("MongoDB Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inventory Audit Logs | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Matching the UI from image_6dcd21.png */
        .main-wrapper { margin-left: 85px; padding: 40px; background-color: #f8f9fa; min-height: 100vh; }
        .logs-title { display: flex; align-items: center; gap: 15px; margin-bottom: 5px; color: #2d3436; }
        .logs-title i { color: #2bcbba; font-size: 1.8rem; }
        .source-text { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 30px; }

        .log-table-container { 
            background: white; 
            border-radius: 18px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.05); 
        }

        .log-table { width: 100%; border-collapse: collapse; }
        
        /* Matching Teal Header from image_6dcd21.png */
        .log-table thead th { 
            background: #2bcbba; 
            color: white; 
            text-align: left; 
            padding: 18px 20px; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
        }

        .log-table tbody td { 
            padding: 18px 20px; 
            border-bottom: 1px solid #f1f3f5; 
            font-size: 0.9rem; 
            color: #2d3436; 
        }

        /* Custom Status/Action Pills */
        .action-pill { 
            display: inline-block; 
            padding: 6px 14px; 
            border-radius: 50px; 
            font-size: 0.7rem; 
            font-weight: 800; 
            text-transform: uppercase;
        }
        .act-add { background: #e3fafc; color: #0c8599; }
        .act-restock { background: #fff9db; color: #f08c00; }
        .act-reduce { background: #f3f0ff; color: #6741d9; }
        .act-delete { background: #fff5f5; color: #c92a2a; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div class="logs-title">
        <i class="fas fa-history"></i>
        <h2>Inventory Audit Logs</h2>
    </div>
    <p class="source-text">Source: <strong>MongoDB system_logs</strong> (Hybrid Architecture)</p>

    <div class="log-table-container">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Action Type</th>
                    <th>Item Details</th>
                    <th>Quantity Change</th>
                    <th>Staff Performed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $entry): ?>
                <tr>
                    <td style="font-weight: 500; color: #636e72;">
                        <?php 
                            $datetime = $entry['timestamp']->toDateTime();
                            echo $datetime->format('M d, Y | h:i A'); 
                        ?>
                    </td>
                    
                    <td>
                        <?php 
                            $event = $entry['event'];
                            $class = 'act-add';
                            if(strpos($event, 'RESTOCK') !== false) $class = 'act-restock';
                            if(strpos($event, 'DEDUCTION') !== false) $class = 'act-reduce';
                            if(strpos($event, 'DELETE') !== false) $class = 'act-delete';
                        ?>
                        <span class="action-pill <?php echo $class; ?>">
                            <?php echo str_replace('_', ' ', $event); ?>
                        </span>
                    </td>

                    <td style="font-weight: 700;">
                        <?php echo $entry['item_name'] ?? ($entry['item_details']['name'] ?? 'Removed Item'); ?>
                    </td>

                    <td style="font-weight: 700;">
                        <?php if(isset($entry['quantity_added'])): ?>
                            <span style="color: #20c997;">+<?php echo $entry['quantity_added']; ?></span>
                        <?php elseif(isset($entry['quantity_deducted'])): ?>
                            <span style="color: #ff7675;">-<?php echo $entry['quantity_deducted']; ?></span>
                        <?php else: ?>
                            <span style="color: #adb5bd;">N/A</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-user-circle" style="color: #dee2e6;"></i>
                            <?php echo $entry['staff_member'] ?? ($entry['staff_name'] ?? 'Jared'); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>