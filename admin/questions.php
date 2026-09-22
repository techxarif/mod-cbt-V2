<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';

$error = '';
$message = '';

/*
|--------------------------------------------------------------------------
| DELETE QUESTION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_question'])) {

    $question_id = (int)($_POST['question_id'] ?? 0);
    $test_id = (int)($_POST['test_id'] ?? 0);

    if ($question_id > 0) {

        try {

            $stmt = $pdo->prepare("
                SELECT question_image
                FROM questions
                WHERE id = ?
            ");

            $stmt->execute([$question_id]);

            $question = $stmt->fetch();

            $stmt = $pdo->prepare("
                DELETE FROM test_questions
                WHERE question_id = ?
            ");

            $stmt->execute([$question_id]);

            $stmt = $pdo->prepare("
                DELETE FROM questions
                WHERE id = ?
            ");

            $stmt->execute([$question_id]);

            if (
                $question &&
                !empty($question['question_image'])
            ) {

                $image_path =
                    __DIR__ . '/../' .
                    $question['question_image'];

                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $message = 'Question deleted successfully.';

        } catch (PDOException $e) {

            $error = 'Database error: ' . $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD TESTS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            t.id,
            t.title,
            t.duration_minutes,
            COUNT(tq.id) AS question_count
        FROM tests t
        LEFT JOIN test_questions tq
            ON tq.test_id = t.id
        GROUP BY
            t.id,
            t.title,
            t.duration_minutes
        ORDER BY t.id DESC
    ");

    $tests = $stmt->fetchAll();

} catch (PDOException $e) {

    $tests = [];

    $error = 'Could not load tests: ' . $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| SELECTED TEST
|--------------------------------------------------------------------------
*/

$selected_test_id = (int)($_GET['test_id'] ?? 0);

$selected_test = null;
$questions = [];


/*
|--------------------------------------------------------------------------
| LOAD SELECTED TEST
|--------------------------------------------------------------------------
*/

