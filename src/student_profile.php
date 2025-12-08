<?php
session_start();
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/notification.php";

if (!isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

$db = get_db();
$user = $_SESSION["user"];
$userId = $user["id"];

// Notification Data
$notif_data = notif('student', true); 
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];
$highlight_count = $notif_data['highlight_count'];

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "update_profile") {
    $phone = trim($_POST["phone_number"]);
    $bio = trim($_POST["bio"]); 
    $studentId = (int)$_POST["student_id"];

    $sql = "UPDATE students SET phone_number=? WHERE id=? AND user_id=?";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(1, $phone);
    $stmt->bindValue(2, $studentId);
    $stmt->bindValue(3, $userId);
    
    $updateStudent = $stmt->execute();

    // Update Bio in profiles_students table
    $checkSql = "SELECT id FROM profiles_students WHERE student_id = ?";
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->bindValue(1, $studentId);
    $checkResult = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($checkResult) {
        $bioSql = "UPDATE profiles_students SET description = ? WHERE student_id = ?";
        $bioStmt = $db->prepare($bioSql);
        $bioStmt->bindValue(1, $bio);
        $bioStmt->bindValue(2, $studentId);
        $bioStmt->execute();
    } else {
        $bioSql = "INSERT INTO profiles_students (student_id, description) VALUES (?, ?)";
        $bioStmt = $db->prepare($bioSql);
        $bioStmt->bindValue(1, $studentId);
        $bioStmt->bindValue(2, $bio);
        $bioStmt->execute();
    }
    
    if($updateStudent){
        $msg = "Profile updated successfully!";
    } else {
        $msg = "Error updating profile.";
    }
}

$sql = "SELECT s.*, c.course_name, p.description as bio
        FROM students s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN profiles_students p ON s.id = p.student_id
        WHERE s.user_id = ?";

$stmt = $db->prepare($sql);
$stmt->bindValue(1, $userId);
$result = $stmt->execute();
$student = $result->fetchArray(SQLITE3_ASSOC); 

if (!$student) {
    $student = [
        'id' => 0,
        'first_name' => $user['name'], 
        'last_name' => '', 
        'email' => 'No Email', 
        'phone_number' => 'No Phone',
        'year_level' => 1,
        'course_name' => '', 
        'bio' => ''
    ];
}

$fullName = $student['first_name'] . ' ' . $student['last_name'];
// Initials Logic
$f_initial = strtoupper(substr($student['first_name'] ?: $user['name'], 0, 1));
$l_initial = !empty($student['last_name']) ? strtoupper(substr($student['last_name'], 0, 1)) : '';
if ($l_initial === '') {
    $initials = strtoupper(substr($user['name'], 0, 2));
} else {
    $initials = $f_initial . $l_initial;
}

$courseName = $student['course_name'] ?? '';
$courseAcronym = "Course"; 
if (stripos($courseName, 'Information System') !== false) {
    $courseAcronym = "BSIS";
} elseif (stripos($courseName, 'Computer Technology') !== false) {
    $courseAcronym = "ACT";
} elseif (!empty($courseName)) {
    $courseAcronym = substr($courseName, 0, 4); 
}

