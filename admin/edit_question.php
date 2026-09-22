<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';


// =========================================================
// GET QUESTION ID
// =========================================================

$question_id = isset($_GET['id'])
    ? (int)$_GET['id']
    : (int)($_POST['question_id'] ?? 0);

$test_id = isset($_GET['test_id'])
    ? (int)$_GET['test_id']
    : (int)($_POST['test_id'] ?? 0);


if ($question_id <= 0 || $test_id <= 0) {
    die('Invalid question or test.');
}


// =========================================================
// LOAD TEST
// =========================================================

$stmt = $pdo->prepare("
    SELECT id, title
    FROM tests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$test_id]);

$test = $stmt->fetch();

if (!$test) {
    die('Test not found.');
}


// =========================================================
// LOAD QUESTION
// IMPORTANT: VERIFY QUESTION BELONGS TO THIS TEST
// =========================================================

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
    FROM questions q
    INNER JOIN test_questions tq
        ON tq.question_id = q.id
    WHERE q.id = ?
      AND tq.test_id = ?
    LIMIT 1
");

$stmt->execute([
    $question_id,
    $test_id
]);

$question = $stmt->fetch();

if (!$question) {
    die('Question not found in this test.');
}


// =========================================================
// VARIABLES
// =========================================================

$message = '';
$error = '';

$question_text = $question['question_text'];
$option_a = $question['option_a'];
$option_b = $question['option_b'];
$option_c = $question['option_c'];
$option_d = $question['option_d'];

$correct_option = $question['correct_option'];

$marks = $question['marks'];
$negative_marks = $question['negative_marks'];

$current_image = $question['question_image'];


// =========================================================
// HANDLE UPDATE
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $question_text = trim($_POST['question_text'] ?? '');

    $option_a = trim($_POST['option_a'] ?? '');
    $option_b = trim($_POST['option_b'] ?? '');
    $option_c = trim($_POST['option_c'] ?? '');
    $option_d = trim($_POST['option_d'] ?? '');

    $correct_option = strtoupper(
        trim($_POST['correct_option'] ?? 'A')
    );

    $marks = (float)($_POST['marks'] ?? 4);

    $negative_marks = (float)(
        $_POST['negative_marks'] ?? 1
    );


    // =====================================================
    // VALIDATION
    // =====================================================

    if ($question_text === '') {

        $error = 'Please enter the question.';

    } elseif (
        $option_a === '' ||
        $option_b === '' ||
        $option_c === '' ||
        $option_d === ''
    ) {

        $error = 'Please enter all four options.';

    } elseif (
        !in_array(
            $correct_option,
            ['A', 'B', 'C', 'D'],
            true
        )
    ) {

        $error = 'Please select the correct answer.';

    } elseif ($marks < 0) {

        $error = 'Marks cannot be negative.';

    } elseif ($negative_marks < 0) {

        $error = 'Negative marks cannot be negative.';

    }


    // =====================================================
    // IMAGE UPLOAD
    // =====================================================

    $new_image_path = $current_image;

    if (
        $error === '' &&
        isset($_FILES['question_image']) &&
        $_FILES['question_image']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['question_image']['error']
            !== UPLOAD_ERR_OK
        ) {

            $error = 'Question image upload failed.';

        } else {

            $max_size = 5 * 1024 * 1024;

            if (
                $_FILES['question_image']['size']
                > $max_size
            ) {

                $error =
                    'Question image must be smaller than 5 MB.';

            } else {

                $tmp_name =
                    $_FILES['question_image']['tmp_name'];

                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mime = $finfo->file($tmp_name);

                $allowed_types = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/webp' => 'webp'
                ];


                if (!isset($allowed_types[$mime])) {

                    $error =
                        'Only JPG, PNG and WEBP images are allowed.';

                } else {

                    $upload_dir =
                        __DIR__ . '/../uploads/questions/';


                    if (!is_dir($upload_dir)) {

                        mkdir(
                            $upload_dir,
                            0777,
                            true
                        );
                    }


                    $filename =
                        'question_' .
                        time() .
                        '_' .
                        bin2hex(random_bytes(5)) .
                        '.' .
                        $allowed_types[$mime];


                    $destination =
                        $upload_dir . $filename;


                    if (
                        !move_uploaded_file(
                            $tmp_name,
                            $destination
                        )
                    ) {

                        $error =
                            'Unable to save the question image.';

                    } else {

                        $new_image_path =
                            'uploads/questions/' . $filename;


                        // ---------------------------------
                        // Delete old image
                        // ---------------------------------

                        if (!empty($current_image)) {

                            $old_image =
                                __DIR__ .
                                '/../' .
                                $current_image;


                            if (
                                is_file($old_image)
                            ) {

                                @unlink($old_image);
                            }
                        }
                    }
                }
            }
        }
    }


    // =====================================================
    // REMOVE EXISTING IMAGE
    // =====================================================

    if (
        $error === '' &&
        isset($_POST['remove_image']) &&
        $_POST['remove_image'] === '1'
    ) {

        if (!empty($current_image)) {

            $old_image =
                __DIR__ .
                '/../' .
                $current_image;


            if (is_file($old_image)) {

                @unlink($old_image);
            }
        }

        $new_image_path = null;
    }


    // =====================================================
    // UPDATE DATABASE
    // =====================================================

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            // ---------------------------------------------
            // Update question
            // ---------------------------------------------

            $stmt = $pdo->prepare("
                UPDATE questions
                SET
                    question_text = ?,
                    question_image = ?,
                    option_a = ?,
                    option_b = ?,
                    option_c = ?,
                    option_d = ?,
                    correct_option = ?,
                    marks = ?,
                    negative_marks = ?
                WHERE id = ?
            ");


            $stmt->execute([
                $question_text,
                $new_image_path,
                $option_a,
                $option_b,
                $option_c,
                $option_d,
                $correct_option,
                $marks,
                $negative_marks,
                $question_id
            ]);


            // ---------------------------------------------
            // Make sure test relationship still exists
            // ---------------------------------------------

            $stmt = $pdo->prepare("
                SELECT id
                FROM test_questions
                WHERE test_id = ?
                  AND question_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $test_id,
                $question_id
            ]);


            $relationship = $stmt->fetch();


            if (!$relationship) {

                $stmt = $pdo->prepare("
                    SELECT
                        COALESCE(
                            MAX(question_order),
                            0
                        ) + 1
                    FROM test_questions
                    WHERE test_id = ?
                ");

                $stmt->execute([
                    $test_id
                ]);

                $question_order =
                    (int)$stmt->fetchColumn();


                $stmt = $pdo->prepare("
                    INSERT INTO test_questions
                    (
                        test_id,
                        question_id,
                        question_order
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $test_id,
                    $question_id,
                    $question_order
                ]);
            }


            $pdo->commit();


            $current_image = $new_image_path;

            $message =
                'Question updated successfully.';


        } catch (Exception $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $error =
                'Failed to update question: ' .
                $e->getMessage();
        }
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

