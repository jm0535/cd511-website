# Moodle Course Prototype Scripts

These PHP CLI scripts were used to automate the creation of a complete Moodle course for CD 511 – Learning Management System and Flipped Classroom.

## Files

| File | Purpose |
|---|---|
| `config.php` | Moodle configuration file with database credentials |
| `setup_moodle_course.php` | Creates users, course, sections, resources, forum, and quiz |
| `setup_moodle_course2.php` | Corrected version with proper module library includes |
| `final_fix2.php` | Adds quiz questions (MCQ + True/False) and forum announcement |
| `fix_quiz_moodle5.php` | Fixes question bank context links for Moodle 5.0 |
| `fix_quiz_final.php` | Final quiz fix – rebuilds slot references for Moodle 5 |

## Usage

1. Install Moodle locally (requires PHP 8.x, MariaDB/MySQL, Apache)
2. Copy `config.php` to your Moodle root directory
3. Run the setup scripts from the command line as the web server user:
   ```bash
   sudo -u apache php setup_moodle_course2.php
   sudo -u apache php final_fix2.php
   ```

## Course Details

- **Full name:** Introduction to Student-Centred Teaching
- **Short name:** CD511-Course
- **Students:** student1 – student5
- **Admin:** admin / AdminPass123!
- **Student password:** StudentPass123!
- **Quiz:** 10 questions (5 MCQ, 5 True/False)
- **Resources:** URL, Page, File
- **Forum:** Announcements with welcome post

## Requirements

- Moodle 4.x or 5.x
- PHP 8.1+
- MariaDB or MySQL
- Apache with mod_rewrite
