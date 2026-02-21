<?php
include 'config/db.php';
session_start();

$error = null;

// Only process when the "Authorize Entry" button is clicked
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? ''; 
    $password = $_POST['password'] ?? '';

    try {
        // 1. Query the dedicated Staff table
        // We use Email and Password as the primary credentials
        $stmt = $pdo->prepare("SELECT * FROM Staff WHERE Email = ? AND Password = ?");
        $stmt->execute([$email, $password]);
        $staff = $stmt->fetch();

        if ($staff) {
            // 2. Set staff-specific session variables
            $_SESSION['staff_id']   = $staff['Staff_ID'];
            $_SESSION['staff_name'] = $staff['First_Name'];
            $_SESSION['role']       = 'staff';
            
            // 3. Log staff entry to MongoDB (Meets your Hybrid DB requirement)
            if (isset($m_manager)) {
                $bulk = new MongoDB\Driver\BulkWrite;
                $bulk->insert([
                    'action' => 'STAFF_PORTAL_LOGIN', 
                    'user_email' => $email, 
                    'staff_id' => $staff['Staff_ID'],
                    'time' => date('Y-m-d H:i:s')
                ]);
                $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);
            }
            
            header("Location: staff_portal.php");
            exit();
        } else {
            $error = "Access Denied: Invalid staff credentials.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Portal | Login</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body" style="background: #1a1c23;">
    <div class="auth-card" style="border-top: 4px solid #4e73df;">
        <h2><i class="fas fa-user-shield"></i> Staff Portal</h2>
        <p>Enter credentials to access clinic management.</p>
        
        <?php if($error): ?>
            <div style="color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; border: 1px solid #fecaca;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="staff_login.php">
            <div class="input-group">
                <label>Staff Email</label>
                <input type="email" name="email" placeholder="staff@happypaws.com" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" class="auth-btn" style="background: #4e73df;">Authorize Entry</button>
        </form>
        
        <div style="margin-top: 20px; text-align: center; font-size: 0.8rem;">
            <a href="customer_login.php" style="color: #4e73df; text-decoration: none;">&larr; Return to Owner Portal</a>
        </div>
    </div>
</body>
</html>