<title>
    Edit Question — MODUS CBT
</title>


<style>

/* =========================================================
   MODUS CINEMATIC QUESTION EDITOR
   ========================================================= */

:root {

    --bg: #05070b;

    --panel: rgba(14, 18, 28, 0.84);

    --panel-strong: rgba(17, 22, 33, 0.96);

    --border: rgba(255,255,255,0.085);

    --border-hover: rgba(255,255,255,0.15);

    --text: #f4f7fb;

    --muted: #8d98aa;

    --muted-2: #657084;

    --blue: #5b8cff;

    --purple: #8b5cf6;

    --green: #45d483;

    --danger: #ff7187;

}


/* =========================================================
   RESET
   ========================================================= */

* {
    box-sizing: border-box;
}


html {
    scroll-behavior: smooth;
}


body {

    margin: 0;

    min-height: 100vh;

    font-family:
        Inter,
        ui-sans-serif,
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    color: var(--text);

    background:

        radial-gradient(
            circle at 10% 5%,
            rgba(91,140,255,0.13),
            transparent 28%
        ),

        radial-gradient(
            circle at 90% 15%,
            rgba(139,92,246,0.12),
            transparent 28%
        ),

        radial-gradient(
            circle at 50% 100%,
            rgba(60,100,255,0.07),
            transparent 38%
        ),

        var(--bg);

    overflow-x: hidden;
}


/* =========================================================
   AMBIENT GLOW
   ========================================================= */

body::before,
body::after {

    content: "";

    position: fixed;

    width: 420px;
    height: 420px;

    border-radius: 50%;

    filter: blur(110px);

    opacity: .15;

    pointer-events: none;

    z-index: -1;

    animation:
        ambientFloat
        13s
        ease-in-out
        infinite
        alternate;
}