if ($selected_test_id > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                id,
                title,
                duration_minutes,
                total_marks,
                negative_marks,
                status
            FROM tests
            WHERE id = ?
        ");

        $stmt->execute([
            $selected_test_id
        ]);

        $selected_test = $stmt->fetch();


        if ($selected_test) {

            $stmt = $pdo->prepare("
                SELECT
                    q.id,
                    q.question_text,
                    q.question_image,
                    q.option_a,
                    q.option_b,
                    q.option_c,
                    q.option_d,
                    q.correct_option,
                    q.marks,
                    q.negative_marks,
                    tq.question_order
                FROM test_questions tq
                INNER JOIN questions q
                    ON q.id = tq.question_id
                WHERE tq.test_id = ?
                ORDER BY tq.question_order ASC, q.id ASC
            ");

            $stmt->execute([
                $selected_test_id
            ]);

            $questions = $stmt->fetchAll();
        }

    } catch (PDOException $e) {

        $error = 'Could not load questions: ' . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Question Bank — MODUS CBT</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<style>

/* =========================================================
   MODUS QUESTION BANK
========================================================= */

:root {
    --bg: #050812;
    --panel: #0c1220;
    --panel-2: #101827;
    --border: rgba(148,163,184,.13);

    --text: #f1f5f9;
    --muted: #94a3b8;

    --blue: #60a5fa;
    --blue-strong: #2563eb;

    --purple: #a78bfa;
    --green: #4ade80;
    --red: #fb7185;
}


/* =========================================================
   PAGE
========================================================= */

body {

    margin: 0;

    background:
        radial-gradient(
            circle at 10% 5%,
            rgba(37,99,235,.12),
            transparent 30%
        ),
        radial-gradient(
            circle at 90% 20%,
            rgba(124,58,237,.10),
            transparent 28%
        ),
        radial-gradient(
            circle at 50% 100%,
            rgba(37,99,235,.06),
            transparent 35%
        ),
        #050812 !important;

    color: var(--text);

    min-height: 100vh;
}


/* subtle page grid */

body::before {

    content: "";

    position: fixed;

    inset: 0;

    pointer-events: none;

    opacity: .20;

    background-image:
        linear-gradient(
            rgba(255,255,255,.018) 1px,
            transparent 1px
        ),
        linear-gradient(
            90deg,
            rgba(255,255,255,.018) 1px,
            transparent 1px
        );

    background-size: 45px 45px;

    mask-image: linear-gradient(
        to bottom,
        black,
        transparent 80%
    );
}


/* =========================================================
   CONTAINER
========================================================= */

.container {

    width: min(1180px, calc(100% - 32px));

    margin: 0 auto;

    padding: 42px 0 80px;

    position: relative;

    z-index: 1;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 25px;

    margin-bottom: 28px;
}

.header h1 {

    margin: 0;

    font-size: 32px;

    font-weight: 750;

    letter-spacing: -.7px;

    color: #f8fafc;
}

.header p {

    margin: 8px 0 0;

    color: var(--muted);

    font-size: 14px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    min-height: 42px;

    padding: 0 16px;

    border-radius: 11px;

    border: 1px solid transparent;

    font-size: 14px;

    font-weight: 650;

    text-decoration: none;

    cursor: pointer;

    transition:
        transform .18s ease,
        background .18s ease,
        border-color .18s ease,
        box-shadow .18s ease;

    box-sizing: border-box;
}

.btn:hover {

    transform: translateY(-1px);
}

.btn-primary {

    color: white;

    background:
        linear-gradient(
            135deg,
            #2563eb,
            #4f46e5
        );

    border-color: rgba(96,165,250,.35);

    box-shadow:
        0 10px 30px rgba(37,99,235,.20);
}

.btn-primary:hover {

    box-shadow:
        0 14px 35px rgba(37,99,235,.30);
}

.btn-secondary {

    background: #172033;

    color: #dbeafe;

    border-color: rgba(96,165,250,.18);
}

.btn-secondary:hover {

    background: #1d2940;

    border-color: rgba(96,165,250,.35);
}

.btn-edit {

    background: #141d2d;

    color: #cbd5e1;

    border-color: rgba(148,163,184,.15);
}

.btn-edit:hover {

    background: #1c283b;

    color: white;
}

.btn-danger {

    background: rgba(127,29,29,.18);

    color: #fda4af;

    border-color: rgba(251,113,133,.20);
}

.btn-danger:hover {

    background: rgba(127,29,29,.30);

    border-color: rgba(251,113,133,.35);
}


/* =========================================================
   GENERIC PANELS
========================================================= */

.test-selector,
.test-info,
.question-card,
.empty {

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.97),
            rgba(7,12,24,.98)
        );

    border: 1px solid var(--border);

    box-shadow:
        0 25px 70px rgba(0,0,0,.35),
        inset 0 1px 0 rgba(255,255,255,.025);

    backdrop-filter: blur(16px);

    -webkit-backdrop-filter: blur(16px);
}


/* =========================================================
   TEST SELECTOR
========================================================= */

.test-selector {

    padding: 22px;

    border-radius: 20px;

    margin-bottom: 22px;

    position: relative;

    overflow: hidden;
}

.test-selector::before {

    content: "";

    position: absolute;

    width: 280px;

    height: 180px;

    right: -100px;

    top: -100px;

    background: rgba(37,99,235,.10);

    filter: blur(55px);

    pointer-events: none;
}

.test-selector label {

    display: block;

    margin-bottom: 9px;

    font-size: 13px;

    font-weight: 700;

    color: #cbd5e1;

    text-transform: uppercase;

    letter-spacing: .7px;
}

.test-selector-row {

    display: flex;

    gap: 10px;
}

.test-selector select {

    flex: 1;

    min-width: 0;

    height: 46px;

    padding: 0 14px;

    background: #080f1d;

    color: #f8fafc;

    border: 1px solid rgba(148,163,184,.16);

    border-radius: 11px;

    outline: none;

    font-size: 14px;

    color-scheme: dark;
}

