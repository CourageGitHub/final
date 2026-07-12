-- ============================================================
-- AI-Powered Past Question Repository & Examination Timetable System
-- Database Schema  |  MySQL 8.0+ / MariaDB 10.4+
-- ============================================================
-- v2: two roles only (admin, student) - lecturer is metadata, not a
-- login. Adds AI interaction logging, feedback, saved answers,
-- notifications, and search logs to support the AI Solver, AI Study
-- Assistant, and AI Analytics Dashboard.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. Departments
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS departments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 2. Users - admin and student only
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('admin','student') NOT NULL DEFAULT 'student',
    department_id   INT UNSIGNED NULL,
    level           VARCHAR(10)  NULL COMMENT 'e.g. 100, 200 - students only',
    identifier      VARCHAR(50)  NULL COMMENT 'matric/reg number',
    phone           VARCHAR(20)  NULL,
    status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_department (department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 3. Courses (lecturer is a plain name - not a login account)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_code     VARCHAR(20)  NOT NULL,
    title           VARCHAR(150) NOT NULL,
    department_id   INT UNSIGNED NOT NULL,
    level           VARCHAR(10)  NOT NULL,
    semester        ENUM('first','second') NOT NULL,
    credit_units    TINYINT UNSIGNED NOT NULL DEFAULT 3,
    lecturer_name   VARCHAR(120) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_courses_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY uq_course_dept (course_code, department_id),
    INDEX idx_courses_level_sem (level, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 4. Rooms / Venues
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rooms (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    capacity    SMALLINT UNSIGNED NOT NULL DEFAULT 40,
    type        ENUM('classroom','hall','lab') NOT NULL DEFAULT 'classroom',
    UNIQUE KEY uq_room_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 5. Weekly time slots (kept for a possible future class timetable;
--    the exam timetable below is the one the frontend brief needs now)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS time_slots (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    UNIQUE KEY uq_slot (day_of_week, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 6. Tags (manual + AI auto-suggested question categorisation)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name    VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 7. Past Questions (the repository core table)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS past_questions (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id           INT UNSIGNED NOT NULL,
    uploaded_by         INT UNSIGNED NOT NULL,
    title               VARCHAR(180) NULL,
    academic_year       VARCHAR(9)  NOT NULL COMMENT 'e.g. 2023/2024',
    semester            ENUM('first','second') NOT NULL,
    exam_type           ENUM('midterm','final','quiz') NOT NULL DEFAULT 'final',
    original_filename   VARCHAR(255) NOT NULL,
    file_path           VARCHAR(255) NOT NULL COMMENT 'stored outside public/ webroot',
    mime_type           VARCHAR(100) NOT NULL,
    file_size           INT UNSIGNED NOT NULL COMMENT 'bytes',
    file_hash           CHAR(64) NOT NULL COMMENT 'sha256 of file, used for duplicate detection',
    extracted_text      MEDIUMTEXT NULL COMMENT 'populated by the OCR pipeline',
    status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    download_count      INT UNSIGNED NOT NULL DEFAULT 0,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pq_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_pq_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pq_status (status),
    INDEX idx_pq_year_sem (academic_year, semester),
    INDEX idx_pq_hash (file_hash),
    FULLTEXT KEY ft_pq_search (title, extracted_text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 8. Individual questions parsed out of a past_questions paper
--    (needed so a student can pick ONE numbered question to send to
--    the AI Solver, per the "Question Panel: Question number" spec)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS question_items (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    past_question_id  INT UNSIGNED NOT NULL,
    question_number    VARCHAR(10) NOT NULL COMMENT 'e.g. 1, 2a, 3',
    content            TEXT NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_qi_paper FOREIGN KEY (past_question_id) REFERENCES past_questions(id) ON DELETE CASCADE,
    FULLTEXT KEY ft_qi_content (content)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 9. Question <-> Tags (many-to-many)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS question_tags (
    past_question_id INT UNSIGNED NOT NULL,
    tag_id            INT UNSIGNED NOT NULL,
    PRIMARY KEY (past_question_id, tag_id),
    CONSTRAINT fk_qt_question FOREIGN KEY (past_question_id) REFERENCES past_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_qt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 10. Class timetable entries (weekly recurring - lower priority)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS timetable_entries (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id         INT UNSIGNED NOT NULL,
    lecturer_name     VARCHAR(120) NOT NULL,
    room_id           INT UNSIGNED NOT NULL,
    time_slot_id      INT UNSIGNED NOT NULL,
    department_id     INT UNSIGNED NOT NULL,
    level             VARCHAR(10) NOT NULL,
    semester          ENUM('first','second') NOT NULL,
    academic_session  VARCHAR(9) NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tt_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_slot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE CASCADE,
    CONSTRAINT fk_tt_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    UNIQUE KEY uq_tt_lecturer_slot (lecturer_name, time_slot_id, academic_session, semester),
    UNIQUE KEY uq_tt_room_slot (room_id, time_slot_id, academic_session, semester),
    UNIQUE KEY uq_tt_class_slot (department_id, level, time_slot_id, academic_session, semester)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 11. Student course registration (needed for exam clash checks)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_course_registration (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id        INT UNSIGNED NOT NULL,
    course_id         INT UNSIGNED NOT NULL,
    academic_session  VARCHAR(9) NOT NULL,
    CONSTRAINT fk_scr_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_scr_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY uq_scr (student_id, course_id, academic_session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 12. Exam timetable entries (date-specific - the one students view)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_timetable_entries (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    course_id         INT UNSIGNED NOT NULL,
    room_id           INT UNSIGNED NOT NULL,
    exam_date         DATE NOT NULL,
    start_time        TIME NOT NULL,
    end_time          TIME NOT NULL,
    academic_session  VARCHAR(9) NOT NULL,
    semester          ENUM('first','second') NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ex_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    CONSTRAINT fk_ex_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE KEY uq_ex_room_time (room_id, exam_date, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 13. Password resets
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 14. Login attempts (brute-force / rate-limiting support)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email         VARCHAR(150) NOT NULL,
    ip_address    VARCHAR(45) NOT NULL,
    success       TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_email_time (email, attempted_at),
    INDEX idx_la_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 15. Audit log
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(100) NOT NULL,
    details     TEXT NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_al_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 16. AI interactions - every Solver / Study Assistant exchange.
--     This one table powers the Solver, the chatbot, AND the AI
--     Analytics Dashboard (most-solved questions, popular topics).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_interactions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    past_question_id  INT UNSIGNED NULL COMMENT 'NULL for free-form chatbot messages',
    interaction_type  ENUM('solve_short','solve_detailed','explain','similar_questions','chat') NOT NULL,
    prompt            TEXT NOT NULL,
    response          MEDIUMTEXT NULL,
    model             VARCHAR(60) NULL COMMENT 'which AI model answered, for auditing',
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ai_question FOREIGN KEY (past_question_id) REFERENCES past_questions(id) ON DELETE SET NULL,
    INDEX idx_ai_question (past_question_id),
    INDEX idx_ai_type (interaction_type),
    INDEX idx_ai_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 17. Answer feedback ("Helpful" / "Needs improvement")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS answer_feedback (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ai_interaction_id INT UNSIGNED NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    rating            ENUM('helpful','needs_improvement') NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fb_interaction FOREIGN KEY (ai_interaction_id) REFERENCES ai_interactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_fb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_fb_once (ai_interaction_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 18. Saved answers ("Save Answer" action)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS saved_answers (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    ai_interaction_id INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_interaction FOREIGN KEY (ai_interaction_id) REFERENCES ai_interactions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_sa (user_id, ai_interaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 19. Notifications (timetable updates, moderation results, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL COMMENT 'NULL = broadcast to everyone',
    title       VARCHAR(150) NOT NULL,
    message     TEXT NOT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 20. Search logs (drives "most searched courses" / "popular topics")
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS search_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    query_text  VARCHAR(255) NULL,
    course_id   INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sl_course FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
    INDEX idx_sl_course (course_id),
    INDEX idx_sl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- 21. Favorites (students bookmarking a past question paper)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS favorites (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    past_question_id  INT UNSIGNED NOT NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_question FOREIGN KEY (past_question_id) REFERENCES past_questions(id) ON DELETE CASCADE,
    UNIQUE KEY uq_fav (user_id, past_question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Reference data to get started (edit to match your institution)
-- ------------------------------------------------------------
INSERT INTO departments (name, code) VALUES
    ('Computer Science', 'CSC'),
    ('Mathematics', 'MTH')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- NOTE: the default admin account is created by database/seed_admin.php,
-- NOT here, so the password is always a real bcrypt hash generated by PHP.
