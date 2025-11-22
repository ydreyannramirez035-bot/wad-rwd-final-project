<?php
// Simple SQLite connection using SQLite3 class and table auto-create
function get_db(): SQLite3
{
    static $db = null;
    if ($db !== null) {
        return $db;
    }
    $dbPath = __DIR__ . '/../users.db';
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);

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
        courseCode TEXT NOT NULL UNIQUE,
        courseName TEXT NOT NULL,
        departmentId INTEGER NOT NULL,
        FOREIGN KEY (departmentId) REFERENCES departments(id)
    )');

    // PARENT TABLE 4: Users (Centralized Login)
    $db->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        roleId INTEGER NOT NULL,
        username TEXT NOT NULL UNIQUE,
        passwordHash TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        FOREIGN KEY (roleId) REFERENCES roles(id)
    )');

    // INFO TABLE: Admins (Child of Users)
    $db->exec('CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        userId INTEGER NOT NULL UNIQUE,
        name TEXT NOT NULL, 
        position TEXT,
        FOREIGN KEY (userId) REFERENCES users(id)
    )');

    // INFO TABLE: Teachers 
    $db->exec('CREATE TABLE IF NOT EXISTS teachers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        userId INTEGER NOT NULL UNIQUE, 
        name TEXT NOT NULL,
        email TEXT UNIQUE,
        FOREIGN KEY (userId) REFERENCES users(id)
    )');

    // JUNCTION TABLE: TeacherDepartments (Many-to-Many Assignment)
    $db->exec('CREATE TABLE IF NOT EXISTS teacherDepartments (
        teacherId INTEGER NOT NULL,
        departmentId INTEGER NOT NULL,
        PRIMARY KEY (teacherId, departmentId),
        FOREIGN KEY (teacherId) REFERENCES teachers(id),
        FOREIGN KEY (departmentId) REFERENCES departments(id)
    )');

    // INFO TABLE: Students (Child of Users)
    $db->exec('CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        userId INTEGER NOT NULL UNIQUE,
        studentNumber TEXT(50) NOT NULL UNIQUE,
        name TEXT(255) NOT NULL,
        gender TEXT(50), 
        age INTEGER,
        courseId INTEGER NOT NULL,
        yearLevel INTEGER,
        email TEXT(255) UNIQUE,
        FOREIGN KEY (userId) REFERENCES users(id),
        FOREIGN KEY (courseId) REFERENCES courses(id)
    )');

    // INDEPENDENT TABLE: Schedules 
    $db->exec('CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        subject TEXT NOT NULL,
        teacherId INTEGER NOT NULL, 
        room TEXT(10),
        timeStart TIME,
        timeEnd TIME,
        FOREIGN KEY (teacherId) REFERENCES teachers(id)
    );');

    // JUNCTION TABLE: StudentSchedules (Many-to-Many Enrollment)
    $db->exec('CREATE TABLE IF NOT EXISTS studentSchedules (
        userId INTEGER NOT NULL,
        scheduleId INTEGER NOT NULL,
        PRIMARY KEY (userId, scheduleId),
        FOREIGN KEY (userId) REFERENCES users(id),
        FOREIGN KEY (scheduleId) REFERENCES schedules(id)
    );');

    return $db;
}