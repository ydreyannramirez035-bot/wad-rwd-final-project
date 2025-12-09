# [PROJECT NAME: e.g., Student Record System]

[INSERT A 1–2 SENTENCE DESCRIPTION OF WHAT THE APP DOES.  
Example: “The Student Record System is a database-driven web application that allows administrators to manage student profiles, course enrollments, and academic records through a secure, user-friendly interface.”]

---

## Group Members and Roles

| Member Name | Role(s) and Contributions                                        |
|------------|-------------------------------------------------------------------|
| Ydrey Ann Ramirez | UI/UX Design, Frontend Coding (HTML, CSS), Planning         |
| Marwin Mandocdoc | Database Design, Backend Coding (PHP & SQLite, JavaScript), Bug Fixing  CRUD Implementation, Testing & Debugging            |
| JR Cachuela Balmaceda | Form Handling & Validation (JavaScript) |
| Janice V. Agnote | Documentation, Code Review, Additional Feature Development |
| Anthony Daniel Bautista | Test Case Design, Edge Case Handling, QA Tester        |

> Edit the table above to match your actual group members and their specific responsibilities.

---

## Features and Technologies Used

### Tech Stack

- **Server-side Language:** PHP  
- **Database:** SQLite  
- **Frontend:** HTML5, CSS (Flexbox/Grid), JavaScript  
- **Other:** PHP built-in server or XAMPP/Apache for local hosting

### Core Features

- **User Authentication**
  - User registration with server-side validation  
  - Secure login with password hashing (e.g., `password_hash` / `password_verify`)  
  - Session-based authentication and logout

- **Database-Driven Functionality**
  - SQLite database with at least **two related tables**  
    - Example: `users` table and `[PRIMARY ENTITY TABLE, e.g., students, products, or tasks]`  
    - Proper use of primary keys and foreign keys for relationships

- **CRUD Operations**
  - **Create:** Add new records to `[PRIMARY ENTITY TABLE]`  
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

> Replace bracketed placeholders (e.g., `[PRIMARY ENTITY TABLE]`) with your actual table/entity names.

---

## Database Structure

The application uses an **SQLite** database file (e.g., `users.db`) located within the project directory.

Example structure (customize to match your actual schema):

- **Table 1: `users`**
  - `id` (INTEGER, PRIMARY KEY, AUTOINCREMENT)  
  - `username` (TEXT, UNIQUE, NOT NULL)  
  - `email` (TEXT, UNIQUE, NOT NULL)  
  - `password_hash` (TEXT, NOT NULL)  
  - `created_at` (TEXT / DATETIME)

- **Table 2: `[PRIMARY ENTITY TABLE, e.g., students]`**
  - `id` (INTEGER, PRIMARY KEY, AUTOINCREMENT)  
  - `[field1]` (e.g., `name` – TEXT, NOT NULL)  
  - `[field2]` (e.g., `course` / `category` – TEXT)  
  - `[field3]` (e.g., `status` / `grade` – TEXT/INTEGER)  
  - `user_id` (INTEGER, FOREIGN KEY REFERENCES `users(id)`)  

Additional related tables can be added as needed (e.g., `courses`, `transactions`, `logs`).

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
