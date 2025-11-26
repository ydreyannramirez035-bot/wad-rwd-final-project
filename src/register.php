<?php
require_once __DIR__ ."/db.php";
// Initialize database connection
$db = get_db();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];

    if (!$name || !$email || !$password) {
        $error = "All fields are required.";
    } 
    else if ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    }
    else if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    }
    else if (!preg_match('@[A-Z]@', $password)) {
        $error = "Password must include at least one uppercase letter.";
    }
    else if (!preg_match('@[a-z]@', $password)) {
        $error = "Password must include at least one lowercase letter.";
    }
    else if (!preg_match('@[0-9]@', $password)) {
        $error = "Password must include at least one number.";
    }
    else if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $error = "Password must include at least one special character.";
    }
    else {
        // Check if email already exists
        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            $error = "Email already registered.";
        } else { 
            // Determine role: first user = admin
            $roleAdmin = $db->querySingle("SELECT id FROM roles WHERE name='admin'");
            $roleStudent = $db->querySingle("SELECT id FROM roles WHERE name='student'");

            $userCount = $db->querySingle("SELECT COUNT(*) FROM users");
            $roleId = ($userCount == 0) ? $roleAdmin : $roleStudent;

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (role_id, name, password_hash, email) VALUES (?, ?, ?, ?)");
            $stmt->bindValue(1, $roleId, SQLITE3_INTEGER);
            $stmt->bindValue(2, $name, SQLITE3_TEXT);
            $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
            $stmt->bindValue(4, $email, SQLITE3_TEXT);
            $stmt->execute();
            header("Location: login.php?registered=true");
            exit;
            }
        }
    }

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <div class="container">
        <h2>Create an Account</h2>
        <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST" action="">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirmPassword" placeholder="Confirm Password" required>
            <button class="btn" type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>

</html>