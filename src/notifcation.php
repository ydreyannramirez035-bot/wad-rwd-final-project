<?php
require_once __DIR__ ."/db.php";

function notif($role = null, $handle_actions = true) {
    if (!isset($_SESSION["user"])) {
        header("Location: ../index.php");
        exit;
    }

    $current_page = basename($_SERVER['PHP_SELF']);
    $user = $_SESSION["user"];
    $user_id = $user['id'];
    $db = get_db();

    // ==========================================
    // ADMIN LOGIC
    // ==========================================
    if ($role === 'admin') {
        // 1. Check Table Columns
        $cols = $db->query("PRAGMA table_info(users)");
        $hasCol = false;
        while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
            if ($col['name'] === 'last_notification_check') {
                $hasCol = true;
                break;
            }
        }
        if (!$hasCol) {
            $db->exec("ALTER TABLE users ADD COLUMN last_notification_check DATETIME DEFAULT '1970-01-01 00:00:00'");
        }

        // 2. Handle Badge Clear (AJAX)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
            $db->exec("UPDATE users SET last_notification_check = datetime('now', 'localtime') WHERE id = $user_id");
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        }

        // 3. System Activity Log
        $current_db_val = $db->querySingle("SELECT last_activity FROM admin_system_log WHERE id = 1");
        $current_time = time();
        if (!is_numeric($current_db_val) || ($current_db_val > $current_time + 60)) {
            $db->exec("UPDATE admin_system_log SET last_activity = strftime('%s', 'now') WHERE id = 1");
        }

        // 4. Handle Actions
        if ($handle_actions) {
            if (isset($_GET['action']) && $_GET['action'] === 'clear_notifications') {
                $db->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (message LIKE '%bio%' OR message LIKE '%phone%')");
                header("Location: " . $current_page);
                exit;
            }
            if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['id'])) {
                $notif_id = (int)$_GET['id'];
                $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
                header("Location: admin_student_manage.php");
                exit;
            }
        }

        // 5. Fetch Data
        $last_check_row = $db->querySingle("SELECT last_notification_check FROM users WHERE id = $user_id", true);
        $last_click = ($last_check_row && $last_check_row['last_notification_check']) ? $last_check_row['last_notification_check'] : '1970-01-01 00:00:00';

        $stmt_count = $db->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND created_at > :last_click AND (message LIKE '%bio%' OR message LIKE '%phone%')");
        $stmt_count->bindValue(':last_click', $last_click, SQLITE3_TEXT);
        $unread_count = $stmt_count->execute()->fetchArray()[0];

        $notif_result = $db->query("SELECT n.*, s.first_name, s.last_name FROM notifications n LEFT JOIN students s ON n.student_id = s.id WHERE (n.message LIKE '%bio%' OR n.message LIKE '%phone%') ORDER BY n.created_at DESC LIMIT 10");
    } 
    
    // ==========================================
    // STUDENT LOGIC
    // ==========================================
    elseif ($role === 'student') {
        // 1. Get Student ID
        $student = $db->querySingle("SELECT id FROM students WHERE user_id = $user_id", true);
        if (!$student) return ['unread_count' => 0, 'notifications' => []]; // Safety check
        $student_id = $student['id'];

        // 2. Check Table Columns
        $cols = $db->query("PRAGMA table_info(students)");
        $hasCol = false;
        while ($col = $cols->fetchArray(SQLITE3_ASSOC)) {
            if ($col['name'] === 'last_notification_check') {
                $hasCol = true;
                break;
            }
        }
        if (!$hasCol) {
            $db->exec("ALTER TABLE students ADD COLUMN last_notification_check DATETIME DEFAULT '1970-01-01 00:00:00'");
        }

        // 3. Handle Badge Clear (AJAX)
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_badge_only') {
            $db->exec("UPDATE students SET last_notification_check = datetime('now', 'localtime') WHERE id = $student_id");
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success']);
            exit;
        }

        // 4. Handle Actions
        if ($handle_actions) {
            if (isset($_GET['action']) && $_GET['action'] === 'clear_notifications') {
                $db->exec("UPDATE notifications SET is_read = 1 WHERE student_id = $student_id AND is_read = 0");
                header("Location: " . $current_page);
                exit;
            }
            if (isset($_GET['action']) && $_GET['action'] === 'read_notif' && isset($_GET['id'])) {
                $notif_id = (int)$_GET['id'];
                $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
                header("Location: student_schedule.php");
                exit;
            }
        }

        // 5. Fetch Data
        $last_check_row = $db->querySingle("SELECT last_notification_check FROM students WHERE id = $student_id", true);
        $last_click = ($last_check_row && $last_check_row['last_notification_check']) ? $last_check_row['last_notification_check'] : '1970-01-01 00:00:00';

        $stmt_count = $db->prepare("SELECT COUNT(*) FROM notifications WHERE student_id = :sid AND is_read = 0 AND created_at > :last_click");
        $stmt_count->bindValue(':sid', $student_id, SQLITE3_INTEGER);
        $stmt_count->bindValue(':last_click', $last_click, SQLITE3_TEXT);
        $unread_count = $stmt_count->execute()->fetchArray()[0];

        $notif_result = $db->query("SELECT * FROM notifications WHERE student_id = $student_id ORDER BY created_at DESC LIMIT 10");
    }

    // ==========================================
    // COMMON RETURN
    // ==========================================
    $notifications = [];
    if (isset($notif_result) && $notif_result) {
        while ($row = $notif_result->fetchArray(SQLITE3_ASSOC)) {
            $notifications[] = $row;
        }
    }
    
    return [
        'unread_count' => $unread_count ?? 0,
        'notifications' => $notifications
    ];
}