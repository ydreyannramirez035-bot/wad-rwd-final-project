I’ll walk through each of those PHP files, see what they actually do, then map them to your 1–12 guide questions (problem, solution, features, tech, etc.) so you can use it in [knowing.md](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/knowing.md:0:0-0:0) and your presentation.

First I need to inspect the code of all the listed PHP files. I’ll load them now.

Here’s a clear explanation of your whole PHP system using the 1–12 guide, based on the actual files you tagged.

---

## 1. App name, team, roles, target users, problem

- **App Name:** ClassSched  
- **Team & Roles:**  
  - You can list your members here (e.g., UI, backend, DB, testing).  
- **Target Users:**  
  - School admins / program chairs (BSIS / ACT).  
  - College students (BSIS / ACT).  
- **Main Problem:**  
  - Class schedules and student info are usually scattered in Excel / paper, hard to update, no clear dashboard per role, and no automatic way to notify students when schedules or profile details change.

---

## 2. Problem validation and failed existing solutions

- **Validation:**  
  - Observed that schedules and student data are handled manually.  
  - Changing a schedule doesn’t automatically inform students.  
  - Admins don’t see quick stats (student counts, teachers, rooms, etc.).  
- **Existing approaches & issues:**  
  - **Spreadsheets / printed schedules:** slow to update, error‑prone, no notifications.  
  - **Generic tools (e.g., chat groups, plain portals):** not tied to a structured DB; no per-course filtering, no system‑generated notifications.

---

## 3. Core solution and unique value

- **Core Solution:**  
  - A web app where:  
    - Admins manage users, students, and class schedules.  
    - Students log in to see their personalized schedule, profile, and notifications.  
- **Unique Value:**  
  - Tight link between **schedules**, **students**, and **notifications** via SQLite triggers in [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0).  
  - Admin dashboard ([admin_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_dashboard.php:0:0-0:0)) shows **live stats** (students, classes, teachers, rooms, last activity).  
  - Student dashboard ([student_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_dashboard.php:0:0-0:0)) and schedule ([student_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_schedule.php:0:0-0:0)) give a **clean, day‑based view** with filtering.  
  - Notifications ([notification.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/notification.php:0:0-0:0)) let **admins track profile changes** and **students get schedule/bio/phone updates** automatically.

---

## 4. Tech stack and system architecture

- **Frontend:**  
  - PHP templates + HTML, CSS, Bootstrap 5, Font Awesome, custom JS (`js/validation.js`, `js/notification.js`, `js/day.js`, etc.).  
- **Backend:**  
  - PHP (session-based auth, routing via separate PHP files).  
- **Database:**  
  - SQLite (`users.db`) created and managed in [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0).  
