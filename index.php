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
            $loginError = "Database connection not found.";
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
                        
                        header("Location: " . $_SERVER['PHP_SELF'] . "?registered=true");
                        exit;

                    } else {
                        // Subsequent users are Students
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
        $openRegisterModal = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>ClassSched</title>

    <link rel="icon" href="img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/index.css">
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

    <section class="hero-section flex-grow-1 d-flex align-items-center text-center">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>

        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold text-dark mb-4 lh-sm animate-fade-up delay-1">
                        Simplify Your <br>
                        <span class="text-brand-blue">School Scheduling</span>
                    </h1>
                    <p class="lead text-secondary mb-5 mx-auto animate-fade-up delay-2" style="max-width: 600px;">
                        Say goodbye to lost paper schedules. Digitally organize classes, teachers, and rooms in one secure platform accessible by admins and students.
                    </p>
                    
                    <div class="d-flex flex-row justify-content-center gap-3 animate-fade-up delay-3 flex-wrap">
                        <button type="button" class="btn btn-hero-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                            Create Schedule
                        </button>
                        <a href="#how-it-works" class="btn btn-hero-secondary d-flex align-items-center gap-2 text-decoration-none">
                            <i class="fa-solid fa-arrow-down" style="font-size: 0.8rem;"></i> Learn More
                        </a>
                    </div>

                    <div class="mt-5 d-flex flex-column flex-sm-row justify-content-center align-items-center gap-3 animate-fade-up delay-3">
                        <span class="text-secondary small fw-medium">Trusted by 500+ schools</span>
                        <div class="d-flex ms-2">
                            <div class="rounded-circle bg-secondary border border-2 border-white" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-secondary border border-2 border-white opacity-75" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-secondary border border-2 border-white opacity-50" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-brand-blue text-white d-flex align-items-center justify-content-center border border-2 border-white fw-bold" style="width:32px; height:32px; font-size: 10px;">+</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-5 pt-4 text-start animate-fade-up delay-3">
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Digital Organization</h5>
                        <p class="text-secondary mb-0 small">Keep all your class schedules and student records organized in one secure database.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="icon-box bg-info bg-opacity-10 text-info">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Student & Admin Portals</h5>
                        <p class="text-secondary mb-0 small">Dedicated dashboards for students to view their specific schedules and admins to manage data.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-card h-100">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning">
                            <i class="fa-solid fa-filter"></i>
                        </div>
                        <h5 class="fw-bold mb-2">Easy Filtering</h5>
                        <p class="text-secondary mb-0 small">Quickly find schedules by filtering for specific courses (BSIS/ACT) or sorting by time.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="py-5 bg-light border-top">
        <div class="container py-5">
            <div class="text-center mb-5">
                <span class="badge bg-brand-blue bg-opacity-10 rounded-pill px-3 py-2 mb-3 fw-medium text-color">How It Works</span>
                <h2 class="display-5 fw-bold text-dark">Streamlined Management</h2>
                <p class="text-secondary">Three simple steps to digitize your school's scheduling.</p>
            </div>

            <div class="row g-5 align-items-center mb-5">
                <div class="col-lg-6 order-lg-2">
                    <div class="bg-white rounded-4 text-center shadow-sm border animate-fade-up delay-1">
                        <img class="img-fluid" src="img/admin.png" width="600" height="400"alt="admin">
                    </div>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="step-number">1</div>
                    <h3 class="fw-bold mb-3">Admin Setup</h3>
                    <p class="text-secondary mb-4">
                        Administrators have full control. Manually register students, set up their profiles, and assign them to courses like BSIS or ACT.
                    </p>
                    <ul class="list-unstyled text-secondary">
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Register student accounts</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Update student contact info</li>
                        <li><i class="fa-solid fa-check text-brand-blue me-2"></i> Assign year levels and courses</li>
                    </ul>
                </div>
            </div>

            <div class="row g-5 align-items-center mb-5">
                <div class="col-lg-6">
                    <div class="bg-white rounded-4 text-center shadow-sm border animate-fade-up delay-1">
                        <img class="img-fluid" src="img/schedule.png" width="600" height="400"alt="admin">
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="step-number">2</div>
                    <h3 class="fw-bold mb-3">Input the Schedule</h3>
                    <p class="text-secondary mb-4">
                        Admins can easily input class details including subjects, teachers, rooms, and time slots into the system's digital ledger.
                    </p>
                    <ul class="list-unstyled text-secondary">
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Add classes for specific days</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Assign rooms and teachers</li>
                        <li><i class="fa-solid fa-check text-brand-blue me-2"></i> Sort view by time or day</li>
                    </ul>
                </div>
            </div>

            <div class="row g-5 align-items-center">
                <div class="col-lg-6 order-lg-2">
                    <div class="bg-white rounded-4 text-center shadow-sm border animate-fade-up delay-1">
                        <img class="img-fluid" src="img/student.png" width="600" height="400"alt="admin">
                    </div>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="step-number">3</div>
                    <h3 class="fw-bold mb-3">Student Access</h3>
                    <p class="text-secondary mb-4">
                        Students can log in to their own dedicated portal to view their specific class schedules and check teacher details anytime.
                    </p>
                    <ul class="list-unstyled text-secondary">
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Mobile-friendly view</li>
                        <li class="mb-2"><i class="fa-solid fa-check text-brand-blue me-2"></i> Personalized dashboard</li>
                        <li><i class="fa-solid fa-check text-brand-blue me-2"></i> View daily class counts</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="py-5 bg-brand-blue text-white text-center">
        <div class="container">
            <h2 class="fw-bold mb-3">Ready to get started?</h2>
            <p class="mb-4 opacity-75">Join schools streamlining their operations today.</p>
            <button class="btn btn-light rounded-pill px-5 fw-bold text-brand-blue" data-bs-toggle="modal" data-bs-target="#registerModal">Create Account Now</button>
        </div>
    </section>

    <footer class="bg-white border-top py-4 text-center">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg p-3" style="border-radius: 1rem; border: none;">
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
                            <input type="text" name="username_or_email" class="form-control" placeholder="Enter your credentials" required style="border-radius: 0.5rem; padding: 0.75rem;">
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-medium text-dark">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required style="border-radius: 0.5rem; padding: 0.75rem;">
                        </div>
                        <button type="submit" class="btn w-100 fw-bold text-white" style="background-color: #3b66d1; border-radius: 50px; padding: 10px;">Sign In</button>
                    </form>
                    <div class="mt-3 text-center small text-muted">
                        New here? <a href="#" class="text-primary text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal">Create an account</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg p-3" style="border-radius: 1rem; border: none;">
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
                            <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="you@school.edu" required>
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
                        <button type="submit" class="btn w-100 fw-bold text-white" style="background-color: #3b66d1; border-radius: 50px; padding: 10px;">Sign Up</button>
                    </form>
                    <div class="mt-3 text-center small text-muted">
                        Already have an account? <a href="#" class="text-primary text-decoration-none fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
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