<?php
session_start();
require_once __DIR__ . "/db.php";

if (!isset($_SESSION["user"])) {
    header("Location: login.php");
    exit;
}

$db = get_db();
$userId = $_SESSION["user"]["id"];

$msg = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === "update_profile") {
    // FIX: Only capture Phone and Bio. 
    // We do NOT capture First/Last/Email because they are disabled inputs and won't be submitted.
    $phone = trim($_POST["phone_number"]);
    $bio = trim($_POST["bio"]); 
    $studentId = (int)$_POST["student_id"];

    // FIX: Removed the check for empty Name/Email because they aren't being sent.
    // We proceed directly to update only the Phone Number in the main table.
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
        'first_name' => 'Student', 
        'last_name' => 'Name', 
        'email' => 'No Email', 
        'phone_number' => 'No Phone',
        'year_level' => 1,
        'course_name' => '', 
        'bio' => ''
    ];
}

$fullName = $student['first_name'] . ' ' . $student['last_name'];
$words = explode(" ", $fullName);
$initials = "";
foreach($words as $w) { 
    if(!empty($w)) $initials .= strtoupper($w[0]); 
}
$initials = substr($initials, 0, 2);

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
    <title>Profile | ClassSched</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../styles/student_profile.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="#">
                <div style="width:40px; height:40px; background:#e0e5f2; border-radius:50%;"></div> 
                <span class="brand-text">ClassSched</span>
            </a>

            <div class="d-flex align-items-center gap-4">
                <div class="d-none d-md-flex gap-4">
                    <a href="student_dashboard.php" class="text-decoration-none text-dark fw-medium">Dashboard</a>
                    <a href="student_schedule.php" class="text-decoration-none text-dark fw-medium">Class Schedule</a>
                </div>
                
                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                         <div class="rounded-circle d-flex justify-content-center align-items-center bg-primary text-white" 
                              style="width: 40px; height: 40px; font-size: 14px;">
                            <?= $initials ?>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-2" style="border-radius: 12px;">
                        <li><a class="dropdown-item rounded" href="student_profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item rounded text-danger" href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        
        <?php if($msg): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <?= htmlspecialchars($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold m-0">My profile</h3>
        </div>

        <div class="row g-4">

            <div class="col-lg-4">
                <div class="card-custom text-center">
                    
                    <div class="profile-avatar">
                        <?= $initials ?>
                    </div>

                    <h4 class="fw-bold mb-1">
                        <?= htmlspecialchars($fullName) ?>
                    </h4>
                    
                    <p class="text-secondary small mb-3">
                        Student • <?= htmlspecialchars($courseAcronym . ' ' . $yearLevel) ?>
                    </p>

                    <p class="text-muted small mb-4 px-3">
                        <?= !empty($student['bio']) ? nl2br(htmlspecialchars($student['bio'])) : 'No bio added yet.' ?>
                    </p>

                    <button type="button" class="btn btn-edit mb-4" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-fill me-2"></i> Edit profile
                    </button>

                    <div class="text-start mt-auto">
                        <h6 class="fw-bold mb-3 small text-uppercase text-secondary ls-1">Contacts</h6>
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-envelope text-primary me-2"></i>
                            <span class="small"><?= htmlspecialchars($student['email']) ?></span>
                        </div>
                        <div class="d-flex align-items-center">
                            <i class="bi bi-telephone text-primary me-2"></i>
                            <span class="small"><?= htmlspecialchars($student['phone_number']) ?></span>
                        </div>
                    </div>

                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-custom">
                    <h5 class="fw-bold mb-4">Profile details</h5>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label-custom">First name</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['first_name']) ?>" readonly>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label-custom">Last name</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['last_name']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Email address</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['email']) ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">Phone</label>
                            <input type="text" class="form-control form-control-view" 
                                   value="<?= htmlspecialchars($student['phone_number']) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <footer>
            ClassSched © 2025 — All rights reserved.
        </footer>
    </main>

    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile">
                        <input type="hidden" name="student_id" value="<?= $student['id'] ?>">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">First Name</label>
                                <input type="text" name="first_name" class="form-control" 
                                       value="<?= htmlspecialchars($student['first_name']) ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Last Name</label>
                                <input type="text" name="last_name" class="form-control" 
                                       value="<?= htmlspecialchars($student['last_name']) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($student['email']) ?>" disabled>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Phone Number</label>
                                <input type="text" name="phone_number" class="form-control" 
                                       value="<?= htmlspecialchars($student['phone_number']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Bio / Notes</label>
                                <textarea name="bio" class="form-control" rows="3" maxlength="25" style="resize: none;" placeholder="Tell us about yourself..."><?= htmlspecialchars($student['bio']) ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light text-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>