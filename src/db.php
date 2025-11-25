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

    //PARENT TABLE 1: Roles
    $db->exec('CREATE TABLE IF NOT EXISTS roles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        description TEXT
        )');

    // I want to dynamic to inserting the name, and description` for the role
    $db->exec("
    INSERT OR IGNORE INTO roles (name, description) VALUES
    ('admin', 'Full access to the system, can manage users and settings'),
    ('student', 'Regular user with standard access');
    ");
    
    // PARENT TABLE 2: Departments 
    $db->exec('CREATE TABLE IF NOT EXISTS departments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL
        )');

    // PARENT TABLE 3: Courses 
    $db->exec('CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_code TEXT NOT NULL UNIQUE,
        course_name TEXT NOT NULL,
        department_id INTEGER NOT NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id)
    )');

    // PARENT TABLE 4: Users (Centralized Login)
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        role_id INTEGER NOT NULL,
        username TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        passwordHash TEXT NOT NULL,
        FOREIGN KEY (role_id) REFERENCES roles(id)
    )');

    // INFO TABLE: Admins (Child of Users)
    $db->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        name TEXT NOT NULL, 
        position TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // INFO TABLE: Teachers 
    $db->exec('CREATE TABLE IF NOT EXISTS teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE, 
        name TEXT NOT NULL,
        email TEXT UNIQUE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )');

    // JUNCTION TABLE: TeacherDepartments (Many-to-Many Assignment)
    $db->exec('CREATE TABLE IF NOT EXISTS teacherDepartments (
        teacher_id INTEGER NOT NULL,
        department_id INTEGER NOT NULL,
        PRIMARY KEY (teacher_id, department_id),
        FOREIGN KEY (teacher_id) REFERENCES teachers(id),
        FOREIGN KEY (department_id) REFERENCES departments(id)
    )');

    // INFO TABLE: Students (Child of Users)
    $db->exec('CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL UNIQUE,
        studentNumber TEXT(50) NOT NULL UNIQUE,
        name TEXT(255) NOT NULL,
        gender TEXT(50), 
        age INTEGER,
        course_id INTEGER NOT NULL,
        year_level INTEGER,
        email TEXT(255) UNIQUE,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (course_id) REFERENCES courses(id)
    )');

    // INDEPENDENT TABLE: Schedules 
    $db->exec('CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        day TEXT NOT NULL,
        subject TEXT NOT NULL,
        teacher_id INTEGER NOT NULL, 
        room TEXT(10),
        time_start TIME,
        time_end TIME,
        FOREIGN KEY (teacher_id) REFERENCES teachers(id)
    );');

    // JUNCTION TABLE: StudentSchedules (Many-to-Many Enrollment)
    $db->exec('CREATE TABLE IF NOT EXISTS studentSchedules (
        user_id INTEGER NOT NULL,
        schedule_id INTEGER NOT NULL,
        PRIMARY KEY (user_id, schedule_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (schedule_id) REFERENCES schedules(id)
    );');

    return $db;
}