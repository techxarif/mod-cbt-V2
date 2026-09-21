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

            /*
             * Get image before deleting.
             */
            $stmt = $pdo->prepare("
                SELECT question_image
                FROM questions
                WHERE id = ?
            ");

            $stmt->execute([$question_id]);

            $question = $stmt->fetch();

            /*
             * Remove test-question relationship.
             */
            $stmt = $pdo->prepare("
                DELETE FROM test_questions
                WHERE question_id = ?
            ");

            $stmt->execute([$question_id]);

            /*
             * Delete question.
             */
            $stmt = $pdo->prepare("
                DELETE FROM questions
                WHERE id = ?
            ");

            $stmt->execute([$question_id]);

            /*
             * Delete image.
             */
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

            $error =
                'Database error: ' .
                $e->getMessage();
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

    $error =
        'Could not load tests: ' .
        $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| SELECTED TEST
|--------------------------------------------------------------------------
*/

$selected_test_id =
    (int)($_GET['test_id'] ?? 0);

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

        $selected_test =
            $stmt->fetch();


        /*
         * Load ONLY questions belonging
         * to this test.
         */

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

            $questions =
                $stmt->fetchAll();
        }

    } catch (PDOException $e) {

        $error =
            'Could not load questions: ' .
            $e->getMessage();
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

<title>Questions - MODUS CBT</title>

<link
    rel="stylesheet"
    href="../assets/css/style.css"
>

<style>

body {
    background: #080808;
    color: #fff;
}

.container {

    max-width: 1200px;

    margin: 30px auto;

    padding: 0 20px 60px;
}

.header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

    gap: 20px;
}

.header h1 {

    margin: 0 0 5px;
}

.header p {

    margin: 0;

    color: #888;
}

.btn {

    display: inline-block;

    padding: 11px 17px;

    border-radius: 8px;

    border: none;

    text-decoration: none;

    font-weight: 600;

    cursor: pointer;
}

.btn-primary {

    background: #fff;

    color: #000;
}

.btn-secondary {

    background: #222;

    color: #fff;

    border: 1px solid #333;
}

.btn-danger {

    background: #351313;

    color: #ff9999;

    border: 1px solid #642626;
}

.btn-edit {

    background: #202020;

    color: #fff;

    border: 1px solid #3b3b3b;
}


/* TEST SELECTOR */

.test-selector {

    background: #111;

    border: 1px solid #292929;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 25px;
}

.test-selector label {

    display: block;

    font-weight: 600;

    margin-bottom: 8px;
}

.test-selector-row {

    display: flex;

    gap: 10px;
}

.test-selector select {

    flex: 1;

    background: #181818;

    border: 1px solid #333;

    color: #fff;

    border-radius: 8px;

    padding: 12px;
}


/* TEST INFO */

.test-info {

    background: #111;

    border: 1px solid #292929;

    border-radius: 12px;

    padding: 20px;

    margin-bottom: 20px;
}

.test-info h2 {

    margin: 0 0 12px;
}

.test-meta {

    display: flex;

    flex-wrap: wrap;

    gap: 10px;
}

.meta {

    background: #1a1a1a;

    border: 1px solid #303030;

    border-radius: 7px;

    padding: 7px 10px;

    color: #aaa;

    font-size: 13px;
}


/* ALERT */

.alert {

    padding: 13px 16px;

    border-radius: 8px;

    margin-bottom: 20px;
}

.success {

    background: #102719;

    border: 1px solid #235b36;

    color: #8df3a7;
}

.error {

    background: #2b1111;

    border: 1px solid #652828;

    color: #ff9999;
}


/* QUESTION */

.question-card {

    background: #111;

    border: 1px solid #292929;

    border-radius: 12px;

    padding: 22px;

    margin-bottom: 15px;
}

.question-header {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    margin-bottom: 14px;
}

.question-number {

    font-weight: 700;

    color: #aaa;
}

.question-text {

    font-size: 16px;

    line-height: 1.6;

    white-space: pre-wrap;

    margin-top: 7px;
}

.question-image {

    display: block;

    max-width: 100%;

    max-height: 300px;

    margin: 15px 0;

    border-radius: 8px;

    border: 1px solid #333;
}


/* OPTIONS */

.options {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

    margin-top: 18px;
}

.option {

    background: #181818;

    border: 1px solid #2c2c2c;

    border-radius: 8px;

    padding: 12px;
}

.option.correct {

    background: #112117;

    border-color: #39714a;
}

.option-label {

    font-weight: 700;

    margin-right: 7px;
}


/* FOOTER */

.question-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 20px;

    padding-top: 15px;

    border-top: 1px solid #242424;

    gap: 15px;
}

