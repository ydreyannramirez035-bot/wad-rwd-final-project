# ClassSched

The Student Schedule System is a database-driven web application that allows administrators to manage student profiles, Schedules records through a secure, user-friendly interface

---

## Group Members and Roles

| Member Name | Role(s) and Contributions                                        |
|------------|-------------------------------------------------------------------|
| Ydrey Ann Ramirez | UI/UX Design, Frontend Coding (HTML, CSS), Planning         |
| Marwin Mandocdoc | Database Design, Backend Coding (PHP & SQLite, JavaScript), Bug Fixing  CRUD Implementation, Testing & Debugging            |
| JR Cachuela Balmaceda | Form Handling & Validation (JavaScript) |
| Janice V. Agnote | Documentation, Code Review, Additional Feature Development |
| Anthony Daniel Bautista | Test Case Design, Edge Case Handling, QA Tester        |

---

## Features and Technologies Used

### Tech Stack

- **Server-side Language:** PHP  
- **Database:** SQLite  
- **Frontend:** HTML5, CSS (Flexbox), JavaScript  
- **Other:** PHP built-in server or XAMPP/Apache for local hosting

### Core Features

- **User Authentication**
  - User registration with server-side validation  
  - Secure login with password hashing (e.g., `password_hash` / `password_verify`)  
  - Session-based authentication and logout

- **Database-Driven Functionality**
  - SQLite database with at least **two related tables**  
    - Example: `users` table and `students, schedules, or notification`  
    - Proper use of primary keys and foreign keys for relationships

- **CRUD Operations**
  - **Create:** Add new records to `users, students, schedules`  
  - **Read:** Display lists and detailed views of records  
  - **Update:** Edit existing records with validation  
  - **Delete:** Safely remove records (with confirmation prompts)

- **Form Handling and Validation**
  - Server-side validation of all critical fields  
  - Handling of invalid input with user-friendly error messages  
  - Prevention of duplicate or inconsistent records where applicable

- **Usability and Layout**
  - Responsive layout using **CSS Flexbox and Grid**  
  - Clear navigation between pages (e.g., Dashboard, List View, Add/Edit Forms)  
  - Simple, clean interface suitable for academic demonstration

---
## Instructions to Run the Project Locally

### 1. Download or Clone the Repository

Using **Git**:

```bash
git clone [https://github.com/ydreyannramirez035-bot/wad-rwd-final-project.git](https://github.com/ydreyannramirez035-bot/wad-rwd-final-project.git)
cd wad-rwd-final-project
php -S localhost:8000
http://localhost:8000/

Admin Account
Username: admin
Email:    group8@gmail.com
Password: Group82025!

Sample User Account
Username: janice
Email:    agnote@gmail.com
Password: Group82025!
