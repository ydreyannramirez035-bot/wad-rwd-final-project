<?php
session_start();
require_once __DIR__ . "/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = strtolower(trim($_POST["username_or_email"]));
    $password = trim($_POST["password"]);

    $db = get_db();
    
    $sql = "SELECT * FROM users WHERE email = :input OR username = :input";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':input', $input, SQLITE3_TEXT);
    
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user"] = [
            "id" => $user["id"],
            "username" => $user["username"],
            "email" => $user["email"],
            "role_id" => $user["role_id"]
        ];

        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();

        if ($user["username"] == "admin" || $user["email"] == $result->fetchArray()) {
            header("Location: admin_dashboard.php");
            exit;
        }

        header("Location: student_dashboard.php");
        exit;
    } else {
        $error = "Invalid username/email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="../styles/style.css">
</head>
<body>
    <div class="container">
        <h2>Already have an account? Please login</h2>
        
        <?php if (isset($_GET["registered"])): ?>
            <p style='color:green;'>Registration successful! Please login.</p>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <p style='color:red;'><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username_or_email" placeholder="Username or Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button class="btn" type="submit">Login</button>
        </form>
        
        <p>Don’t have an account? <a href="register.php">Register</a></p>
    </div>
</body>
</html>