body::before {

    top: -180px;
    left: -140px;

    background: #356cff;
}


body::after {

    right: -160px;
    bottom: -180px;

    background: #8b5cf6;

    animation-delay: -5s;
}


@keyframes ambientFloat {

    from {
        transform: translate3d(0,0,0) scale(1);
    }

    to {
        transform: translate3d(30px,25px,0) scale(1.08);
    }

}


/* =========================================================
   TOPBAR
   ========================================================= */

.topbar {

    height: 72px;

    position: sticky;

    top: 0;

    z-index: 100;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 32px;

    background:
        rgba(5,7,11,.78);

    border-bottom:
        1px solid var(--border);

    backdrop-filter:
        blur(22px);

    -webkit-backdrop-filter:
        blur(22px);
}


.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    font-weight: 800;

    letter-spacing: .4px;
}


.brand-mark {

    width: 35px;
    height: 35px;

    display: grid;

    place-items: center;

    border-radius: 10px;

    color: white;

    font-size: 15px;

    font-weight: 900;

    background:
        linear-gradient(
            135deg,
            var(--blue),
            var(--purple)
        );

    box-shadow:
        0 0 30px rgba(91,140,255,.24);
}


.brand-name {

    font-size: 16px;
}


.brand-name span {

    color: #8d98aa;

    font-weight: 500;

    margin-left: 4px;
}


.back-btn {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    padding: 9px 13px;

    border-radius: 9px;

    border:
        1px solid rgba(255,255,255,.08);

    background:
        rgba(255,255,255,.025);

    color: #c7cfdd;

    text-decoration: none;

    font-size: 12px;

    font-weight: 700;

    transition:
        .2s ease;
}


.back-btn:hover {

    color: white;

    background:
        rgba(255,255,255,.06);

    border-color:
        rgba(255,255,255,.14);

    transform:
        translateY(-1px);
}


/* =========================================================
   MAIN
   ========================================================= */