.question-actions {

    display: flex;

    gap: 8px;
}

.marks {

    display: flex;

    gap: 8px;

    flex-wrap: wrap;
}

.mark {

    background: #1a1a1a;

    border: 1px solid #303030;

    border-radius: 6px;

    padding: 6px 9px;

    color: #aaa;

    font-size: 12px;
}


/* EMPTY */

.empty {

    text-align: center;

    background: #111;

    border: 1px dashed #333;

    border-radius: 12px;

    padding: 60px 20px;

    color: #888;
}


@media(max-width:700px) {

    .header {

        flex-direction: column;

        align-items: flex-start;
    }

    .test-selector-row {

        flex-direction: column;
    }

    .options {

        grid-template-columns: 1fr;
    }

    .question-footer {

        flex-direction: column;

        align-items: flex-start;
    }

}

</style>

</head>


<body>

<div class="container">


    <div class="header">

        <div>

            <h1>Question Bank</h1>

            <p>
                Select a test to manage its questions.
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


    <!-- TEST SELECTOR -->

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


        <!-- TEST INFO -->

        <div class="test-info">

            <h2>
                <?= htmlspecialchars($selected_test['title']) ?>
            </h2>

            <div class="test-meta">

                <span class="meta">
                    Questions:
                    <?= count($questions) ?>
                </span>

                <span class="meta">
                    Duration:
                    <?= (int)$selected_test['duration_minutes'] ?>
                    minutes
                </span>

                <span class="meta">
                    Status:
                    <?= htmlspecialchars($selected_test['status']) ?>
                </span>

            </div>

        </div>


        <?php if (empty($questions)): ?>

            <div class="empty">

                <h3>
                    No questions in this test yet.
                </h3>

                <p>
                    Start adding questions to this test.
                </p>

                <br>

                <a
                    href="add_question.php?test_id=<?= $selected_test_id ?>"
                    class="btn btn-primary"
                >
                    + Add First Question
                </a>

            </div>

        <?php else: ?>


            <?php foreach ($questions as $question): ?>

                <div class="question-card">


                    <div class="question-header">

                        <div>

                            <div class="question-number">

                                Question
                                <?= (int)$question['question_order'] ?>

                            </div>

                            <div class="question-text">

                                <?= htmlspecialchars($question['question_text']) ?>

                            </div>

                        </div>

                    </div>


                    <?php if (!empty($question['question_image'])): ?>

                        <img
                            src="../<?= htmlspecialchars($question['question_image']) ?>"
                            class="question-image"
                            alt="Question image"
                        >

                    <?php endif; ?>


                    <div class="options">


                        <div
                            class="option <?= $question['correct_option'] === 'A' ? 'correct' : '' ?>"
                        >

                            <span class="option-label">
                                A.
                            </span>

                            <?= htmlspecialchars($question['option_a']) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'B' ? 'correct' : '' ?>"
                        >

                            <span class="option-label">
                                B.
                            </span>

                            <?= htmlspecialchars($question['option_b']) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'C' ? 'correct' : '' ?>"
                        >

                            <span class="option-label">
                                C.
                            </span>

                            <?= htmlspecialchars($question['option_c']) ?>

                        </div>


                        <div
                            class="option <?= $question['correct_option'] === 'D' ? 'correct' : '' ?>"
                        >

                            <span class="option-label">
                                D.
                            </span>

                            <?= htmlspecialchars($question['option_d']) ?>

                        </div>


                    </div>


                    <div class="question-footer">


                        <div class="marks">

                            <span class="mark">

                                +<?= htmlspecialchars($question['marks']) ?>

                            </span>

                            <span class="mark">

                                -<?= htmlspecialchars($question['negative_marks']) ?>

                            </span>

                            <span class="mark">

                                Correct:
                                <?= htmlspecialchars($question['correct_option']) ?>

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
                                onsubmit="
                                    return confirm(
                                        'Delete this question from the test?'
                                    );
                                "
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

        <div class="empty">

            <h3>
                Select a test
            </h3>

            <p>
                Questions will be displayed test-wise after selecting a test.
            </p>

        </div>

    <?php endif; ?>


</div>

</body>

</html>