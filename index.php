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
    <title>ClassSched — School Scheduling</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --brand-blue: #3b66d1;
            --brand-blue-hover: #2d52b0;
            --brand-dark: #0f1724;
            --brand-light: #f5f7ff;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--brand-dark);
            background-color: white;
        }

        /* Custom Brand Utilities */
        .text-brand-blue { color: var(--brand-blue) !important; }
        .bg-brand-blue { background-color: var(--brand-blue) !important; }
        
        .btn-brand {
            background-color: var(--brand-blue);
            color: white;
            border-radius: 50px;
            padding: 10px 24px;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(59, 102, 209, 0.3);
        }
        .btn-brand:hover {
            background-color: var(--brand-blue-hover);
            color: white;
            transform: translateY(-2px);
        }

        .btn-outline-brand {
            border: 1px solid #e5e7eb;
            color: #4b5563;
            border-radius: 8px;
            padding: 12px 32px;
            font-weight: 600;
            background: white;
            transition: all 0.2s;
        }
        .btn-outline-brand:hover {
            border-color: var(--brand-blue);
            color: var(--brand-blue);
        }

        .btn-hero {
            background-color: var(--brand-blue);
            color: white;
            border-radius: 8px;
            padding: 14px 32px;
            font-weight: 700;
            border: none;
            box-shadow: 0 10px 15px -3px rgba(59, 102, 209, 0.3);
            transition: all 0.2s;
        }
        .btn-hero:hover {
            background-color: var(--brand-blue-hover);
            color: white;
            transform: translateY(-2px);
        }

        /* Added smooth scrolling for the window */
        html {
            scroll-behavior: smooth;
        }

        /* Decoration Blobs */
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
        }
        .blob-blue {
            top: -5rem;
            right: -5rem;
            width: 24rem;
            height: 24rem;
            background-color: #eff6ff; /* blue-50 equivalent */
        }
        .blob-purple {
            top: 10rem;
            left: -5rem;
            width: 18rem;
            height: 18rem;
            background-color: #faf5ff; /* purple-50 equivalent */
        }

        /* Mock UI Styling */
        .mock-card {
            border-radius: 1rem;
            border: 1px solid #f3f4f6;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: transform 0.5s;
        }
        .mock-card:hover {
            transform: rotate(0deg) !important;
        }
        .schedule-item {
            transition: background-color 0.2s;
        }
        .schedule-item:hover {
            background-color: #f8fafc;
        }

        /* Animation */
        .animate-bounce-slow {
            animation: bounce 3s infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(-5%); }
            50% { transform: translateY(0); }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 position-relative">

    <!-- HEADER -->
    <header class="w-100 border-bottom bg-white sticky-top py-3">
        <div class="container d-flex justify-content-between align-items-center">
            
            <!-- Logo -->
            <div class="d-flex align-items-center cursor-pointer" onclick="window.scrollTo(0,0)" style="cursor: pointer;">
                <img src="../img/logo.jpg" width="50" height="50" class="me-2">
                <span class="fs-4 fw-bold text-dark lh-1">Class</span><span class="fs-4 text-brand-blue">Sched</span></span>
            </div>

            <!-- Nav Actions -->
            <nav class="d-flex align-items-center gap-3">
                <button type="button" class="btn btn-link text-decoration-none text-secondary fw-medium d-none d-sm-block" data-bs-toggle="modal" data-bs-target="#loginModal">
                    Login
                </button>
                <button type="button" class="btn btn-brand" data-bs-toggle="modal" data-bs-target="#registerModal">
                    Get Started
                </button>
            </nav>
        </div>
    </header>

    <!-- HERO SECTION -->
    <main class="flex-grow-1 d-flex align-items-center position-relative py-5 overflow-hidden">
        <!-- Background Blob decoration -->
        <div class="blob blob-blue"></div>
        <div class="blob blob-purple"></div>

        <div class="container">
            <div class="row align-items-center gy-5">
                
                <!-- Text Content -->
                <div class="col-lg-6 text-center text-lg-start">
                    <div class="d-inline-block px-3 py-1 bg-light text-brand-blue text-uppercase fw-bold rounded-pill mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        Version 2.0 is live
                    </div>
                    <h1 class="display-4 fw-bolder text-dark mb-4 lh-sm">
                        Simplify Your <br>
                        <span class="text-brand-blue">School Scheduling</span>
                    </h1>
                    <p class="lead text-secondary mb-5 mx-auto mx-lg-0" style="max-width: 500px;">
                        Stop wrestling with spreadsheets. Easily manage classes, teachers, and classrooms with our intelligent conflict-free engine.
                    </p>
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center justify-content-lg-start">
                        <button type="button" class="btn btn-hero" data-bs-toggle="modal" data-bs-target="#registerModal">
                            Create Schedule
                        </button>
                        <button class="btn btn-outline-brand d-flex align-items-center justify-content-center gap-2">
                            <i class="fa-solid fa-play"></i> Watch Demo
                        </button>
                    </div>
                    
                    <!-- Trust Badges -->
                    <div class="pt-4 mt-4 d-flex flex-column flex-sm-row align-items-center justify-content-center justify-content-lg-start gap-3 text-secondary small">
                        <span>Trusted by 500+ schools</span>
                        <div class="d-flex ms-2">
                            <div class="rounded-circle bg-secondary border border-2 border-white" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-secondary border border-2 border-white opacity-75" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-secondary border border-2 border-white opacity-50" style="width:32px; height:32px; margin-right: -8px;"></div>
                            <div class="rounded-circle bg-brand-blue text-white d-flex align-items-center justify-content-center border border-2 border-white fw-bold" style="width:32px; height:32px; font-size: 10px;">+</div>
                        </div>
                    </div>
                </div>

                <!-- Visual Content (Mock UI) -->
                <div class="col-lg-6">
                    <div class="position-relative mx-auto" style="max-width: 500px;">
                        <!-- Decorative Elements -->
                        <div class="position-absolute rounded-circle bg-warning opacity-50" style="width: 6rem; height: 6rem; top: -1.5rem; right: -1.5rem; filter: blur(20px);"></div>
                        
                        <!-- Main Card -->
                        <div class="bg-white mock-card overflow-hidden" style="transform: rotate(1deg);">
                            <!-- Fake Browser Header -->
                            <div class="bg-light border-bottom p-3 d-flex gap-2">
                                <div class="rounded-circle bg-danger opacity-50" style="width: 12px; height: 12px;"></div>
                                <div class="rounded-circle bg-warning opacity-50" style="width: 12px; height: 12px;"></div>
                                <div class="rounded-circle bg-success opacity-50" style="width: 12px; height: 12px;"></div>
                            </div>
                            
                            <!-- Dashboard Content -->
                            <div class="p-4">
                                <div class="d-flex justify-content-between align-items-end mb-4">
                                    <div>
                                        <h5 class="fw-bold text-dark mb-0">Grade 10 - Section A</h5>
                                        <p class="small text-muted mb-0">Mathematics Dept.</p>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success bg-opacity-10 text-success rounded px-2 py-1">No Conflicts</span>
                                    </div>
                                </div>

                                <!-- Schedule Grid Mockup -->
                                <div class="d-flex flex-column gap-3">
                                    <!-- Item 1 -->
                                    <div class="d-flex align-items-center gap-3 p-2 rounded schedule-item border border-white">
                                        <div class="text-center text-muted fw-bold small" style="width: 40px;">08:00</div>
                                        <div class="flex-grow-1 p-2 rounded border-start border-4 border-primary bg-light">
                                            <div class="fw-bold text-primary small">Algebra II</div>
                                            <div class="d-flex justify-content-between text-primary opacity-75" style="font-size: 0.75rem;">
                                                <span>Rm 304</span>
                                                <span><i class="fa-regular fa-user me-1"></i> Mr. Smith</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Item 2 -->
                                    <div class="d-flex align-items-center gap-3 p-2 rounded schedule-item border border-white">
                                        <div class="text-center text-muted fw-bold small" style="width: 40px;">09:30</div>
                                        <div class="flex-grow-1 p-2 rounded border-start border-4 border-info bg-light">
                                            <div class="fw-bold text-info small">Physics Lab</div>
                                            <div class="d-flex justify-content-between text-info opacity-75" style="font-size: 0.75rem;">
                                                <span>Lab 2</span>
                                                <span><i class="fa-regular fa-user me-1"></i> Ms. Davis</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Item 3 -->
                                    <div class="d-flex align-items-center gap-3 p-2 rounded schedule-item border border-white">
                                        <div class="text-center text-muted fw-bold small" style="width: 40px;">11:00</div>
                                        <div class="flex-grow-1 p-2 rounded border-start border-4 border-warning bg-light">
                                            <div class="fw-bold text-warning small text-dark">World History</div>
                                            <div class="d-flex justify-content-between text-warning opacity-75" style="font-size: 0.75rem;">
                                                <span class="text-dark">Rm 102</span>
                                                <span class="text-dark"><i class="fa-regular fa-user me-1"></i> Mr. Johnson</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Badge -->
                        <div class="position-absolute bg-white p-3 rounded-3 shadow-sm border animate-bounce-slow d-flex align-items-center gap-3" style="bottom: -1.5rem; left: -1.5rem;">
                            <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                <i class="fa-solid fa-check"></i>
                            </div>
                            <div>
                                <div class="text-muted fw-medium" style="font-size: 0.75rem;">Export Status</div>
                                <div class="fw-bold text-dark small">Ready to Print</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FEATURES STRIP -->
    <section class="bg-light py-5 border-top">
        <div class="container">
            <div class="row g-4 text-center">
                <div class="col-md-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 bg-primary bg-opacity-10 text-primary" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Instant Generation</h3>
                    <p class="text-muted small">Create complex schedules in seconds using our advanced AI algorithm.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 bg-info bg-opacity-10 text-info" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Conflict Free</h3>
                    <p class="text-muted small">Automatically detects and resolves double-bookings for teachers and rooms.</p>
                </div>
                <div class="col-md-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 mb-3 bg-warning bg-opacity-10 text-warning" style="width: 48px; height: 48px; font-size: 1.25rem;">
                        <i class="fa-solid fa-cloud-arrow-down"></i>
                    </div>
                    <h3 class="h5 fw-bold mb-2">Easy Export</h3>
                    <p class="text-muted small">Download schedules as PDF, Excel, or share directly via email.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-white border-top py-4 text-center text-muted small">
        <div class="container">
            <p class="mb-2">ClassSched © 2025 — Simplifying Education.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="#" class="text-decoration-none text-muted hover-brand">Privacy</a>
                <span>•</span>
                <a href="#" class="text-decoration-none text-muted hover-brand">Terms</a>
                <span>•</span>
                <a href="#" class="text-decoration-none text-muted hover-brand">Support</a>
            </div>
        </div>
    </footer>


    <!-- LOGIN MODAL -->
    <div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-body p-4 p-sm-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 fw-bold mb-0">Welcome Back</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <!-- PHP SUCCESS MESSAGE -->
                    <?php if (!empty($registerSuccess)): ?>
                        <div class="alert alert-success small"><?php echo htmlspecialchars($registerSuccess); ?></div>
                    <?php endif; ?>

                    <!-- PHP ERROR MESSAGE -->
                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger small"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- HIDDEN ACTION FIELD -->
                        <input type="hidden" name="action_type" value="login">
                        
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small">Username or Email</label>
                            <input type="text" name="username_or_email" class="form-control form-control-lg fs-6" placeholder="you@school.edu" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium text-secondary small">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg fs-6" placeholder="••••••••" required>
                        </div>
                        <button type="submit" class="btn btn-brand w-100 py-2">Sign In</button>
                    </form>
                    <div class="mt-4 text-center small text-muted">
                        Don't have an account? 
                        <!-- Switch Modals -->
                        <a href="#" class="text-brand-blue fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- REGISTER MODAL -->
    <div class="modal fade" id="registerModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-body p-4 p-sm-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 fw-bold mb-0">Create Account</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- PHP ERROR MESSAGE -->
                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger small"><?php echo htmlspecialchars($registerError); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <!-- HIDDEN ACTION FIELD -->
                        <input type="hidden" name="action_type" value="register">

                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small">Username</label>
                            <input type="text" name="username" class="form-control form-control-lg fs-6" placeholder="Ex. PrincipalJohn" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small">Email Address</label>
                            <input type="email" name="email" class="form-control form-control-lg fs-6" placeholder="admin@school.edu" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-medium text-secondary small">Password</label>
                            <input type="password" name="password" class="form-control form-control-lg fs-6" placeholder="Create a password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-medium text-secondary small">Confirm Password</label>
                            <input type="password" name="confirmPassword" class="form-control form-control-lg fs-6" placeholder="Confirm password" required>
                        </div>
                        <button type="submit" class="btn btn-brand w-100 py-2">Start Free Trial</button>
                    </form>
                    <div class="mt-4 text-center small text-muted">
                        Already registered? 
                        <a href="#" class="text-brand-blue fw-semibold text-decoration-none" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Auto-Open Modals on Error/Success -->
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