.container {

    width:
        min(100%, 1100px);

    margin:
        0 auto;

    padding:
        45px 24px 80px;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {

    margin-bottom: 28px;

    animation:
        fadeUp
        .55s
        ease
        both;
}


.eyebrow {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-bottom: 12px;

    color: #82a6ff;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: 1.8px;

    text-transform: uppercase;
}


.eyebrow-dot {

    width: 7px;
    height: 7px;

    border-radius: 50%;

    background: var(--blue);

    box-shadow:
        0 0 0 4px rgba(91,140,255,.08),
        0 0 15px rgba(91,140,255,.7);

    animation:
        pulse
        2s
        ease-in-out
        infinite;
}


@keyframes pulse {

    0%,
    100% {
        opacity: .55;
        transform: scale(.9);
    }

    50% {
        opacity: 1;
        transform: scale(1.1);
    }

}


.page-header h1 {

    margin: 0;

    font-size:
        clamp(30px,5vw,44px);

    line-height: 1.05;

    letter-spacing: -1.8px;

    font-weight: 800;
}


.page-header p {

    margin:
        13px 0 0;

    max-width: 680px;

    color: var(--muted);

    font-size: 14px;

    line-height: 1.7;
}


/* =========================================================
   TEST CONTEXT CARD
   ========================================================= */

.test-card {

    position: relative;

    margin-bottom: 18px;

    padding: 19px 22px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 20px;

    background:
        linear-gradient(
            135deg,
            rgba(17,23,35,.88),
            rgba(9,12,19,.92)
        );

    border:
        1px solid var(--border);

    border-radius: 16px;

    overflow: hidden;

    animation:
        fadeUp
        .55s
        .05s
        ease
        both;
}


.test-card::before {

    content: "";

    position: absolute;

    top: 0;
    left: 0;

    width: 3px;
    height: 100%;

    background:
        linear-gradient(
            180deg,
            var(--blue),
            var(--purple)
        );
}


.test-meta {

    min-width: 0;
}


.test-label {

    color: #718096;

    font-size: 10px;

    font-weight: 800;

    letter-spacing: 1.4px;

    text-transform: uppercase;

    margin-bottom: 6px;
}


.test-title {

    font-size: 17px;

    font-weight: 750;

    color: #edf2f8;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.question-number {

    flex-shrink: 0;

    padding: 7px 11px;

    border-radius: 999px;

    background:
        rgba(91,140,255,.07);

    border:
        1px solid rgba(91,140,255,.14);

    color: #8eafff;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: .5px;
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {

    margin-bottom: 18px;

    padding: 13px 15px;

    border-radius: 11px;

    font-size: 13px;

    line-height: 1.5;

    animation:
        fadeUp
        .4s
        ease
        both;
}


.success {

    color: #7de3a7;

    background:
        rgba(69,212,131,.07);

    border:
        1px solid rgba(69,212,131,.16);
}


.error {

    color: #ff9baa;

    background:
        rgba(255,113,135,.07);

    border:
        1px solid rgba(255,113,135,.17);
}


/* =========================================================
   MAIN EDITOR CARD
   ========================================================= */

.card {

    position: relative;

    padding: 31px;

    background:
        linear-gradient(
            145deg,
            rgba(20,25,38,.88),
            rgba(9,12,19,.94)
        );

    border:
        1px solid var(--border);

    border-radius: 22px;

    box-shadow:
        0 30px 90px rgba(0,0,0,.36),
        inset 0 1px 0 rgba(255,255,255,.025);

    overflow: hidden;

    animation:
        fadeUp
        .65s
        .1s
        ease
        both;
}


.card::before {

    content: "";

    position: absolute;

    top: 0;
    left: 10%;

    width: 80%;
    height: 1px;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(91,140,255,.65),
            rgba(139,92,246,.65),
            transparent
        );
}


.card::after {

    content: "";

    position: absolute;

    width: 300px;
    height: 300px;

    right: -170px;
    top: -170px;

    border-radius: 50%;

    background:
        rgba(91,140,255,.07);

    filter:
        blur(70px);

    pointer-events: none;
}


.card h1 {

    position: relative;

    z-index: 2;

    margin:
        0 0 27px;

    font-size: 21px;

    letter-spacing: -.5px;
}


/* =========================================================
   FORM
   ========================================================= */

form {

    position: relative;

    z-index: 2;
}


.field {

    margin-bottom: 25px;
}


label {

    display: block;

    margin-bottom: 9px;

    color: #dce3ee;

    font-size: 12px;

    font-weight: 750;

    letter-spacing: .2px;
}


textarea,
input[type="text"],
input[type="number"],
input[type="file"] {

    width: 100%;

    border:
        1px solid rgba(255,255,255,.09);

    border-radius: 11px;

    outline: none;

    background:
        rgba(4,7,12,.72);

    color: white;

    font-family: inherit;

    font-size: 14px;

    transition:
        border-color .2s ease,
        box-shadow .2s ease,
        background .2s ease;
}


textarea {

    min-height: 155px;

    padding: 15px;

    resize: vertical;

    line-height: 1.65;
}


input[type="text"],
input[type="number"] {

    height: 49px;

    padding:
        0 14px;
}


input[type="file"] {

    min-height: 49px;

    padding: 12px 14px;

    cursor: pointer;
}


input::placeholder,
textarea::placeholder {

    color: #596476;
}


textarea:hover,
input:hover {

    border-color:
        rgba(255,255,255,.14);
}


textarea:focus,
input:focus {

    border-color:
        rgba(91,140,255,.7);

    background:
        rgba(7,10,17,.95);

    box-shadow:
        0 0 0 3px rgba(91,140,255,.08),
        0 0 25px rgba(91,140,255,.05);
}


/* =========================================================
   OPTIONS
   ========================================================= */

.options {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 14px;
}


.option-box {

    position: relative;
}


.option-box > label {

    position: absolute;

    left: 10px;
    top: 10px;

    width: 29px;
    height: 29px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0;

    border-radius: 7px;

    color: white;

    background:
        linear-gradient(
            135deg,
            #3c68d9,
            #7250db
        );

    font-size: 11px;

    font-weight: 850;

    z-index: 2;

    pointer-events: none;
}


.option-box input {

    padding-left: 51px;
}


/* =========================================================
   CORRECT ANSWER
   ========================================================= */

.correct-section {

    margin-top: 28px;

    padding:
        20px;

    border-radius: 14px;

    background:
        rgba(255,255,255,.025);

    border:
        1px solid rgba(255,255,255,.055);
}


.correct-buttons {

    display: flex;

    gap: 9px;

    flex-wrap: wrap;
}


.correct-btn {

    min-width: 54px;

    height: 43px;

    padding:
        0 18px;

    border:
        1px solid rgba(255,255,255,.1);

    border-radius: 9px;

    background:
        rgba(255,255,255,.025);

    color: #aeb8c8;

    font-family: inherit;

    font-size: 12px;

    font-weight: 800;

    cursor: pointer;

    transition:
        .2s ease;
}


.correct-btn:hover {

    color: white;

    border-color:
        rgba(91,140,255,.4);

    background:
        rgba(91,140,255,.07);

    transform:
        translateY(-1px);
}


.correct-btn.selected {

    color: white;

    border-color:
        transparent;

    background:
        linear-gradient(
            135deg,
            var(--blue),
            var(--purple)
        );

    box-shadow:
        0 8px 25px rgba(91,140,255,.2);
}


.hint {

    margin-top: 8px;

    color: #687386;

    font-size: 11px;

    line-height: 1.5;
}


/* =========================================================
   MARKS
   ========================================================= */

.marks-grid {

    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 14px;

    margin-top: 22px;
}


/* =========================================================
   IMAGE
   ========================================================= */

.image-section {

    margin-top: 25px;

    padding-top: 25px;

    border-top:
        1px solid rgba(255,255,255,.06);
}


.current-image {

    display: block;

    max-width: 440px;

    max-height: 280px;

    object-fit: contain;

    margin:
        10px 0 12px;

    padding: 5px;

    border-radius: 11px;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid rgba(255,255,255,.1);
}


.image-preview {

    display: none;

    max-width: 440px;

    max-height: 280px;

    object-fit: contain;

    margin-top: 13px;

    padding: 5px;

    border-radius: 11px;

    background:
        rgba(0,0,0,.25);

    border:
        1px solid rgba(91,140,255,.25);

    box-shadow:
        0 15px 40px rgba(0,0,0,.25);
}


.remove-image {

    display: inline-flex;

    align-items: center;

    gap: 8px;

    margin-top: 3px;

    color: #ff8d9f;

    font-size: 11px;

    font-weight: 600;

    cursor: pointer;
}


.remove-image input {

    width: auto;

    accent-color: #ff7187;

}


/* =========================================================
   DIVIDER
   ========================================================= */

.editor-divider {

    height: 1px;

    margin:
        30px 0 0;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.08),
            transparent
        );
}