.test-selector select:focus {

    border-color: rgba(96,165,250,.55);

    box-shadow:
        0 0 0 3px rgba(37,99,235,.10);
}


/* =========================================================
   ALERTS
========================================================= */

.alert {

    padding: 14px 17px;

    border-radius: 13px;

    margin-bottom: 20px;

    font-size: 14px;
}

.success {

    background: rgba(22,101,52,.20);

    border: 1px solid rgba(74,222,128,.22);

    color: #86efac;
}

.error {

    background: rgba(127,29,29,.20);

    border: 1px solid rgba(251,113,133,.22);

    color: #fda4af;
}


/* =========================================================
   TEST INFORMATION
========================================================= */

.test-info {

    padding: 24px;

    border-radius: 20px;

    margin-bottom: 22px;

    position: relative;

    overflow: hidden;
}

.test-info::after {

    content: "";

    position: absolute;

    right: -80px;

    top: -100px;

    width: 240px;

    height: 240px;

    background: rgba(124,58,237,.08);

    filter: blur(55px);

    pointer-events: none;
}

.test-info h2 {

    position: relative;

    z-index: 1;

    margin: 0 0 16px;

    font-size: 22px;

    color: #f8fafc;
}

.test-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 9px;

    position: relative;

    z-index: 1;
}

.meta {

    display: inline-flex;

    align-items: center;

    padding: 8px 11px;

    border-radius: 9px;

    background: rgba(15,23,42,.90);

    border: 1px solid rgba(148,163,184,.11);

    color: #aebdce;

    font-size: 12px;

    font-weight: 600;
}


/* =========================================================
   QUESTION CARD
========================================================= */

.question-card {

    border-radius: 20px;

    padding: 23px;

    margin-bottom: 15px;

    transition:
        transform .20s ease,
        border-color .20s ease,
        box-shadow .20s ease;
}

.question-card:hover {

    transform: translateY(-2px);

    border-color: rgba(96,165,250,.20);

    box-shadow:
        0 30px 75px rgba(0,0,0,.40),
        0 0 0 1px rgba(96,165,250,.03),
        inset 0 1px 0 rgba(255,255,255,.03);
}


/* =========================================================
   QUESTION HEADER
========================================================= */

.question-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

    margin-bottom: 15px;
}

.question-number {

    display: inline-flex;

    align-items: center;

    padding: 5px 9px;

    border-radius: 7px;

    background: rgba(37,99,235,.10);

    border: 1px solid rgba(96,165,250,.15);

    color: #93c5fd;

    font-size: 11px;

    font-weight: 750;

    letter-spacing: .5px;

    text-transform: uppercase;
}

.question-text {

    margin-top: 12px;

    font-size: 16px;

    line-height: 1.65;

    color: #f1f5f9;

    white-space: pre-wrap;
}


/* =========================================================
   QUESTION IMAGE
========================================================= */

.question-image {

    display: block;

    max-width: 100%;

    max-height: 330px;

    margin: 18px 0;

    padding: 5px;

    object-fit: contain;

    border-radius: 13px;

    background: #080d18;

    border: 1px solid rgba(148,163,184,.15);

    box-shadow:
        0 15px 35px rgba(0,0,0,.25);
}


/* =========================================================
   OPTIONS
========================================================= */

.options {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

    margin-top: 20px;
}

.option {

    min-width: 0;

    padding: 14px 15px;

    border-radius: 12px;

    background:
        linear-gradient(
            145deg,
            rgba(15,23,42,.92),
            rgba(9,14,25,.96)
        );

    border: 1px solid rgba(148,163,184,.11);

    color: #cbd5e1;

    line-height: 1.5;

    font-size: 14px;

    transition:
        background .18s ease,
        border-color .18s ease;
}

.option:hover {

    background: #121d2f;

    border-color: rgba(96,165,250,.20);
}

.option.correct {

    background:
        linear-gradient(
            145deg,
            rgba(20,83,45,.35),
            rgba(10,40,25,.55)
        );

    border-color: rgba(74,222,128,.28);

    color: #dcfce7;

    box-shadow:
        inset 0 0 25px rgba(74,222,128,.025);
}

