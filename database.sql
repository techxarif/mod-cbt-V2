CREATE DATABASE IF NOT EXISTS md_cbt
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE md_cbt;


/* =========================================================
   TEACHERS
   ========================================================= */

CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    username VARCHAR(100) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    name VARCHAR(150) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;


/* =========================================================
   STUDENTS
   ========================================================= */

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    /* Student's full name */
    name VARCHAR(150) NOT NULL,

    /* Automatically generated MODUS UID */
    uid VARCHAR(50) NOT NULL UNIQUE,

    /* Student mobile number */
    mobile VARCHAR(20) NOT NULL,

    /* Date of birth */
    date_of_birth DATE NOT NULL,

    /* Password is always stored as a hash */
    password_hash VARCHAR(255) NOT NULL,

    status ENUM(
        'active',
        'inactive'
    ) DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_students_mobile (mobile),

    INDEX idx_students_name (name)

) ENGINE=InnoDB;


/* =========================================================
   TESTS
   ========================================================= */

CREATE TABLE IF NOT EXISTS tests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,

    total_marks DECIMAL(10,2) NOT NULL DEFAULT 0,

    negative_marks DECIMAL(10,2) NOT NULL DEFAULT 0,

    start_time DATETIME NULL,

    end_time DATETIME NULL,

    status ENUM(
        'draft',
        'waiting',
        'active',
        'completed'
    ) DEFAULT 'draft',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tests_status (status)

) ENGINE=InnoDB;


/* =========================================================
   QUESTION BANK
   ========================================================= */

CREATE TABLE IF NOT EXISTS questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    question_text TEXT NOT NULL,

    question_image VARCHAR(500) NULL,

    option_a TEXT NOT NULL,

    option_b TEXT NOT NULL,

    option_c TEXT NOT NULL,

    option_d TEXT NOT NULL,

    correct_option ENUM(
        'A',
        'B',
        'C',
        'D'
    ) NOT NULL,

    marks DECIMAL(10,2) NOT NULL DEFAULT 1,

    negative_marks DECIMAL(10,2) NOT NULL DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;


/* =========================================================
   TEST QUESTIONS
   ========================================================= */

CREATE TABLE IF NOT EXISTS test_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    test_id INT UNSIGNED NOT NULL,

    question_id INT UNSIGNED NOT NULL,

    question_order INT UNSIGNED NOT NULL,

    FOREIGN KEY (test_id)
        REFERENCES tests(id)
        ON DELETE CASCADE,

    FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_test_question (
        test_id,
        question_id
    ),

    INDEX idx_test_questions_test (
        test_id
    ),

    INDEX idx_test_questions_question (
        question_id
    )

) ENGINE=InnoDB;


/* =========================================================
   STUDENT TEST ASSIGNMENTS
   ========================================================= */

CREATE TABLE IF NOT EXISTS student_tests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_id INT UNSIGNED NOT NULL,

    test_id INT UNSIGNED NOT NULL,

    status ENUM(
        'assigned',
        'started',
        'submitted',
        'absent'
    ) DEFAULT 'assigned',

    started_at DATETIME NULL,

    submitted_at DATETIME NULL,

    score DECIMAL(10,2) DEFAULT 0,

    correct_answers INT UNSIGNED DEFAULT 0,

    wrong_answers INT UNSIGNED DEFAULT 0,

    unanswered INT UNSIGNED DEFAULT 0,

    FOREIGN KEY (student_id)
        REFERENCES students(id)
        ON DELETE CASCADE,

    FOREIGN KEY (test_id)
        REFERENCES tests(id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_student_test (
        student_id,
        test_id
    ),

    INDEX idx_student_tests_test (
        test_id
    ),

    INDEX idx_student_tests_student (
        student_id
    )

) ENGINE=InnoDB;


/* =========================================================
   STUDENT ANSWERS
   ========================================================= */

CREATE TABLE IF NOT EXISTS answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_test_id INT UNSIGNED NOT NULL,

    question_id INT UNSIGNED NOT NULL,

    selected_option ENUM(
        'A',
        'B',
        'C',
        'D'
    ) NULL,

    is_correct TINYINT(1) DEFAULT NULL,

    marks_awarded DECIMAL(10,2) DEFAULT 0,

    answered_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_test_id)
        REFERENCES student_tests(id)
        ON DELETE CASCADE,

    FOREIGN KEY (question_id)
        REFERENCES questions(id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_answer (
        student_test_id,
        question_id
    ),

    INDEX idx_answers_student_test (
        student_test_id
    )

) ENGINE=InnoDB;


/* =========================================================
   EXAM SESSIONS
   ========================================================= */

CREATE TABLE IF NOT EXISTS exam_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    student_test_id INT UNSIGNED NOT NULL,

    session_token VARCHAR(255) NOT NULL UNIQUE,

    ip_address VARCHAR(45) NULL,

    started_at DATETIME NULL,

    last_activity DATETIME NULL,

    submitted_at DATETIME NULL,

    status ENUM(
        'active',
        'submitted',
        'disconnected'
    ) DEFAULT 'active',

    FOREIGN KEY (student_test_id)
        REFERENCES student_tests(id)
        ON DELETE CASCADE,

    INDEX idx_sessions_student_test (
        student_test_id
    )

) ENGINE=InnoDB;