/* =========================================================
   ACTIONS
   ========================================================= */

.actions {

    display: flex;

    align-items: center;

    justify-content: space-between;

    gap: 12px;

    margin-top: 25px;
}


.save-btn {

    position: relative;

    min-height: 48px;

    padding:
        0 24px;

    border: 0;

    border-radius: 11px;

    overflow: hidden;

    color: white;

    background:
        linear-gradient(
            135deg,
            #4d7fff,
            #7457ed,
            #8b5cf6
        );

    box-shadow:
        0 12px 30px rgba(91,140,255,.22);

    font-family: inherit;

    font-size: 13px;

    font-weight: 800;

    cursor: pointer;

    transition:
        transform .2s ease,
        box-shadow .2s ease;
}


.save-btn::before {

    content: "";

    position: absolute;

    top: 0;
    bottom: 0;

    left: -80px;

    width: 55px;

    transform:
        skewX(-18deg);

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.35),
            transparent
        );

    transition:
        left .55s ease;
}


.save-btn:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 17px 38px rgba(91,140,255,.3);
}


.save-btn:hover::before {

    left:
        calc(100% + 50px);
}


.questions-btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    min-height: 48px;

    padding:
        0 19px;

    border-radius: 11px;

    border:
        1px solid rgba(255,255,255,.09);

    background:
        rgba(255,255,255,.025);

    color: #b8c1cf;

    text-decoration: none;

    font-size: 13px;

    font-weight: 700;

    transition:
        .2s ease;
}


.questions-btn:hover {

    color: white;

    background:
        rgba(255,255,255,.055);

    border-color:
        rgba(255,255,255,.15);

    transform:
        translateY(-1px);
}


/* =========================================================
   ANIMATION
   ========================================================= */

