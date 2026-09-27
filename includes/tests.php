<?php

// ============================================================
// MODUS CBT — Shared Test Query Functions
// Deduplicates repeated queries across results, pdf, exam_control, questions.
// ============================================================


// ----------------------------------------------------------
// get_test() — fetch a single test by ID
// ----------------------------------------------------------

function get_test(int $id): array|false
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            id, title, status, duration_minutes,
            total_marks, negative_marks,
            start_time, end_time, created_at
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    return $stmt->fetch();
}


// ----------------------------------------------------------
// get_all_tests() — fetch all tests ordered newest first
// ----------------------------------------------------------

function get_all_tests(array $statuses = []): array
{
    global $pdo;

    if ($statuses) {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $pdo->prepare("
            SELECT id, title, status, duration_minutes,
                   total_marks, negative_marks, start_time, end_time, created_at
            FROM tests
            WHERE status IN ($placeholders)
            ORDER BY id DESC
        ");
        $stmt->execute($statuses);
    } else {
        $stmt = $pdo->query("
            SELECT id, title, status, duration_minutes,
                   total_marks, negative_marks, start_time, end_time, created_at
            FROM tests
            ORDER BY id DESC
        ");
    }

    return $stmt->fetchAll();
}


// ----------------------------------------------------------
// get_test_results() — fetch student results for a test
// ----------------------------------------------------------

function get_test_results(int $test_id, int $limit = 0, int $offset = 0): array
{
    global $pdo;

    $sql = "
        SELECT
            st.id AS student_test_id,
            st.status,
            st.score,
            st.correct_answers,
            st.wrong_answers,
            st.unanswered,
            st.started_at,
            st.submitted_at,
            s.uid,
            s.name,
            s.mobile,
            s.date_of_birth
        FROM student_tests st
        INNER JOIN students s ON s.id = st.student_id
        WHERE st.test_id = ?
        ORDER BY st.score DESC, st.submitted_at ASC
    ";

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$test_id, $limit, $offset]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$test_id]);
    }

    return $stmt->fetchAll();
}


// ----------------------------------------------------------
// result_stats() — calculate stats from results array
// (avoids repeated PHP foreach loops in results.php + result_pdf.php)
// ----------------------------------------------------------

function result_stats(array $results): array
{
    $submitted     = 0;
    $total_score   = 0.0;
    $highest_score = null;
    $lowest_score  = null;

    foreach ($results as $r) {
        if ($r['status'] === 'submitted') {
            $submitted++;
            $score = (float) $r['score'];
            $total_score += $score;
            if ($highest_score === null || $score > $highest_score) {
                $highest_score = $score;
            }
            if ($lowest_score === null || $score < $lowest_score) {
                $lowest_score = $score;
            }
        }
    }

    $average = $submitted > 0 ? $total_score / $submitted : 0.0;

    return [
        'total'       => count($results),
        'submitted'   => $submitted,
        'pending'     => count($results) - $submitted,
        'average'     => round($average, 2),
        'highest'     => $highest_score ?? 0,
        'lowest'      => $lowest_score  ?? 0,
    ];
}


// ----------------------------------------------------------
// count_test_results() — paginated count
// ----------------------------------------------------------

function count_test_results(int $test_id): int
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_tests WHERE test_id = ?");
    $stmt->execute([$test_id]);
    return (int) $stmt->fetchColumn();
}


// ----------------------------------------------------------
// get_test_questions() — fetch questions for a test
// ----------------------------------------------------------

function get_test_questions(int $test_id, bool $include_answers = false): array
{
    global $pdo;

    $cols = "
        q.id, q.question_text, q.question_image,
        q.option_a, q.option_b, q.option_c, q.option_d,
        q.marks, q.negative_marks, tq.question_order
    ";

    if ($include_answers) {
        $cols .= ", q.correct_option";
    }

    $stmt = $pdo->prepare("
        SELECT $cols
        FROM test_questions tq
        INNER JOIN questions q ON q.id = tq.question_id
        WHERE tq.test_id = ?
        ORDER BY tq.question_order ASC
    ");
    $stmt->execute([$test_id]);
    return $stmt->fetchAll();
}
