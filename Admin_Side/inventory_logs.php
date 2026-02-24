<?php
require 'vendor/autoload.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->inventory_logs;

    // Updated regex to include PRICE updates
    $logs = $collection->find(
        ['event' => ['$regex' => 'STOCK|INVENTORY|ITEM|DEDUCTION|SALE|PRICE']], 
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
        .main-wrapper { margin-left: 90px; padding: 40px; background-color: #f8f9fa; min-height: 100vh; }
        .logs-title { display: flex; align-items: center; gap: 15px; margin-bottom: 5px; color: #2d3436; }
        .logs-title i { color: #2bcbba; font-size: 1.8rem; }
        .source-text { color: #7f8c8d; font-size: 0.9rem; margin-bottom: 30px; }
        .log-table-container { background: white; border-radius: 18px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .log-table { width: 100%; border-collapse: collapse; }
        .log-table thead th { background: #2bcbba; color: white; text-align: left; padding: 18px 20px; font-size: 0.75rem; text-transform: uppercase; }
        .log-table tbody td { padding: 18px 20px; border-bottom: 1px solid #f1f3f5; font-size: 0.85rem; color: #2d3436; }
        .action-pill { display: inline-block; padding: 6px 14px; border-radius: 50px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
        .act-add { background: #e3fafc; color: #0c8599; }
        .act-restock { background: #fff9db; color: #f08c00; }
        .act-sale { background: #fff0f6; color: #d6336c; }
        .act-price { background: #e7f5ff; color: #1971c2; } /* Blue badge for price changes */
        .act-reduce { background: #f3f0ff; color: #6741d9; }
        .act-delete { background: #fff5f5; color: #c92a2a; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div class="logs-title"><i class="fas fa-history"></i><h2>Inventory Audit Logs</h2></div>
    <p class="source-text">Hybrid Audit Trail: Tracks stock movement and financial value updates.</p>

    <div class="log-table-container">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Action Type</th>
                    <th>Item Details</th>
                    <th>Update Change</th>
                    <th>Staff</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $entry): ?>
                <tr>
                    <td>
                        <?php 
                            $datetime = $entry['timestamp']->toDateTime();
                            $datetime->setTimezone(new DateTimeZone('Asia/Manila'));
                            echo $datetime->format('M d, Y | h:i A'); 
                        ?>
                    </td>
                    <td>
                        <?php 
                            $event = $entry['event'];
                            $class = 'act-add';
                            if(strpos($event, 'RESTOCK') !== false) $class = 'act-restock';
                            if(strpos($event, 'SALE') !== false) $class = 'act-sale';
                            if(strpos($event, 'PRICE') !== false) $class = 'act-price';
                            if(strpos($event, 'DEDUCTION') !== false) $class = 'act-reduce';
                            if(strpos($event, 'DELETE') !== false) $class = 'act-delete';
                        ?>
                        <span class="action-pill <?php echo $class; ?>">
                            <?php echo str_replace('_', ' ', $event); ?>
                        </span>
                    </td>
                    <td style="font-weight: 700;">
                        <?php echo $entry['item_name'] ?? 'Unknown Item'; ?>
                    </td>
                    <td style="font-weight: 700;">
                        <?php 
                        if ($event === 'PRICE_UPDATE') {
                            echo "<span style='color:#1971c2'>₱".number_format((float)$entry['old_price'], 2)." → ₱".number_format((float)$entry['new_price'], 2)."</span>";
                        } else {
                            $val = $entry['quantity_change'] ?? ($entry['quantity_added'] ?? (-$entry['quantity_deducted'] ?? null));
                            if($val !== null) {
                                $color = ($val < 0) ? '#ff7675' : '#20c997';
                                $prefix = ($val > 0) ? '+' : '';
                                echo "<span style='color:$color'>$prefix$val</span>";
                            } else { echo "---"; }
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($entry['staff_member'] ?? 'Charles'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>