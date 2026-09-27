-- ============================================================
-- MODUS CBT — Database Performance Indexes
-- Run once on the database (staging first, then production).
-- Safe: uses IF NOT EXISTS equivalents / IGNORE approach.
-- ============================================================

-- D1: Indexes from fix plan

-- Students: filter by status efficiently
ALTER TABLE students
    ADD INDEX IF NOT EXISTS idx_students_status (status);

-- student_tests: core join + filter patterns
ALTER TABLE student_tests
    ADD INDEX IF NOT EXISTS idx_student_tests_test_status (test_id, status);

ALTER TABLE student_tests
    ADD INDEX IF NOT EXISTS idx_student_tests_test_score (test_id, score DESC, submitted_at ASC);

ALTER TABLE student_tests
    ADD INDEX IF NOT EXISTS idx_student_tests_status_submitted (status, submitted_at DESC);

-- test_questions: ordered question retrieval
ALTER TABLE test_questions
    ADD INDEX IF NOT EXISTS idx_test_questions_test_order (test_id, question_order);

-- answers: join by question
ALTER TABLE answers
    ADD INDEX IF NOT EXISTS idx_answers_question (question_id);

-- exam_sessions: filter by student_test + status
ALTER TABLE exam_sessions
    ADD INDEX IF NOT EXISTS idx_sessions_test_status (student_test_id, status);

-- tests: dashboard + exam_control ORDER BY
ALTER TABLE tests
    ADD INDEX IF NOT EXISTS idx_tests_status_id (status, id DESC);
