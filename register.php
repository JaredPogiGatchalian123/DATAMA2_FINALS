<?php
include 'config/db.php';

if (isset($_POST['register'])) {
    $email = $_POST['email'];
    $p = $_POST['password']; 
    $fname = $_POST['owner_fname'];
    $lname = $_POST['owner_lname'];
    $phone = $_POST['phone'];

    try {
        // FIXED: Removed 'Role' from the columns and values list
        $sql = "INSERT INTO Owner (Owner_Fname, Owner_Lname, Email, Password, Phone) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$fname, $lname, $email, $p, $phone])) {
            header("Location: customer_login.php?registered=1");
            exit();
        }
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $error = "Error: This email address is already registered.";
        } else {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="auth-body">
    <div class="auth-card" style="max-width: 500px;">
        <h2><i class="fas fa-paw"></i> Join HappyPaws</h2>
        <p>Register to start booking appointments.</p>

        <?php if(isset($error)): ?>
            <div style="color: #ff7675; background: #fff5f5; padding: 10px; border-radius: 10px; margin-bottom: 20px; font-size: 0.85rem; border: 1px solid #ff7675;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="email@example.com" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a secure password" required>
            </div>

            <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">
            <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 15px; font-weight: 700; text-transform: uppercase;">Owner Information</p>

            <div style="display: flex; gap: 15px;">
                <div class="input-group" style="flex: 1;">
                    <label>First Name</label>
                    <input type="text" name="owner_fname" placeholder="First Name" required>
                </div>
                <div class="input-group" style="flex: 1;">
                    <label>Last Name</label>
                    <input type="text" name="owner_lname" placeholder="Last Name" required>
                </div>
            </div>

            <div class="input-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="09xxxxxxxxx" required>
            </div>

            <button type="submit" name="register" class="auth-btn">Create Account</button>
        </form>
        
        <div class="auth-footer">
            Already have an account? <a href="customer_login.php">Sign In</a>
        </div>
    </div>
</body>
</html>