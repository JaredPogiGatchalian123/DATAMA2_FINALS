<?php
require 'config/db.php'; 
require 'vendor/autoload.php'; 
session_start();

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
            ['invoice_no' => new MongoDB\BSON\Regex($searchTerm, 'i')],
            ['owner_name' => new MongoDB\BSON\Regex($searchTerm, 'i')]
        ];
    }
    if (!empty($methodFilter)) {
        $query['method'] = $methodFilter;
    }

    $payments = $collection->find($query, ['sort' => ['timestamp' => -1]]);
} catch (Exception $e) {
    die("MongoDB Connection Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Logs | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-wrapper { margin-left: 85px; padding: 40px; transition: 0.3s ease; }
        .log-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .log-table th { background: #2bcbba; color: white; padding: 15px; text-align: left; }
        .log-table td { padding: 15px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        .amount-highlight { color: #27ae60; font-weight: bold; font-family: 'Courier New', monospace; font-size: 1rem; }
        .method-badge { padding: 4px 10px; border-radius: 15px; background: #f1f2f6; color: #2f3542; font-size: 0.7rem; font-weight: bold; text-transform: uppercase; border: 1px solid #dfe4ea; }
        
        /* FIXED CENTERING AND WIDTH LAYOUT */
        .filter-header { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 25px; 
            background: #fff; 
            padding: 20px; 
            border-radius: 12px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); 
            align-items: center;
            justify-content: center; /* Centers the whole bar */
        }

        .search-box { 
            width: 50%; /* Shortened length as requested */
            position: relative; 
        }

        .search-input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #eee; 
            border-radius: 8px; 
            font-size: 0.9rem; 
            outline: none; 
            text-align: center; /* Puts text in the middle as requested */
        }

        .filter-select { 
            width: 20%; 
            padding: 12px; 
            border: 1px solid #eee; 
            border-radius: 8px; 
            background: #fff; 
            font-size: 0.9rem; 
            outline: none;
        }

        .btn-search { 
            background: #2bcbba; 
            color: white; 
            border: none; 
            padding: 12px 25px; 
            border-radius: 8px; 
            font-weight: 700; 
            cursor: pointer; 
            transition: 0.2s; 
            white-space: nowrap;
        }

        .btn-search:hover { background: #20bf6b; }
        .btn-reset { color: #7f8c8d; text-decoration: none; font-size: 0.85rem; font-weight: 600; white-space: nowrap; }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-wrapper">
    <div style="margin-bottom: 30px;">
        <h2><i class="fas fa-file-invoice-dollar" style="color: #2bcbba;"></i> Financial Payment Logs</h2>
    </div>

    <form method="GET" class="filter-header">
        <div class="search-box">
            <input type="text" name="search" class="search-input" placeholder="Search Invoice ID or Owner Name" value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        
        <select name="method" class="filter-select">
            <option value="">All Methods</option>
            <option value="Cash" <?php echo ($methodFilter == 'Cash') ? 'selected' : ''; ?>>Cash</option>
            <option value="E-Wallet" <?php echo ($methodFilter == 'E-Wallet') ? 'selected' : ''; ?>>E-Wallet</option>
            <option value="Card" <?php echo ($methodFilter == 'Card') ? 'selected' : ''; ?>>Card</option>
        </select>

        <button type="submit" class="btn-search">FILTER</button>
        <?php if(!empty($searchTerm) || !empty($methodFilter)): ?>
            <a href="payment_logs.php" class="btn-reset">Clear</a>
        <?php endif; ?>
    </form>

    <table class="log-table">
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Invoice #</th>
                <th>Owner Name</th>
                <th>Service</th>
                <th>Method</th>
                <th>Amount Paid</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $hasPayments = false;
            foreach ($payments as $pay): 
                $hasPayments = true;
            ?>
            <tr>
                <td>
                    <?php 
                        if(isset($pay['timestamp']) && $pay['timestamp'] instanceof MongoDB\BSON\UTCDateTime) {
                            echo $pay['timestamp']->toDateTime()->format('M d, Y | h:i A'); 
                        } else { echo "N/A"; }
                    ?>
                </td>
                <td><strong style="color: #2bcbba;">#<?php echo htmlspecialchars($pay['invoice_no'] ?? 'N/A'); ?></strong></td>
                <td><?php echo htmlspecialchars($pay['owner_name'] ?? 'Walk-in'); ?></td>
                <td><?php echo htmlspecialchars($pay['service'] ?? 'Medical Service'); ?></td>
                <td><span class="method-badge"><?php echo htmlspecialchars($pay['method'] ?? 'Cash'); ?></span></td>
                <td class="amount-highlight">₱<?php echo number_format($pay['amount_paid'] ?? 0, 2); ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if (!$hasPayments): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 50px; color: #adb5bd;">
                        <i class="fas fa-search fa-3x" style="display:block; margin-bottom:15px; opacity:0.3; color: #2bcbba;"></i>
                        No matching transactions found.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>