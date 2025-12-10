<?php
function get_db(): SQLite3
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }
    $dbPath = __DIR__ . '/../users.db';
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);

    // CRITICAL: Enable Foreign Key enforcement in SQLite
    $db->exec('PRAGMA foreign_keys = ON;');

    // --- PARENT TABLE 1: Roles ---
    $db->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT
    )');

    $roleCount = $db->querySingle("SELECT COUNT(*) FROM roles");
    if ($roleCount == 0) {
        $db->exec("
            INSERT INTO roles (name, description) VALUES
            ('admin', 'Full access to the system, can manage users and settings'),
            ('student', 'Regular user with standard access');
        ");
    }
    
    // --- PARENT TABLE 2: Courses ---
    $db->exec('CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_name TEXT NOT NULL UNIQUE
    )');

    $courseCount = $db->querySingle("SELECT COUNT(*) FROM courses");
    if ($courseCount == 0) {
        $db->exec("
            INSERT INTO courses (course_name) VALUES
            ('Bachelor of Science in Information System'),
            ('Associate in Computer Technology');
        ");
    }

    // --- PARENT TABLE 3: Users ---
    // FIX: Added 'UNIQUE' to username to prevent duplicates at the database level
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        username TEXT NOT NULL UNIQUE, 
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )');

    // --- INFO TABLE: Teachers ---
    $db->exec('CREATE TABLE IF NOT EXISTS teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $teacherCount = $db->querySingle("SELECT COUNT(*) FROM teachers");
    if ($teacherCount == 0) {
        $db->exec("
            INSERT INTO teachers (name) VALUES
            ('John Doe'), ('Jane Smith'), ('Mark Johnson'),
            ('Emily Davis'), ('Michael Brown'), ('Sarah Wilson'),
            ('William Taylor'), ('Olivia Moore'), ('James Anderson'),
            ('Sophia Martin');
        ");
    }

    $db->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject_name TEXT NOT NULL UNIQUE
    )");
    $subjectCount = $db->querySingle("SELECT COUNT(*) FROM subjects");
    if ($subjectCount == 0) {
        $db->exec("
            INSERT INTO subjects (subject_name) VALUES
            ('The Contemporary World'), ('Christian Teaching 3'), ('Responsive Web Design'), 
            ('DSA'), ('Pagbasa at Pagsulat'),
            ('IS Infra & Network Tech'), ('Path Fit 3'), ('ORGMAN'), ('Web Application Dev1'),
            ('Life & Works of Rizal');
        ");
    }

    // --- INFO TABLE: Students ---
    $db->exec('CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE,
        student_number TEXT(50) NOT NULL UNIQUE,
        last_name TEXT(50) NOT NULL,
        first_name TEXT(50),
        middle_name TEXT(50),
        age INTEGER,
        phone_number TEXT(20),
        course_id INTEGER NOT NULL,
        year_level INTEGER,
        email TEXT(255) UNIQUE,
        last_notification_check DATETIME DEFAULT "1970-01-01 00:00:00",
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS profiles_students (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    description TEXT(50),

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    )');

    // --- INDEPENDENT TABLE: Schedules ---
    $db->exec('CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        day TEXT NOT NULL,
        subject_id INTEGER NOT NULL,
        teacher_id INTEGER NOT NULL,
        room TEXT(10),
        time_start TIME,
        time_end TIME,
        course_id INTEGER NOT NULL,

        FOREIGN KEY (teacher_id) REFERENCES teachers(id),
        FOREIGN KEY (course_id) REFERENCES courses(id),
        FOREIGN KEY (subject_id) REFERENCES subjects(id)
    );');

    // NEW: Notifications Table
    $db->exec('CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER,
        message TEXT,
        is_read INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )');

    $db->exec('CREATE TABLE IF NOT EXISTS admin_system_log (
        id INTEGER PRIMARY KEY CHECK (id = 1),
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $db->exec("INSERT OR IGNORE INTO admin_system_log (id, last_activity) VALUES (1, strftime('%s', 'now'))");

    $db->exec('CREATE TRIGGER IF NOT EXISTS track_student_creation
        AFTER INSERT ON students
        BEGIN
            UPDATE admin_system_log SET last_activity = datetime("now", "localtime") WHERE id = 1;
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS track_schedule_creation
        AFTER INSERT ON schedules
        BEGIN
            UPDATE admin_system_log SET last_activity = datetime("now", "localtime") WHERE id = 1;
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS track_schedule_modification
        AFTER UPDATE ON schedules
        BEGIN
            UPDATE admin_system_log SET last_activity = datetime("now", "localtime") WHERE id = 1;
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS trigger_bio_update
        AFTER UPDATE OF description ON profiles_students
        WHEN OLD.description IS NOT NEW.description
        BEGIN
            INSERT INTO notifications (student_id, message, is_read, created_at)
            VALUES (NEW.student_id, "Updated their bio description.", 0, datetime("now", "localtime"));
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS trigger_bio_insert
        AFTER INSERT ON profiles_students
        BEGIN
            INSERT INTO notifications (student_id, message, is_read, created_at)
            VALUES (NEW.student_id, "Added a new bio description.", 0, datetime("now", "localtime"));
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS trigger_phone_update
        AFTER UPDATE OF phone_number ON students
        WHEN OLD.phone_number IS NOT NEW.phone_number
        BEGIN
            INSERT INTO notifications (student_id, message, is_read, created_at)
            VALUES (NEW.id, "Updated their phone number.", 0, datetime("now", "localtime"));
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS trigger_schedule_update
        AFTER UPDATE ON schedules
        BEGIN
            INSERT INTO notifications (student_id, message, is_read, created_at)
            SELECT id, 
                "Schedule Update: " || 
                (SELECT subject_name FROM subjects WHERE id = NEW.subject_id) || 
                " on " || NEW.day || " (" || IFNULL(NEW.time_start, "TBA") || "-" || IFNULL(NEW.time_end, "TBA") || ") " ||
                "in " || IFNULL(NEW.room, "TBA") || 
                " with " || (SELECT name FROM teachers WHERE id = NEW.teacher_id), 
                0, 
                datetime("now", "localtime")
            FROM students
            WHERE course_id = NEW.course_id;
        END;
    ');

    $db->exec('CREATE TRIGGER IF NOT EXISTS trigger_schedule_insert
        AFTER INSERT ON schedules
        BEGIN
            INSERT INTO notifications (student_id, message, is_read, created_at)
            SELECT id, 
                "New Class: " || 
                (SELECT subject_name FROM subjects WHERE id = NEW.subject_id) || 
                " on " || NEW.day || " (" || IFNULL(NEW.time_start, "TBA") || "-" || IFNULL(NEW.time_end, "TBA") || ") " ||
                "in " || IFNULL(NEW.room, "TBA") || 
                " with " || (SELECT name FROM teachers WHERE id = NEW.teacher_id), 
                0, 
                datetime("now", "localtime")
            FROM students
            WHERE course_id = NEW.course_id;
        END;
    ');

    return $db;
}