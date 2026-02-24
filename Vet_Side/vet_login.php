<?php
include 'config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM vet WHERE Vet_Email = ? AND Password = ?");
    $stmt->execute([$email, $password]);
    $vet = $stmt->fetch();

    if ($vet) {
        $_SESSION['vet_id'] = $vet['Vet_ID'];
        $_SESSION['vet_name'] = $vet['Vet_Fname'] . " " . $vet['Vet_Lname'];
        $_SESSION['role'] = 'vet';
        header("Location: vet_dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vet Login | HappyPaws</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background: #f0f2f5; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
        .login-card { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 350px; }
        .login-card h2 { color: #2bcbba; text-align: center; margin-bottom: 30px; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 10px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #2bcbba; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Vet Portal</h2>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">LOGIN</button>
        </form>
    </div>
</body>
</html>