@keyframes fadeUp {

    from {

        opacity: 0;

        transform:
            translateY(16px);

    }

    to {

        opacity: 1;

        transform:
            translateY(0);

    }

}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 750px) {

    .topbar {

        height: auto;

        min-height: 68px;

        padding:
            12px 16px;
    }


    .container {

        padding:
            32px 15px 60px;
    }


    .options,
    .marks-grid {

        grid-template-columns:
            1fr;
    }


    .card {

        padding:
            22px 18px;

        border-radius:
            17px;
    }


    .test-card {

        align-items:
            flex-start;

        flex-direction:
            column;

        padding:
            17px;
    }


    .test-title {

        white-space:
            normal;
    }


    .actions {

        flex-direction:
            column-reverse;

        align-items:
            stretch;
    }


    .save-btn,
    .questions-btn {

        width: 100%;
    }

}


@media (max-width: 450px) {

    .brand-name {

        display: none;
    }


    .page-header h1 {

        font-size:
            32px;
    }


    .correct-btn {

        flex: 1;

        min-width:
            0;
    }

}


@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration:
            .01ms !important;

        animation-iteration-count:
            1 !important;

        transition-duration:
            .01ms !important;

        scroll-behavior:
            auto !important;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     TOPBAR
     ========================================================= -->

<div class="topbar">

    <div class="brand">

        <div class="brand-mark">
            M
        </div>

        <div class="brand-name">
            MODUS <span>CBT</span>
        </div>

    </div>


    <a
        href="questions.php?test_id=<?= $test_id ?>"
        class="back-btn"
    >
        ← Back to Questions
    </a>

</div>



<!-- =========================================================
     MAIN
     ========================================================= -->

<div class="container">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            QUESTION EDITOR

        </div>


        <h1>
            Edit Question
        </h1>


        <p>
            Modify the question, answer options, scoring,
            and optional question image without changing
            its position in the examination.
        </p>

    </div>



    <!-- =====================================================
         TEST CONTEXT
         ===================================================== -->

    <div class="test-card">

        <div class="test-meta">

            <div class="test-label">
                Editing question in
            </div>


            <div class="test-title">

                <?= htmlspecialchars($test['title']) ?>

            </div>

        </div>


        <div class="question-number">

            Question #<?= (int)$question['question_order'] ?>

        </div>

    </div>



    <!-- =====================================================
         ALERTS
         ===================================================== -->

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
         EDITOR
         ===================================================== -->

    <div class="card">


        <h1>
            Question Configuration
        </h1>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <!-- HIDDEN VALUES -->

            <input
                type="hidden"
                name="question_id"
                value="<?= $question_id ?>"
            >


            <input
                type="hidden"
                name="test_id"
                value="<?= $test_id ?>"
            >



            <!-- =================================================
                 QUESTION
                 ================================================= -->

            <div class="field">

                <label for="question_text">
                    Question
                </label>


                <textarea
                    id="question_text"
                    name="question_text"
                    placeholder="Enter the question..."
                    required
                ><?= htmlspecialchars($question_text) ?></textarea>


                <div class="hint">
                    Write the complete question exactly as it
                    should appear to students.
                </div>

            </div>



            <!-- =================================================
                 OPTIONS
                 ================================================= -->

            <div class="field">

                <label>
                    Answer Options
                </label>


                <div class="options">


                    <div class="option-box">

                        <label>A</label>

                        <input
                            type="text"
                            name="option_a"
                            value="<?= htmlspecialchars($option_a) ?>"
                            placeholder="Option A"
                            required
                        >

                    </div>



                    <div class="option-box">

                        <label>B</label>

                        <input
                            type="text"
                            name="option_b"
                            value="<?= htmlspecialchars($option_b) ?>"
                            placeholder="Option B"
                            required
                        >

                    </div>



                    <div class="option-box">

                        <label>C</label>

                        <input
                            type="text"
                            name="option_c"
                            value="<?= htmlspecialchars($option_c) ?>"
                            placeholder="Option C"
                            required
                        >

                    </div>



                    <div class="option-box">

                        <label>D</label>

                        <input
                            type="text"
                            name="option_d"
                            value="<?= htmlspecialchars($option_d) ?>"
                            placeholder="Option D"
                            required
                        >

                    </div>


                </div>

            </div>



            <!-- =================================================
                 CORRECT ANSWER
                 ================================================= -->

            <div class="correct-section">

                <label>
                    Correct Answer
                </label>


                <div class="correct-buttons">


                    <?php foreach (
                        ['A', 'B', 'C', 'D'] as $option
                    ): ?>


                        <button
                            type="button"
                            class="correct-btn
                            <?= $correct_option === $option
                                ? 'selected'
                                : ''
                            ?>"
                            data-option="<?= $option ?>"
                        >

                            <?= $option ?>

                        </button>


                    <?php endforeach; ?>


                </div>


                <input
                    type="hidden"
                    name="correct_option"
                    id="correct_option"
                    value="<?= htmlspecialchars($correct_option) ?>"
                >


                <div class="hint">
                    Select the correct option. Keyboard shortcuts:
                    1 = A, 2 = B, 3 = C, 4 = D.
                </div>

            </div>



            <!-- =================================================
                 MARKS
                 ================================================= -->

            <div class="marks-grid">


                <div>

                    <label for="marks">
                        Marks
                    </label>


                    <input
                        type="number"
                        id="marks"
                        name="marks"
                        value="<?= htmlspecialchars($marks) ?>"
                        min="0"
                        step="0.5"
                    >

                </div>



                <div>

                    <label for="negative_marks">
                        Negative Marks
                    </label>


                    <input
                        type="number"
                        id="negative_marks"
                        name="negative_marks"
                        value="<?= htmlspecialchars($negative_marks) ?>"
                        min="0"
                        step="0.5"
                    >

                </div>


            </div>



            <!-- =================================================
                 IMAGE
                 ================================================= -->

            <div class="image-section">

                <label>
                    Question Image
                </label>


                <?php if (!empty($current_image)): ?>

                    <img
                        src="../<?= htmlspecialchars($current_image) ?>"
                        class="current-image"
                        alt="Current question image"
                    >


                    <label class="remove-image">

                        <input
                            type="checkbox"
                            name="remove_image"
                            value="1"
                        >

                        Remove current image

                    </label>

                <?php endif; ?>



                <div style="margin-top:18px;">

                    <label for="question_image">

                        <?= !empty($current_image)
                            ? 'Replace Image'
                            : 'Upload Question Image'
                        ?>

                    </label>


                    <input
                        type="file"
                        id="question_image"
                        name="question_image"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                </div>


                <div class="hint">

                    JPG, PNG or WEBP · Maximum 5 MB

                </div>


                <img
                    id="imagePreview"
                    class="image-preview"
                    alt="New image preview"
                >

            </div>



            <div class="editor-divider"></div>



            <!-- =================================================
                 ACTIONS
                 ================================================= -->

            <div class="actions">


                <a
                    href="questions.php?test_id=<?= $test_id ?>"
                    class="questions-btn"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="save-btn"
                >
                    Save Changes
                </button>


            </div>


        </form>

    </div>


