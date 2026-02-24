<?php
require 'config/db.php'; 
require 'vendor/autoload.php'; 
session_start();

// Security: Ensure only staff can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: staff_login.php");
    exit();
}

$searchTerm = $_GET['search'] ?? '';
$methodFilter = $_GET['method'] ?? '';

try {
    $client = new MongoDB\Client("mongodb://localhost:27017");
    $collection = $client->happypawsvet->payment_logs;

    $query = [];
    if (!empty($searchTerm)) {
        $query['$or'] = [
            ['owner_name' => new MongoDB\BSON\Regex($searchTerm, 'i')],
            ['details.service' => new MongoDB\BSON\Regex($searchTerm, 'i')],
            ['service' => new MongoDB\BSON\Regex($searchTerm, 'i')],
            ['method' => new MongoDB\BSON\Regex($searchTerm, 'i')]
        ];
    }
    if (!empty($methodFilter)) {
        $query['method'] = $methodFilter;
    }

    // Always sort by newest timestamp first so the latest transaction is at the top
    $payments = $collection->find($query, ['sort' => ['timestamp' => -1]]);
} catch (Exception $e) {
    die("MongoDB Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Financial Payment Logs | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; margin: 0; }
        .main-wrapper { margin-left: 90px; padding: 40px; }
        .log-table { width: 100%; border-collapse: separate; border-spacing: 0; background: #fff; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .log-table th { background: #2bcbba; color: white; padding: 18px 20px; text-align: left; font-size: 0.85rem; text-transform: uppercase; }
        .log-table td { padding: 18px 20px; border-bottom: 1px solid #f1f3f5; font-size: 0.9rem; color: #2d3436; }
        
        .amount-highlight { color: #27ae60; font-weight: 800; font-size: 1rem; }
        .method-badge { padding: 6px 12px; border-radius: 8px; background: #f1f3f5; color: #4b4b4b; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; border: 1px solid #e0e0e0; }
        
        .filter-header { display: flex; gap: 15px; margin-bottom: 30px; background: #fff; padding: 25px; border-radius: 15px; align-items: center; box-shadow: 0 4px 10px rgba(0,0,0,0.02); }
        .search-box { flex: 1; position: relative; }
        .search-input { width: 100%; padding: 12px 15px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; transition: 0.3s; }
        
        .filter-select { width: 200px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff; outline: none; font-weight: 600; color: #2d3436; }
        .btn-search { background: #2bcbba; color: white; border: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-reset { color: #adb5bd; text-decoration: none; font-size: 0.85rem; font-weight: 600; margin-left: 10px; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="margin-bottom: 35px; display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-file-invoice-dollar" style="font-size: 2rem; color: #2bcbba;"></i>
        <div>
            <h2 style="margin:0; font-weight: 900;">Financial Payment Logs</h2>
            <p style="color: #7f8c8d; margin-top: 5px;">Real-time tracking of clinical and inventory revenue.</p>
        </div>
    </div>

    <form method="GET" class="filter-header">
        <div class="search-box">
            <input type="text" name="search" class="search-input" placeholder="Search Owner, Service, or Invoice..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <select name="method" class="filter-select">
            <option value="">All Methods</option>
            <option value="Cash" <?php echo ($methodFilter == 'Cash') ? 'selected' : ''; ?>>Cash</option>
            <option value="E-Wallet" <?php echo ($methodFilter == 'E-Wallet') ? 'selected' : ''; ?>>E-Wallet</option>
            <option value="Card" <?php echo ($methodFilter == 'Card') ? 'selected' : ''; ?>>Card</option>
        </select>
        <button type="submit" class="btn-search">FILTER</button>
        <?php if(!empty($searchTerm) || !empty($methodFilter)): ?>
            <a href="payment_logs.php" class="btn-reset">Clear Filters</a>
        <?php endif; ?>
    </form>

    <table class="log-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Owner Name</th>
                <th>Service / Description</th>
                <th>Method</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hasPayments = false;
            foreach ($payments as $pay): 
                $hasPayments = true;
                
                // Date formatting
                $formattedDate = "---";
                if(isset($pay['timestamp'])) {
                    $dt = $pay['timestamp']->toDateTime();
                    $dt->setTimezone(new DateTimeZone('Asia/Manila'));
                    $formattedDate = $dt->format('F d, Y | h:i A');
                }
                
                // Get sub-details array
                $details = (array)($pay['details'] ?? []);
                
                // DATA MAPPING FIX: Support both field variations
                $serviceInfo = $details['service'] ?? $pay['service'] ?? 'Medical Service';
                $payMethod = $pay['method'] ?? $pay['payment_method'] ?? 'CASH';
                $amount = $pay['amount_paid'] ?? $pay['total_paid'] ?? 0;
            ?>
            <tr>
                <td><strong><?php echo $formattedDate; ?></strong></td>
                <td><strong style="color: #2bcbba;"><?php echo htmlspecialchars($pay['owner_name'] ?? 'Walk-in Customer'); ?></strong></td>
                <td><?php echo htmlspecialchars($serviceInfo); ?></td>
                <td><span class="method-badge"><?php echo htmlspecialchars($payMethod); ?></span></td>
                <td class="amount-highlight">₱<?php echo number_format($amount, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if (!$hasPayments): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 80px 0; color: #adb5bd;">
                        <i class="fas fa-search fa-4x" style="display:block; margin-bottom:20px; opacity:0.1; color: #2d3436;"></i>
                        <p style="font-weight: 600; font-size: 1.1rem;">No recent payment logs found.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>