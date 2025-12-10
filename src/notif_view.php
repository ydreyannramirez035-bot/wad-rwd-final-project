<?php
session_start();

require_once __DIR__ . "/notification.php";
require_once __DIR__ . "/db.php";

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$db = get_db();

$user_id = null;
$user_name = 'Student';

if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
    $user_name = $_SESSION['user']['name'] ?? 'Student';
} elseif (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_name = $_SESSION['user_name'] ?? 'Student';
} else {
    header("Location: index.php");
    exit();
}

$student_id = 0;

$student = [
    'id' => 0,
    'first_name' => $user_name, 
    'last_name' => '' 
];

$stmt = $db->prepare("SELECT id, first_name, last_name FROM students WHERE user_id = :uid");
$stmt->bindValue(':uid', $user_id, SQLITE3_INTEGER);
$res = $stmt->execute();
$student_row = $res->fetchArray(SQLITE3_ASSOC);

if ($student_row) {
    $student = $student_row;
    $student_id = $student['id'];
}

$f_name = $student['first_name'] ?? '';
$l_name = $student['last_name'] ?? '';

$fullName = trim($f_name . ' ' . $l_name);
if (empty($fullName)) {
    $fullName = 'Student Name'; 
}

$initials = 'ST'; 
if (!empty($f_name)) {
    $initials = strtoupper(substr($f_name, 0, 1));
    if (!empty($l_name)) {
        $initials .= strtoupper(substr($l_name, 0, 1));
    }
}

$db = get_db();
if (isset($_GET['action']) && $student_id > 0) {
    
    if ($_GET['action'] == 'read_notif' && isset($_GET['id'])) {
        $notif_id = (int)$_GET['id'];
        
        $stmt_check = $db->prepare("SELECT message FROM notifications WHERE id = :id");
        $stmt_check->bindValue(':id', $notif_id, SQLITE3_INTEGER);
        $res_check = $stmt_check->execute();
        $row_check = $res_check->fetchArray(SQLITE3_ASSOC);
        $message_content = $row_check ? $row_check['message'] : '';

        $update = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND student_id = :sid");
        $update->bindValue(':id', $notif_id, SQLITE3_INTEGER);
        $update->bindValue(':sid', $student_id, SQLITE3_INTEGER);
        $update->execute();
        
        if (stripos($message_content, 'Schedule') !== false || stripos($message_content, 'Class') !== false) {
            header("Location: student_schedule.php");
            exit();
        } else {
            header("Location: notif_view.php");
            exit();
        }
    }
    
    elseif ($_GET['action'] == 'clear_notifications') {
        $update = $db->prepare("UPDATE notifications SET is_read = 1 WHERE student_id = :sid");
        $update->bindValue(':sid', $student_id, SQLITE3_INTEGER);
        $update->execute();
        
        header("Location: notif_view.php");
        exit();
    }

    // --- NEW ACTION: Clear History (Delete Read Notifications) ---
    elseif ($_GET['action'] == 'clear_history') {
        // Only delete notifications that are already read
        $delete = $db->prepare("DELETE FROM notifications WHERE student_id = :sid AND is_read = 1");
        $delete->bindValue(':sid', $student_id, SQLITE3_INTEGER);
        $delete->execute();
        
        header("Location: notif_view.php");
        exit();
    }
}

$notif_data = notif('student', true); 
$unread_count = $notif_data['highlight_count']; 
$total_count = $unread_count;
$unread_count = $notif_data['unread_count'];
$notifications = $notif_data['notifications'];


// Check if we have any read notifications to enable the "Clear History" button
$has_read_notifications = false;
if (!empty($notifications)) {
    foreach ($notifications as $n) {
        if ($n['is_read'] == 1) {
            $has_read_notifications = true;
            break;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassSched | Notifications</title>
    <link rel="icon" href="../img/logo.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/student_dashboard.css">
    <link rel="stylesheet" href="../styles/student.css">
    <link rel="stylesheet" href="../styles/notification.css">
    <link rel="stylesheet" href="../styles/notif_view.css">
</head>
<body>

    <?php require_once __DIR__ . "/student_nav.php"; ?>

    <div class="container mt-4 mb-5">
        
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h4 class="mb-1 fw-bold text-dark">
                    Notifications
                </h4>
                <p class="mb-0 text-muted small">
                    You have <?php echo $total_count; ?> unread notification<?php echo $total_count !== 1 ? 's' : ''; ?>
                </p>
            </div>
            
            <div class="d-flex gap-2">
                <!-- Clear History Button (Only shows if there are read notifications) -->
                <?php if ($has_read_notifications): ?>
                    <a href="?action=clear_history" 
                       class="btn btn-outline-danger rounded-pill px-3 py-2"
                       onclick="return confirm('Are you sure you want to delete all read notifications? This cannot be undone.');">
                        <i class="fa-solid fa-trash me-1"></i> Clear History
                    </a>
                <?php endif; ?>

                <!-- Mark All Read Button (Only shows if there are unread notifications) -->
                <?php if ($total_count > 0): ?>
                    <a href="?action=clear_notifications" class="btn mark-all-btn rounded-pill px-3 py-2">
                        <i class="fa-solid fa-check-double me-1"></i> Mark all read
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($notifications)): ?>
            <div class="notif-card shadow-sm overflow-hidden mt-3">
                <?php foreach ($notifications as $notif): 
                    $is_unread = ($notif['is_read'] == 0);
                    $item_class = $is_unread ? 'unread' : 'read';
                ?>
                    <a href="student_schedule.php?action=read_notif&id=<?php echo $notif['id']; ?>" class="notif-item <?php echo $item_class; ?>">
                        <div class="d-flex flex-column">
                            
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div class="notif-title">
                                    <?php echo htmlspecialchars(!empty($notif['first_name']) ? $notif['first_name'] : 'System Notification'); ?>
                                    <?php if ($is_unread): ?>
                                        <span class="badge bg-danger rounded-pill ms-2" style="font-size: 0.6rem;"></span>
                                    <?php endif; ?>
                                </div>
                                <div class="notif-time ms-3">
                                    <i class="fa-regular fa-clock me-1"></i>
                                    <?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?>
                                </div>
                            </div>

                            <div class="notif-text">
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </div>

                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="notif-card shadow-sm p-5 text-center mt-3">
                <div class="empty-state-icon">
                     <i class="fa-regular fa-bell-slash"></i>
                </div>
                <h6 class="text-secondary fw-bold">No notifications yet</h6>
                <p class="small text-muted mb-0">We'll let you know when updates arrive.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/notification.js"></script>
</body>
</html>