.option-label {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    width: 25px;

    height: 25px;

    margin-right: 8px;

    border-radius: 7px;

    background: rgba(148,163,184,.08);

    color: #cbd5e1;

    font-size: 12px;

    font-weight: 750;
}

.option.correct .option-label {

    background: rgba(74,222,128,.13);

    color: #86efac;
}


/* =========================================================
   QUESTION FOOTER
========================================================= */

.question-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    margin-top: 20px;

    padding-top: 17px;

    border-top: 1px solid rgba(148,163,184,.09);
}

.marks {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;
}

.mark {

    display: inline-flex;

    align-items: center;

    padding: 6px 9px;

    border-radius: 7px;

    background: rgba(15,23,42,.90);

    border: 1px solid rgba(148,163,184,.10);

    color: #94a3b8;

    font-size: 11px;

    font-weight: 650;
}

.question-actions {

    display: flex;

    align-items: center;

    gap: 8px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty {

    text-align: center;

    padding: 65px 25px;

    border-radius: 20px;

    color: #94a3b8;
}

.empty h3 {

    margin: 0 0 8px;

    color: #e2e8f0;

    font-size: 19px;
}

.empty p {

    margin: 0 0 20px;

    color: #64748b;

    font-size: 14px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 760px) {

    .container {

        width: min(100% - 22px, 1180px);

        padding-top: 25px;
    }

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .header h1 {

        font-size: 27px;
    }

    .test-selector-row {

        flex-direction: column;
    }

    .test-selector .btn {

        width: 100%;
    }

    .options {

        grid-template-columns: 1fr;
    }

    .question-footer {

        flex-direction: column;

        align-items: flex-start;
    }

    .question-actions {

        width: 100%;
    }

    .question-actions .btn {

        flex: 1;
    }

}

@media (max-width: 430px) {

    .question-card {

        padding: 17px;
    }

    .test-selector,
    .test-info {

        padding: 17px;
    }

    .question-text {

        font-size: 15px;
    }

}


/* =========================================================
   REDUCED MOTION
========================================================= */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation: none !important;

        transition: none !important;
    }

}

</style>

</head>


<body>

