<?php
include 'config/db.php';
session_start();

$error = null;

// Removed isset($_POST['login']) as it's cleaner to check method only
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? ''; 
    $password = $_POST['password'] ?? '';

    try {
        // FIXED: Using lowercase 'owner' to match your V2 schema
        $stmt = $pdo->prepare("SELECT * FROM owner WHERE Email = ? AND Password = ?");
        $stmt->execute([$email, $password]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['owner_id'] = $user['Owner_ID']; 
            $_SESSION['owner_name'] = $user['Owner_Fname']; 
            $_SESSION['role'] = 'customer'; 
            
            header("Location: customer_site.php");
            exit();
        } else {
            $error = "Invalid email or password. Please try again.";
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Customer Login | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body" style="background: linear-gradient(135deg, #f5f6fa 0%, #e0e4f1 100%);">
    <div class="auth-card">
        <h2>🐾 Welcome Home</h2>
        <p>Sign in to book a visit for your pet.</p>
        
        <?php if($error): ?>
            <div style="color: #ef4444; background: #fee2e2; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid #fecaca;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" class="auth-btn">Sign In</button>
        </form>
        <div class="auth-footer">
            Don't have an account? <a href="register.php">Create one here</a>
        </div>
    </div>
</body>
</html>