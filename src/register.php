<?php
require_once __DIR__ . "/db.php";

$error = "";

// Initialize database connection
$db = get_db();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = strtolower(trim($_POST["username"]));
    $email = strtolower(trim($_POST["email"]));
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];
    // 1. Basic Validation
    if (!$username || !$email || !$password) {
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
    else {
        
        // 2. Check if Email is ALREADY registered
        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
        $stmt->bindValue(1, $email, SQLITE3_TEXT);
        if ($stmt->execute()->fetchArray()) {
            $error = "Email already registered. Please login.";
        } else { 
            
            // 3. Logic: Is this an Admin or a Student?
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM users");
            $countResult = $countStmt->execute();
            $countRow = $countResult->fetchArray(SQLITE3_ASSOC);
            $userCount = $countRow['count'];
            
            if ($userCount == 0) {
                $roleId = $db->querySingle("SELECT id FROM roles WHERE name='admin'");
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (role_id, username, password_hash, email) VALUES (?, ?, ?, ?)");
                $stmt->bindValue(1, $roleId, SQLITE3_INTEGER);
                $stmt->bindValue(2, $username, SQLITE3_TEXT);
                $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
                $stmt->bindValue(4, $email, SQLITE3_TEXT);
                $stmt->execute();
                
                header("Location: login.php?registered=true");
                exit;

            } else {
                $checkStmt = $db->prepare("SELECT id FROM students WHERE email = ?");
                $checkStmt->bindValue(1, $email, SQLITE3_TEXT);
                $studentResult = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$studentResult) {
                    $error = "This email is not found in our student records.";
                } else {
                    $roleId = $db->querySingle("SELECT id FROM roles WHERE name='student'");
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (role_id, username, password_hash, email) VALUES (?, ?, ?, ?)");
                    $stmt->bindValue(1, $roleId, SQLITE3_INTEGER);
                    $stmt->bindValue(2, $username, SQLITE3_TEXT); 
                    $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
                    $stmt->bindValue(4, $email, SQLITE3_TEXT);
                    
                    if ($stmt->execute()) {
                        $newUserId = $db->lastInsertRowID();                       
                        $updateStmt = $db->prepare("UPDATE students SET user_id = ? WHERE email = ?");
                        $updateStmt->bindValue(1, $newUserId, SQLITE3_INTEGER);
                        $updateStmt->bindValue(2, $email, SQLITE3_TEXT);
                        $updateStmt->execute();

                        header("Location: login.php?registered=true");
                        exit;
                    } else {
                        $error = "An error occurred while creating the account.";
                    }
                }
            }
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
        <h2>Register</h2>
        
        <?php if (!empty($error)): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="text" name="username" placeholder="Username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            <input type="email" name="email" placeholder="Email Address" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            <input type="password" name="password" placeholder="Password" required>
            <input type="password" name="confirmPassword" placeholder="Confirm Password" required>
            <button class="btn" type="submit">Register</button>
        </form>
        
        <p>Already have an account? <a href="login.php">Login</a></p>
    </div>
</body>
</html>