<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">

        <div>

            <h1>Question Bank</h1>

            <p>
                Manage questions test-wise inside MODUS CBT.
            </p>

        </div>


        <?php if ($selected_test_id > 0): ?>

            <a
                href="add_question.php?test_id=<?= $selected_test_id ?>"
                class="btn btn-primary"
            >
                + Add Question
            </a>

        <?php endif; ?>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($message): ?>

        <div class="alert success">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- =====================================================
         TEST SELECTOR
    ====================================================== -->

    <div class="test-selector">

        <form method="GET">

            <label>
                Select Test
            </label>

            <div class="test-selector-row">

                <select
                    name="test_id"
                    required
                >

                    <option value="">
                        -- Select a Test --
                    </option>

                    <?php foreach ($tests as $test): ?>

                        <option
                            value="<?= (int)$test['id'] ?>"
                            <?= $selected_test_id === (int)$test['id']
                                ? 'selected'
                                : '' ?>
                        >

                            <?= htmlspecialchars($test['title']) ?>

                            —
                            <?= (int)$test['question_count'] ?>
                            questions

                        </option>

                    <?php endforeach; ?>

                </select>


                <button
                    type="submit"
                    class="btn btn-secondary"
                >
                    View Questions
                </button>

            </div>

        </form>

    </div>


    <?php if ($selected_test): ?>


        <!-- =================================================
             TEST INFORMATION
        ================================================== -->

        <div class="test-info">

            <h2>
                <?= htmlspecialchars($selected_test['title']) ?>
            </h2>

            <div class="test-meta">

                <span class="meta">
                    <?= count($questions) ?> Questions
                </span>

                <span class="meta">
                    <?= (int)$selected_test['duration_minutes'] ?> Minutes
                </span>

                <span class="meta">
                    Status:
                    <?= htmlspecialchars(
                        ucfirst($selected_test['status'])
                    ) ?>
                </span>

                <span class="meta">
                    +<?= htmlspecialchars($selected_test['total_marks']) ?>
                    Total Marks
                </span>

            </div>

        </div>


        <!-- =================================================
             EMPTY
        ================================================== -->

        <?php if (empty($questions)): ?>

            <div class="empty">

                <h3>
                    No questions in this test yet
                </h3>

                <p>
                    Start building the question bank for this test.
                </p>

                <a
                    href="add_question.php?test_id=<?= $selected_test_id ?>"
                    class="btn btn-primary"
                >
                    + Add First Question
                </a>

            </div>


        <?php else: ?>


            <!-- =================================================
                 QUESTIONS
            ================================================== -->

            <?php foreach ($questions as $question): ?>

                <div class="question-card">


                    <div class="question-header">

                        <div>

                            <div class="question-number">

                                Question
                                <?= (int)$question['question_order'] ?>

                            </div>


                            <div class="question-text">

                                <?= htmlspecialchars(
                                    $question['question_text']
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- QUESTION IMAGE -->

                    <?php if (!empty($question['question_image'])): ?>

                        <img
                            src="../<?= htmlspecialchars(
                                $question['question_image']
                            ) ?>"
                            class="question-image"
                            alt="Question image"
                        >

                    <?php endif; ?>


                    <!-- OPTIONS -->

                    <div class="options">


                        <div
                            class="option <?= $question['correct_option'] === 'A'
                                ? 'correct'
                                : '' ?>"
                        >

                            <span class="option-label">
                                A
                            </span>

                            <?= htmlspecialchars(
                                $question['option_a']
                            ) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'B'
                                ? 'correct'
                                : '' ?>"
                        >

                            <span class="option-label">
                                B
                            </span>

                            <?= htmlspecialchars(
                                $question['option_b']
                            ) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'C'
                                ? 'correct'
                                : '' ?>"
                        >

                            <span class="option-label">
                                C
                            </span>

                            <?= htmlspecialchars(
                                $question['option_c']
                            ) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'D'
                                ? 'correct'
                                : '' ?>"
                        >

                            <span class="option-label">
                                D
                            </span>

                            <?= htmlspecialchars(
                                $question['option_d']
                            ) ?>

                        </div>


                    </div>


                    <!-- QUESTION FOOTER -->

                    <div class="question-footer">


                        <div class="marks">

                            <span class="mark">
                                +<?= htmlspecialchars(
                                    $question['marks']
                                ) ?>
                            </span>

                            <span class="mark">
                                -<?= htmlspecialchars(
                                    $question['negative_marks']
                                ) ?>
                            </span>

                            <span class="mark">
                                Correct:
                                <?= htmlspecialchars(
                                    $question['correct_option']
                                ) ?>
                            </span>

                        </div>


                        <div class="question-actions">


                            <a
                                href="edit_question.php?id=<?= (int)$question['id'] ?>&test_id=<?= $selected_test_id ?>"
                                class="btn btn-edit"
                            >
                                Edit
                            </a>


                            <form
                                method="POST"
                                onsubmit="return confirm(
                                    'Delete this question from the test?'
                                );"
                            >

                                <input
                                    type="hidden"
                                    name="question_id"
                                    value="<?= (int)$question['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="test_id"
                                    value="<?= $selected_test_id ?>"
                                >

                                <button
                                    type="submit"
                                    name="delete_question"
                                    class="btn btn-danger"
                                >
                                    Delete
                                </button>

                            </form>


                        </div>


                    </div>


                </div>

            <?php endforeach; ?>


        <?php endif; ?>


    <?php else: ?>


        <!-- =================================================
             NO TEST SELECTED
        ================================================== -->

        <div class="empty">

            <h3>
                Select a test
            </h3>

            <p>
                Choose a test above to view and manage its questions.
            </p>

        </div>

    <?php endif; ?>


</div>

</body>

</html>