</div>



<script>


/* =========================================================
   CORRECT ANSWER
   ========================================================= */

const correctButtons =
    document.querySelectorAll('.correct-btn');


const correctInput =
    document.getElementById('correct_option');


correctButtons.forEach(button => {

    button.addEventListener('click', function () {

        const option =
            this.dataset.option;


        correctInput.value =
            option;


        correctButtons.forEach(btn => {

            btn.classList.remove(
                'selected'
            );

        });


        this.classList.add(
            'selected'
        );

    });

});



/* =========================================================
   IMAGE PREVIEW
   ========================================================= */

const imageInput =
    document.getElementById(
        'question_image'
    );


const imagePreview =
    document.getElementById(
        'imagePreview'
    );


imageInput.addEventListener(
    'change',
    function () {

        const file =
            this.files[0];


        if (!file) {

            imagePreview.style.display =
                'none';

            imagePreview.src =
                '';

            return;

        }


        const reader =
            new FileReader();


        reader.onload =
            function (event) {

                imagePreview.src =
                    event.target.result;

                imagePreview.style.display =
                    'block';

            };


        reader.readAsDataURL(file);

    }
);



/* =========================================================
   KEYBOARD SHORTCUTS
   ========================================================= */

document.addEventListener(
    'keydown',
    function (event) {

        const tag =
            document.activeElement.tagName;


        if (
            tag === 'INPUT' ||
            tag === 'TEXTAREA' ||
            tag === 'SELECT'
        ) {

            return;

        }


        const shortcuts = {

            '1': 'A',
            '2': 'B',
            '3': 'C',
            '4': 'D'

        };


        if (shortcuts[event.key]) {

            const option =
                shortcuts[event.key];


            const button =
                document.querySelector(
                    `.correct-btn[data-option="${option}"]`
                );


            if (button) {

                button.click();

            }

        }

    }
);


</script>


</body>

</html>