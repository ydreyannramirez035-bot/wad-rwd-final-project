<?php
session_start();

// Path to point to 'src/db.php' based on the folder structure
if (file_exists(__DIR__ . "/src/db.php")) {
    require_once __DIR__ . "/src/db.php";
}

// Variables to hold state
$loginError = "";
$registerError = "";
$registerSuccess = "";

// Flags to control which modal opens on page load
$openLoginModal = false;
$openRegisterModal = false;

// 1. Handle "Registration Successful" redirect flag
if (isset($_GET["registered"])) {
    $registerSuccess = "Registration successful! Please login.";
    $openLoginModal = true;
}

// 2. Handle Form Submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- LOGIN LOGIC ---
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'login') {
        
        $input = strtolower(trim($_POST["username_or_email"]));
        $password = trim($_POST["password"]);

        // Verify DB connection exists
        if (function_exists('get_db')) {
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

                // Check for admin
                $stmtAdmin = $db->prepare("SELECT 1 FROM users WHERE email = ?");
                $stmtAdmin->bindValue(1, $user['email'], SQLITE3_TEXT);
                $resAdmin = $stmtAdmin->execute();

                // Simple check based on username or logic you provided
                if ($user["username"] == "admin") {
                    header("Location: src/admin_dashboard.php");
                    exit;
                }

                header("Location: src/student_dashboard.php");
                exit;
            } else {
                $loginError = "Invalid username/email or password.";
                $openLoginModal = true;
            }
        } else {
            $loginError = "Database connection not found (db.php missing in src folder).";
            $openLoginModal = true;
        }
    } 
    
    // --- REGISTER LOGIC ---
    elseif (isset($_POST['action_type']) && $_POST['action_type'] === 'register') {
        
        $username = strtolower(trim($_POST["username"]));
        $email = strtolower(trim($_POST["email"]));
        $password = $_POST["password"];
        $confirmPassword = $_POST["confirmPassword"];

        if (function_exists('get_db')) {
            $db = get_db();

            // 1. Basic Validation
            if (!$username || !$email || !$password) {
                $registerError = "All fields are required.";
            } 
            else if ($password !== $confirmPassword) {
                $registerError = "Passwords do not match.";
            }
            else if (strlen($password) < 8) {
                $registerError = "Password must be at least 8 characters long.";
            }
            else if (!preg_match('@[A-Z]@', $password)) {
                $registerError = "Password must include at least one uppercase letter.";
            }
            else if (!preg_match('@[a-z]@', $password)) {
                $registerError = "Password must include at least one lowercase letter.";
            }
            else if (!preg_match('@[0-9]@', $password)) {
                $registerError = "Password must include at least one number.";
            }
            else {
                // 2. Check if Email is ALREADY registered
                $stmt = $db->prepare("SELECT 1 FROM users WHERE email = ?");
                $stmt->bindValue(1, $email, SQLITE3_TEXT);
                
                if ($stmt->execute()->fetchArray()) {
                    $registerError = "Email already registered. Please login.";
                } else { 
                    // 3. Logic: Is this an Admin or a Student?
                    $countStmt = $db->prepare("SELECT COUNT(*) as count FROM users");
                    $countResult = $countStmt->execute();
                    $countRow = $countResult->fetchArray(SQLITE3_ASSOC);
                    $userCount = $countRow['count'];
                    
                    if ($userCount == 0) {
                        // First user is Admin
                        $roleId = $db->querySingle("SELECT id FROM roles WHERE name='admin'");
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        $stmt = $db->prepare("INSERT INTO users (role_id, username, password_hash, email) VALUES (?, ?, ?, ?)");
                        $stmt->bindValue(1, $roleId, SQLITE3_INTEGER);
                        $stmt->bindValue(2, $username, SQLITE3_TEXT);
                        $stmt->bindValue(3, $hashedPassword, SQLITE3_TEXT);
                        $stmt->bindValue(4, $email, SQLITE3_TEXT);
                        $stmt->execute();
                        
                        // Redirect to self with flag
                        header("Location: " . $_SERVER['PHP_SELF'] . "?registered=true");
                        exit;

                    } else {
                        // Subsequent users are Students (must exist in student table)
                        $checkStmt = $db->prepare("SELECT id FROM students WHERE email = ?");
                        $checkStmt->bindValue(1, $email, SQLITE3_TEXT);
                        $studentResult = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);

                        if (!$studentResult) {
                            $registerError = "This email is not found in our student records.";
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

                                header("Location: " . $_SERVER['PHP_SELF'] . "?registered=true");
                                exit;
                            } else {
                                $registerError = "An error occurred while creating the account.";
                            }
                        }
                    }
                }
            }
        } else {
            $registerError = "Database connection not found.";
        }
        
        // If we reached here, there was an error
        $openRegisterModal = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>
        <img src="img/logo.png" width="40" height="40" class="me-2 logo-img" alt="Logo">
        ClassSched
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/index.css">
    
    <style>
        @media (max-width: 375px) {
            .navbar-brand .logo-text { font-size: 1.2rem; }
            .navbar-brand img { width: 30px; height: 30px; margin-right: 0.25rem !important; }
            .btn-login, .btn-brand { padding: 6px 12px; font-size: 0.8rem; }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

    <nav class="navbar fixed-top">
        <div class="container d-flex justify-content-between align-items-center flex-nowrap">
            <a class="navbar-brand d-flex align-items-center m-0 p-0" href="#">
                <img src="img/logo.png" width="40" height="40" class="me-2 logo-img" alt="Logo">
                <span class="logo-text lh-1">
                    <span class="text-class">Class</span><span class="text-sched">Sched</span>
                </span>
            </a>
            
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-login fw-medium text-nowrap" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Login
                </button>
                <button type="button" class="btn btn-brand text-nowrap" data-bs-toggle="modal" data-bs-target="#registerModal">
                    Get Started
                </button>
            </div>
        </div>
    </nav>

    <section class="hero-section flex-grow-1 d-flex align-items-center">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="container text-center">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold text-dark mb-4 lh-sm animate-fade-up delay-1">
                        Simplify Your <br>
                        <span class="text-brand-blue">School Scheduling</span>
                    </h1>
                    <p class="lead text-secondary mb-5 mx-auto animate-fade-up delay-2" style="max-width: 600px;">
                        Say goodbye to messy spreadsheets. Manage classes, teachers, and rooms in one place with a system that helps you avoid schedule conflicts.
                    </p>
                    
                    <div class="d-flex flex-row justify-content-center gap-3 animate-fade-up delay-3 flex-wrap">
                        <button type="button" class="btn btn-hero-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                            Create Schedule
                        </button>
                        <button class="btn btn-hero-secondary d-flex align-items-center gap-2">
                            <i class="fa-solid fa-play" style="font-size: 0.8rem;"></i> Watch Demo
                        </button>
                    </div>

                    <div class="mt-5 d-flex flex-column flex-sm-row justify-content-center align-items-center gap-3 animate-fade-up delay-3">
                        <span class="text-secondary small fw-medium">Trusted by 500+ schools</span>
                        
                        <div class="avatar-group">
                            <div class="avatar-placeholder bg-secondary bg-opacity-50"></div>
                            <div class="avatar-placeholder bg-secondary bg-opacity-25"></div>
                            <div class="avatar-placeholder bg-secondary bg-opacity-10"></div>
                            <div class="plus-badge">+</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-5 pt-4 text-start animate-fade-up delay-3">
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Centralized Schedule Management</h5>
                        <p class="text-secondary mb-0 small">Efficiently add, edit, and remove class schedules and student records in one secure dashboard.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Student & Teacher Portals</h5>
                        <p class="text-secondary mb-0 small">Dedicated dashboards for students to view schedules and admins to manage data.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Real-Time Updates</h5>
                        <p class="text-secondary mb-0 small">Instant notifications for schedule changes, profile updates, and announcements.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <footer class="bg-white border-top py-4 text-center">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg p-3">
                <div class="modal-header border-0 pb-0">
                    <h4 class="fw-bold">Welcome Back!</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-4">Please login to access your dashboard.</p>

                    <?php if (!empty($registerSuccess)): ?>
                        <div class="alert alert-success small py-2"><?php echo htmlspecialchars($registerSuccess); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger small py-2"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="action_type" value="login">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Username or Email</label>
                            <input type="text" name="username_or_email" class="form-control" placeholder="Enter your credentials" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-medium text-dark">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button type="submit" class="btn btn-brand w-100">Sign In</button>
                    </form>
                    
                    <div class="mt-3 text-center small text-muted">
                        New here? <a href="#" class="text-brand-blue text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal">Create an account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg p-3">
                <div class="modal-header border-0 pb-0">
                    <h4 class="fw-bold">Create Account</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <p class="text-muted small mb-4">Register to manage or view schedules.</p>

                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger small py-2"><?php echo htmlspecialchars($registerError); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <input type="hidden" name="action_type" value="register">

                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Choose a username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="you@school.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-medium text-dark">Password</label>
                                <input type="password" name="password" class="form-control" placeholder="8+ chars" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label small fw-medium text-dark">Confirm</label>
                                <input type="password" name="confirmPassword" class="form-control" placeholder="Confirm" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-brand w-100">Sign Up</button>
                    </form>
                    
                    <div class="mt-3 text-center small text-muted">
                        Already have an account? <a href="#" class="text-brand-blue text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($openLoginModal): ?>
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            <?php endif; ?>

            <?php if ($openRegisterModal): ?>
                var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            <?php endif; ?>
        });
    </script>
</body>
</html>