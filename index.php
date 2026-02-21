<?php
include 'config/db.php';
session_start();

if (isset($_POST['login'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE Username = ? AND Password = ?");
    $stmt->execute([$u, $p]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['User_ID'];
        $_SESSION['username'] = $user['Username'];
        $_SESSION['role'] = $user['Role'];

        if ($m_manager) {
            $bulk = new MongoDB\Driver\BulkWrite;
            $bulk->insert(['action' => 'LOGIN', 'user' => $u, 'time' => date('Y-m-d H:i:s')]);
            $m_manager->executeBulkWrite('happypawsvet.logs', $bulk);
        }
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Incorrect username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <h2>🐾 HappyPaws</h2>
        <p>Clinic Management System</p>
        
        <?php if(isset($error)): ?>
            <div style="color: #ef4444; background: #fee2e2; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="e.g. staff1" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" class="auth-btn">Sign In</button>
        </form>
        
        <div class="auth-footer">
            New here? <a href="register.php">Create Account</a>
            <br><br>
            <strong>Hybrid Database Active</strong><br>
            MySQL & MongoDB (27017)
        </div>
    </div>
</body>
</html>