- **Architecture / Flow:**  
  - [index.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/index.php:0:0-0:0) → login/register → sets `$_SESSION['user']`.  
  - If `username == 'admin'` → [admin_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_dashboard.php:0:0-0:0).  
  - Otherwise → [student_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_dashboard.php:0:0-0:0).  
  - Shared DB access via [get_db()](cci:1://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:1:0-229:1) in [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0).  
  - Notifications logic centralized in [notification.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/notification.php:0:0-0:0).  
  - Tables: `users`, `students`, `courses`, `teachers`, `subjects`, `schedules`, `notifications`, `profiles_students`, `admin_system_log`, plus triggers.

---

## 5. Features mapped to user pain

- **Feature: Account login & registration ([index.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/index.php:0:0-0:0))**  
  - Solves: unsecured / shared logins.  
  - Strong password checks and role‑based redirect (admin vs student).

- **Feature: Admin dashboard overview ([admin_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_dashboard.php:0:0-0:0))**  
  - Stats: total students, classes, teachers, rooms, last system activity.  
  - Recent schedules and student list preview, course filter (All / BSIS / ACT).  
  - Solves: admins guessing counts and manually compiling reports.

- **Feature: Admin schedule management ([admin_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_schedule.php:0:0-0:0))**  
  - CRUD for schedules, with subject/teacher/course selection.  
  - Ajax filtering: by course, search, sort (time/day).  
  - Solves: messy timetable editing and duplicated schedules.

- **Feature: Admin student management ([admin_student_manage.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_student_manage.php:0:0-0:0))**  
  - CRUD for students, filter by course, search, sort by name/ID.  
  - Solves: paper-based or Excel-based student lists with poor search.

- **Feature: Student dashboard ([student_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_dashboard.php:0:0-0:0))**  
  - Personalized schedule cards: by selected day or whole week.  
  - Quick view of number of classes for that day/period.  
  - Solves: students constantly asking/scrolling for their schedule.

- **Feature: Full student schedule page ([student_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_schedule.php:0:0-0:0))**  
  - Detailed, filterable view similar to dashboard but focused on schedule.  

- **Feature: Student profile & bio ([student_profile.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_profile.php:0:0-0:0))**  
  - Students can update phone and short bio; data joins with `courses`.  
  - Solves: outdated contact records and no simple self‑service bio.

- **Feature: Notifications system ([notification.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/notification.php:0:0-0:0) + DB triggers in [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0))**  
  - Admin sees when students update bio/phone.  
  - Students see “New Class” and “Schedule Update” notifications per course.  
  - Solves: no automatic communication when schedules / profile change.

- **Feature: Secure logout ([logout.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/logout.php:0:0-0:0))**  
  - Clears session and prevents back‑navigation into sensitive pages.

---

## 6. Live demo of full workflow

1. **Visitor opens [index.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/index.php:0:0-0:0).**  
   - Sees landing page with “Get started”, login and register modals.  
2. **New student registers.**  
   - Validation checks (length, uppercase, lowercase, number).  
   - On success, user is saved in `users` and is redirected to login.  
3. **Student logs in.**  
   - Correct credentials → [student_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_dashboard.php:0:0-0:0).  
   - Sees schedule cards based on their course & day.  
   - Can open [student_profile.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_profile.php:0:0-0:0) to edit phone and bio.  
4. **Admin logs in (username `admin`).**  
   - Redirected to [admin_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_dashboard.php:0:0-0:0) with stats and recent data.  
   - From there:  
     - Manage schedules in [admin_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_schedule.php:0:0-0:0).  
     - Manage students in [admin_student_manage.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_student_manage.php:0:0-0:0).  
5. **Changes trigger notifications.**  
   - Updating schedules or student bio/phone fires SQLite triggers in [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0) → new rows in `notifications`.  
   - [notification.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/notification.php:0:0-0:0) shows badges and lists for admins and students.  
6. **Logout.**  
   - Any user clicks logout → [logout.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/logout.php:0:0-0:0) → session destroyed → back to [index.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/index.php:0:0-0:0).

---

## 7. Hardest technical challenge and trade-offs

- **Main challenges:**  
  - Designing a normalized SQLite schema that supports roles, courses, students, schedules, notifications, and logs.  
  - Implementing **triggers** to auto‑log system activity and create notifications correctly.  
  - Managing complex schedule queries and filters with joins and dynamic conditions (day, course, search, sort).  
- **Trade-offs:**  
  - Kept everything in plain PHP + SQLite instead of a heavy framework.  
  - Fewer advanced access-control layers, but simpler to understand for an academic project.  

---

## 8. Security, data handling, known risks

- **Security measures:**  
  - Passwords stored as `password_hash` in `users`.  
  - Session-based auth; protected pages check `$_SESSION['user']`.  
  - Some prepared statements (e.g., login, profile update, many schedule queries).  
  - Basic cache-control headers on protected pages to prevent back-button issues.  
- **Data handling:**  
  - Centralized DB creation and seeding in [get_db()](cci:1://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:1:0-229:1) ([db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0)).  
  - Foreign keys enabled and enforced.  
  - Notifications and logs written by DB triggers, not only by PHP.  
- **Known risks / limitations:**  
  - Some queries still interpolate values directly (potential injection if not carefully sanitized).  
  - No HTTPS enforcement (depends on deployment).  
  - No full role‑based permission system beyond basic checks (admin vs student).

---

## 9. Testing, bugs, limitations

- **Testing:**  
  - Manual testing of login/register, dashboards, schedule CRUD, filters, and notifications.  
- **Limitations / possible issues:**  
  - No automated test suite.  
  - UI optimized mainly for desktop; mobile responsiveness depends on Bootstrap defaults.  
  - Schedules assume fixed textual days (Monday–Sunday) and may not cover special cases (holidays, overlapping checks).

---

## 10. Future improvements

- **Potential next steps:**  
  - Add pagination to large student/schedule lists.  
  - More robust input validation and universal use of prepared statements.  
  - More advanced role system (e.g., teacher accounts).  
  - Export schedules / student lists to PDF/Excel.  
  - More detailed analytics on dashboard (per year level, per room utilization).  
  - Improve mobile layout and add dark mode.

---

## 11. Actual team contributions

You can map files / features to members, for example:

- **Member A:** DB design + [db.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/db.php:0:0-0:0) (tables, triggers, seeding).  
- **Member B:** Admin side – [admin_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_dashboard.php:0:0-0:0), [admin_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_schedule.php:0:0-0:0), [admin_student_manage.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/admin_student_manage.php:0:0-0:0).  
- **Member C:** Student side – [student_dashboard.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_dashboard.php:0:0-0:0), [student_schedule.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_schedule.php:0:0-0:0), [student_profile.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/student_profile.php:0:0-0:0).  
- **Member D:** Auth + landing – [index.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/index.php:0:0-0:0), [logout.php](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/src/logout.php:0:0-0:0), password validation JS, styling.

Adjust to reflect your real work.

---

## 12. Final value and skill proof

- **Final value:**  
  - ClassSched turns disorganized, static schedules and student records into a **centralized, role‑based system** with live dashboards and automatic notifications.  
- **Skills demonstrated:**  
  - PHP + SQLite3 CRUD and joins.  
  - DB design with foreign keys and triggers.  
  - Session-based authentication and password hashing.  
  - UI/UX using Bootstrap, modals, AJAX for dynamic filtering.  
  - Handling both admin and student workflows in one coherent system.

---

If you want, I can now turn this into a polished [knowing.md](cci:7://file:///c:/Users/Marwin/Documents/Marwin%27s%20Files/2ND%20YEAR%201ST%20SEMESTER/WEB%20DEVELOPMENT%201/finals/project/wad-rwd-final-project/knowing.md:0:0-0:0) section (bullet or paragraph style) ready to paste directly.