$yearLevel = $student['year_level'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSched | Profile</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../styles/student_profile.css">
    <link rel="stylesheet" href="../styles/student.css">
    <link rel="stylesheet" href="../styles/notification.css">
</head>

<body class="d-flex flex-column min-vh-100 position-relative">

    <nav class="navbar navbar-expand-sm sticky-top">
        <div class="container-fluid px-4">
            
            <div class="d-flex align-items-center">
                <button class="navbar-toggler me-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar" aria-controls="offcanvasNavbar">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <a class="navbar-brand me-0" href="student_dashboard.php">
                    <img src="../img/logo.png" width="60" height="60">
                </a>
            </div>
            <div class="offcanvas offcanvas-start" tabindex="-1" id="offcanvasNavbar" aria-labelledby="offcanvasNavbarLabel">
            <div class="offcanvas-header">
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                <img src="../img/logo.png" width="60" height="60" class="me-2">
                <h5 class="offcanvas-title" id="offcanvasNavbarLabel"></h5>
            </div>
            <div class="offcanvas-body">
                <ul class="navbar-nav justify-content-start flex-grow-1 pe-3">
                    <li class="nav-item"><a class="nav-link" href="student_dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="student_schedule.php">Class Schedule</a></li>
                </ul>
            </div>
            </div>

            <div class="d-flex align-items-center gap-3">              
                
                <div class="dropdown notification-container me-4 position-relative">
                    <i class="fa-solid fa-bell dropdown-toggle" 
                       id="notificationDropdown" 
                       data-bs-toggle="dropdown" 
                       aria-expanded="false" 
                       style="font-size: 1.2rem;">
                    </i>
                    
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge">
                            <?php echo ($unread_count > 9) ? '9+' : $unread_count; ?>
                        </span>
                    <?php endif; ?>

                    <ul class="dropdown-menu dropdown-menu-end notification-list shadow" aria-labelledby="notificationDropdown">
                        <li class="dropdown-header d-flex justify-content-between align-items-center">
                            <span class="fw-bold">Notifications</span>
                            <?php if ($highlight_count > 0): ?> <a href="?action=clear_notifications" class="text-decoration-none small text-primary">Mark all read</a>
                            <?php endif; ?>
                        </li>

                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notif): ?>
                                <?php 
                                    $status_class = ($notif['is_read'] == 0) ? 'fw-bold bg-light border-start border-3 border-primary' : 'text-muted';
                                ?>
                                <li>
                                    <a class="dropdown-item notification-item p-3 <?php echo $status_class; ?>" href="?action=read_notif&id=<?php echo $notif['id']; ?>">
                                        
                                        <div class="notif-content">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <strong class="<?php echo ($notif['is_read'] == 0) ? 'text-dark' : ''; ?>">
                                                    <?php echo htmlspecialchars(!empty($notif['first_name']) ? $notif['first_name'] . ' ' . $notif['last_name'] : 'ClassSched Alert'); ?>
                                                </strong>
                                                
                                                <?php if ($notif['is_read'] == 0): ?>
                                                    <span class="badge bg-primary rounded-pill" style="font-size: 0.5rem;">NEW</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="small mt-1 <?php echo ($notif['is_read'] == 0) ? 'text-dark' : ''; ?>">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                            </div>
                                            
                                            <div class="notif-time small mt-1 text-secondary">
                                                <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                                            </div>
                                        </div>

                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="text-center py-4 text-muted small">No notifications yet</li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="dropdown">
                    <button class="btn btn-student dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <span class="student-text">Student • </span><?php echo htmlspecialchars($initials); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-1"><small>Signed in as<br><b><?php echo htmlspecialchars($display_name); ?></b></small></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="student_profile.php">Profile</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container px-4 py-5">
        
        <?php if($msg): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="fw-bold text-dark mb-2">My Profile</h1>
        </div>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="card-custom text-center h-100">
                    
                    <div class="profile-avatar mb-3">
                        <?= $initials ?>
                    </div>

                    <h4 class="fw-bold mb-1 text-dark">
                        <?= htmlspecialchars($fullName) ?>
                    </h4>
                    
                    <p class="text-secondary small mb-3">
                        Student • <?= htmlspecialchars($courseAcronym . ' ' . $yearLevel) ?>
                    </p>

                    <div class="bio-section mb-4 px-3 py-2 bg-light rounded-3">
                        <p class="text-muted small mb-0 fst-italic text-break">
                            <?= !empty($student['bio']) ? nl2br(htmlspecialchars($student['bio'])) : 'No bio added yet.' ?>
                        </p>
                    </div>

                    <button type="button" class="btn btn-edit mb-4 w-100" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-fill me-2"></i> Edit Profile
                    </button>

                    <div class="text-start mt-auto pt-3 border-top">
                        <h6 class="fw-bold mb-3 small text-uppercase text-secondary ls-1">Contact Info</h6>
                        <div class="d-flex align-items-center mb-2">
                            <div class="icon-circle bg-primary bg-opacity-10 text-primary me-2">
                                <i class="bi bi-envelope"></i>
                            </div>
                            <span class="small text-dark"><?= htmlspecialchars($student['email']) ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="icon-circle bg-primary bg-opacity-10 text-primary me-2">
                                <i class="bi bi-telephone"></i>
                            </div>
                            <span class="small text-dark"><?= htmlspecialchars($student['phone_number']) ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-custom h-100">
                    <h5 class="fw-medium mb-4 text-dark">Profile Details</h5>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-custom">First Name</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['first_name']) ?>" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label-custom">Last Name</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['last_name']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Email Address</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['email']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Phone Number</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['phone_number']) ?>" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label-custom">Course</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($courseAcronym) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Year Level</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($yearLevel) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form method="POST" action="">
                    <div class="modal-body pt-4">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">First Name</label>
                                <input type="text" class="form-control bg-light" 
                                       value="<?= htmlspecialchars($student['first_name']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Last Name</label>
                                <input type="text" class="form-control bg-light" 
                                       value="<?= htmlspecialchars($student['last_name']) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-secondary">Email</label>
                                <input type="email" class="form-control bg-light" 
                                       value="<?= htmlspecialchars($student['email']) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Phone Number</label>
                                <input type="text" name="phone_number" class="form-control" 
                                       value="<?= htmlspecialchars($student['phone_number']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold text-dark">Bio</label>
                                <textarea name="bio" class="form-control text-break" rows="3" maxlength="150" style="resize: none;" placeholder="Tell us about yourself..."><?= htmlspecialchars($student['bio']) ?></textarea>
                                <div class="form-text small">Max 150 characters.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top-0 pt-0 pb-4 px-3">
                        <button type="button" class="btn btn-light text-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <footer class="bg-white border-top py-4 text-center">
        <div class="container">
            <p class="text-muted small mb-0">ClassSched © 2025 — Designed for Efficiency.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>
