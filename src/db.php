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

    // FIX: Check if roles exist before trying to insert
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

    // FIX: Check if courses exist before inserting
    $courseCount = $db->querySingle("SELECT COUNT(*) FROM courses");
    if ($courseCount == 0) {
        $db->exec("
            INSERT INTO courses (course_name) VALUES
            ('Bachelor of Science in Information System'),
            ('Associate in Computer Technology');
        ");
    }

    // --- PARENT TABLE 3: Users ---
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        username TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )');

    // --- INFO TABLE: Teachers ---
    $db->exec('CREATE TABLE IF NOT EXISTS teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    // FIX: Check if teachers exist before inserting
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
            ('Data Structure and Algorithm'), ('Pagtuturo at Pagtataya sa Pagbasa at Pagsulat'),
            ('IS Infra & Network Tech'), ('Path Fit 3'), ('Organization and Management Concepts'), ('Web Application Dev1'),
            ('Life & Works of Rizal');
        ");
    }

    // --- INFO TABLE: Students ---
    $db->exec('CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER UNIQUE,
        student_number TEXT(50) NOT NULL,
        last_name TEXT(50) NOT NULL,
        first_name TEXT(50),
        middle_name TEXT(50),
        age INTEGER,
        phone_number TEXT(20),
        course_id INTEGER NOT NULL,
        year_level INTEGER,
        email TEXT(255) UNIQUE,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
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

    return $db;
}