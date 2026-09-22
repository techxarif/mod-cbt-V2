<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';


// =========================================================
// GET TEST ID
// =========================================================

$test_id = isset($_GET['test_id'])
    ? (int)$_GET['test_id']
    : (int)($_POST['test_id'] ?? 0);

if ($test_id <= 0) {
    die('Invalid test selected.');
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
// DEFAULT VALUES
// =========================================================

$message = '';
$error = '';

$question_input = '';

$correct_option = 'A';

$marks = 4;
$negative_marks = 1;


// =========================================================
// HANDLE FORM
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $question_input = trim(
        $_POST['question_input'] ?? ''
    );

    $correct_option = strtoupper(
        trim($_POST['correct_option'] ?? 'A')
    );

    $marks = 4;
    $negative_marks = 1;


    // =====================================================
    // BASIC VALIDATION
    // =====================================================

    if ($question_input === '') {

        $error = 'Please enter the question with options.';

    } elseif (
        !in_array(
            $correct_option,
            ['A', 'B', 'C', 'D'],
            true
        )
    ) {

        $error = 'Please select the correct answer.';

    }


    // =====================================================
    // PARSE QUESTION + OPTIONS
    // =====================================================

    $question_text = '';
    $option_a = '';
    $option_b = '';
    $option_c = '';
    $option_d = '';

    if ($error === '') {

        /*
         * Supported formats:
         *
         * A) Option
         * B) Option
         * C) Option
         * D) Option
         *
         * Also accepts:
         *
         * A. Option
         * B. Option
         * C. Option
         * D. Option
         *
         * Also accepts:
         *
         * (A) Option
         * (B) Option
         */


        $pattern = '/
            (?:^|\R)
            \s*
            [\(]?\s*
            ([ABCD])
            \s*[\)\.]
            \s*
            (.*?)
            (?=
                \R\s*\(?[ABCD]\s*[\)\.]
                |
                $
            )
        /isx';


        preg_match_all(
            $pattern,
            $question_input,
            $matches,
            PREG_SET_ORDER
        );


        if (count($matches) < 4) {

            /*
             * Fallback parser for options that may appear
             * on the same line.
             *
             * Example:
             *
             * A) One B) Two C) Three D) Four
             */

            $inline_pattern = '/
                \s*
                \(?([ABCD])\)?
                \s*[\)\.]
                \s*
                (.*?)
                (?=
                    \s+\(?[ABCD]\)?\s*[\)\.]
                    |
                    $
                )
            /isx';


            preg_match_all(
                $inline_pattern,
                $question_input,
                $inline_matches,
                PREG_SET_ORDER
            );


            if (count($inline_matches) >= 4) {

                $matches = $inline_matches;
            }
        }


        // =================================================
        // EXTRACT OPTIONS
        // =================================================

        $options = [];

        foreach ($matches as $match) {

            $letter = strtoupper(
                trim($match[1])
            );

            $text = trim($match[2]);

            $text = preg_replace(
                '/\s+/',
                ' ',
                $text
            );

            if (
                in_array(
                    $letter,
                    ['A', 'B', 'C', 'D'],
                    true
                )
            ) {

                $options[$letter] = $text;
            }
        }


        // =================================================
        // CHECK ALL OPTIONS EXIST
        // =================================================

        if (
            !isset($options['A']) ||
            !isset($options['B']) ||
            !isset($options['C']) ||
            !isset($options['D'])
        ) {

            $error =
                'Could not detect all four options. ' .
                'Please write them as A), B), C), D).';

        } else {

            $option_a = $options['A'];
            $option_b = $options['B'];
            $option_c = $options['C'];
            $option_d = $options['D'];


            // =============================================
            // REMOVE OPTIONS FROM QUESTION
            // =============================================

            /*
             * Find the first A/B/C/D option.
             * Everything before that becomes the question.
             */

            $question_pattern = '/
                ^(.*?)
                \R?\s*
                \(?A\)?
                \s*[\)\.]
                \s*
            /isx';


            if (
                preg_match(
                    $question_pattern,
                    $question_input,
                    $question_match
                )
            ) {

                $question_text =
                    trim($question_match[1]);

            } else {

                /*
                 * Fallback:
                 * locate A) / A. / (A)
                 */

                $position = null;

                $patterns = [
                    '/\n\s*\(?A\)?\s*[\)\.]\s*/i',
                    '/\s+\(?A\)?\s*[\)\.]\s*/i'
                ];

                foreach ($patterns as $p) {

                    if (
                        preg_match(
                            $p,
                            $question_input,
                            $m,
                            PREG_OFFSET_CAPTURE
                        )
                    ) {

                        $position =
                            $m[0][1];

                        break;
                    }
                }


                if ($position !== null) {

                    $question_text =
                        trim(
                            substr(
                                $question_input,
                                0,
                                $position
                            )
                        );

                } else {

                    $question_text =
                        trim($question_input);
                }
            }


            if ($question_text === '') {

                $error =
                    'Could not detect the question text.';
            }
        }
    }


    // =====================================================
    // IMAGE UPLOAD
    // =====================================================

    $image_path = null;


    if (
        $error === '' &&
        isset($_FILES['question_image'])
    ) {

        if (
            $_FILES['question_image']['error']
            !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES['question_image']['error']
                !== UPLOAD_ERR_OK
            ) {

                $error =
                    'Question image upload failed.';

            } else {

                $max_size =
                    5 * 1024 * 1024;


                if (
                    $_FILES['question_image']['size']
                    > $max_size
                ) {

                    $error =
                        'Question image must be smaller than 5 MB.';

                } else {

                    $tmp_name =
                        $_FILES['question_image']['tmp_name'];


                    $finfo =
                        new finfo(FILEINFO_MIME_TYPE);


                    $mime =
                        $finfo->file($tmp_name);


                    $allowed_types = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp'
                    ];


                    if (
                        !isset(
                            $allowed_types[$mime]
                        )
                    ) {

                        $error =
                            'Only JPG, PNG and WEBP images are allowed.';

                    } else {

                        $upload_dir =
                            __DIR__ .
                            '/../uploads/questions/';


                        if (
                            !is_dir($upload_dir)
                        ) {

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
                            bin2hex(
                                random_bytes(5)
                            ) .
                            '.' .
                            $allowed_types[$mime];


                        $destination =
                            $upload_dir .
                            $filename;


                        if (
                            !move_uploaded_file(
                                $tmp_name,
                                $destination
                            )
                        ) {

                            $error =
                                'Unable to save the question image.';

                        } else {

                            $image_path =
                                'uploads/questions/' .
                                $filename;
                        }
                    }
                }
            }
        }
    }


    // =====================================================
    // SAVE QUESTION
    // =====================================================

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            // ---------------------------------------------
            // Next question order
            // ---------------------------------------------

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


            // ---------------------------------------------
            // Insert question
            // ---------------------------------------------

            $stmt = $pdo->prepare("
                INSERT INTO questions
                (
                    question_text,
                    question_image,
                    option_a,
                    option_b,
                    option_c,
                    option_d,
                    correct_option,
                    marks,
                    negative_marks
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            $stmt->execute([
                $question_text,
                $image_path,
                $option_a,
                $option_b,
                $option_c,
                $option_d,
                $correct_option,
                4,
                1
            ]);


            $question_id =
                (int)$pdo->lastInsertId();


            // ---------------------------------------------
            // Attach to test
            // ---------------------------------------------

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


            $pdo->commit();


            // ---------------------------------------------
            // Reset form
            // ---------------------------------------------

            $question_input = '';

            $correct_option = 'A';

            $message =
                'Question added successfully.';


        } catch (Exception $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                'Failed to save question: ' .
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
    Add Question - MODUS CBT
</title>


<style>
:root{
    --bg:#05080b;
    --panel:rgba(13,19,24,.88);
    --panel2:rgba(8,12,16,.94);
    --line:rgba(255,255,255,.085);
    --text:#f8fafc;
    --muted:#7d8b9d;
    --green:#22c55e;
    --green2:#16a34a;
    --danger:#ef4444;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    min-height:100vh;
    background:
      radial-gradient(circle at 12% 8%,rgba(34,197,94,.10),transparent 28%),
      radial-gradient(circle at 88% 18%,rgba(59,130,246,.08),transparent 30%),
      linear-gradient(135deg,#030507 0%,#071017 52%,#040608 100%);
    color:var(--text);
    font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    overflow-x:hidden;
}
body:before{
    content:"";
    position:fixed;inset:0;pointer-events:none;z-index:-3;
    background-image:linear-gradient(rgba(255,255,255,.025) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.025) 1px,transparent 1px);
    background-size:54px 54px;
    mask-image:linear-gradient(to bottom,black,transparent 92%);
    animation:gridMove 24s linear infinite;
}
body:after{
    content:"";position:fixed;width:42vw;height:42vw;left:-18vw;top:-18vw;border-radius:50%;
    background:rgba(34,197,94,.12);filter:blur(110px);z-index:-2;pointer-events:none;
    animation:ambient 14s ease-in-out infinite alternate;
}
@keyframes gridMove{from{transform:translate3d(0,0,0)}to{transform:translate3d(54px,54px,0)}}
@keyframes ambient{0%{transform:translate(0,0) scale(1)}100%{transform:translate(10vw,7vh) scale(1.12)}}
.topbar{
    position:sticky;top:0;z-index:20;height:72px;padding:0 clamp(18px,4vw,46px);
    display:flex;align-items:center;justify-content:space-between;
    background:rgba(4,8,11,.72);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);
    border-bottom:1px solid rgba(255,255,255,.07);
    box-shadow:0 18px 50px rgba(0,0,0,.24);
    animation:barIn .75s cubic-bezier(.16,1,.3,1) both;
}
.topbar:after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:1px;background:linear-gradient(90deg,transparent,rgba(34,197,94,.5),transparent);opacity:.45}
@keyframes barIn{from{opacity:0;transform:translateY(-18px)}to{opacity:1;transform:none}}
.brand{display:flex;align-items:center;gap:11px;font-size:18px;font-weight:800;letter-spacing:-.4px}
.brand:before{content:"M";width:36px;height:36px;border-radius:10px;display:grid;place-items:center;color:#031109;background:linear-gradient(135deg,#4ade80,#16a34a);box-shadow:0 0 28px rgba(34,197,94,.2);font-weight:950}
.back-btn{
    text-decoration:none;color:#b9c4d2;background:rgba(255,255,255,.035);border:1px solid var(--line);
    padding:9px 14px;border-radius:9px;font-size:12px;font-weight:650;transition:all .3s cubic-bezier(.16,1,.3,1);
}
.back-btn:hover{color:#fff;border-color:rgba(34,197,94,.32);background:rgba(34,197,94,.07);transform:translateY(-2px);box-shadow:0 10px 25px rgba(0,0,0,.25)}
.container{max-width:1050px;margin:0 auto;padding:38px 22px 70px}
.test-card,.card,.preview-box{
    background:linear-gradient(145deg,var(--panel),var(--panel2));
    border:1px solid var(--line);border-radius:18px;
    box-shadow:0 28px 80px rgba(0,0,0,.30),inset 0 1px rgba(255,255,255,.035);
    backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
}
.test-card{padding:21px 24px;margin-bottom:18px;position:relative;overflow:hidden;animation:panelIn .8s .05s cubic-bezier(.16,1,.3,1) both}
.test-card:before{content:"";position:absolute;inset:0;background:linear-gradient(100deg,rgba(34,197,94,.06),transparent 35%);pointer-events:none}
.test-label{font-size:10px;text-transform:uppercase;color:#64748b;font-weight:800;letter-spacing:1.2px}
.test-title{margin-top:7px;font-size:21px;font-weight:760;letter-spacing:-.4px;color:#f8fafc}
.card{padding:30px;animation:panelIn .85s .13s cubic-bezier(.16,1,.3,1) both}
@keyframes panelIn{from{opacity:0;transform:translateY(24px) scale(.985);filter:blur(3px)}to{opacity:1;transform:none;filter:none}}
h1{margin:0 0 26px;font-size:23px;letter-spacing:-.5px}
label{display:block;font-size:12px;font-weight:700;color:#cbd5e1;margin-bottom:8px}
textarea{
    width:100%;min-height:300px;resize:vertical;border:1px solid #273441;border-radius:12px;padding:16px;
    font-size:14px;line-height:1.65;font-family:inherit;outline:none;background:rgba(2,6,9,.72);color:#f8fafc;
    transition:border-color .3s,box-shadow .3s,background .3s;box-shadow:inset 0 1px rgba(255,255,255,.02)
}
textarea::placeholder{color:#4d5b6b}
textarea:focus{border-color:rgba(34,197,94,.52);background:rgba(2,6,9,.9);box-shadow:0 0 0 4px rgba(34,197,94,.065),0 20px 45px rgba(0,0,0,.18)}
.hint{margin-top:8px;font-size:11px;color:#687789;line-height:1.5}
.example{margin-top:13px;background:rgba(255,255,255,.022);border:1px solid rgba(255,255,255,.065);border-radius:11px;padding:14px;font-size:12px;line-height:1.7;color:#94a3b8;white-space:pre-line}
.example strong{color:#cbd5e1}
.correct-section{margin-top:25px}
.correct-buttons{display:flex;gap:10px}
.correct-btn{
    width:52px;height:45px;border:1px solid #2b3947;background:rgba(255,255,255,.025);color:#94a3b8;border-radius:10px;
    font-size:14px;font-weight:800;cursor:pointer;transition:all .3s cubic-bezier(.16,1,.3,1)
}
.correct-btn:hover{transform:translateY(-3px);border-color:rgba(34,197,94,.35);color:#fff;background:rgba(34,197,94,.06)}
.correct-btn.selected{background:linear-gradient(135deg,#22c55e,#16a34a);color:#031109;border-color:#22c55e;box-shadow:0 10px 28px rgba(34,197,94,.16);transform:translateY(-2px)}
.marking{display:flex;gap:10px;margin-top:25px;flex-wrap:wrap}
.mark-box{padding:10px 14px;background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.065);border-radius:10px;font-size:11px;font-weight:700;color:#aeb9c7;transition:transform .25s,border-color .25s}
.mark-box:hover{transform:translateY(-2px);border-color:rgba(255,255,255,.13)}
.image-section{margin-top:25px}
input[type=file]{width:100%;padding:11px;border:1px solid #273441;border-radius:10px;background:rgba(2,6,9,.7);color:#8fa0b2;font-size:12px}
input[type=file]::file-selector-button{border:0;border-radius:7px;padding:8px 11px;margin-right:10px;background:rgba(255,255,255,.08);color:#dbe4ee;cursor:pointer}
.image-preview{display:none;margin-top:13px;max-width:400px;max-height:250px;border-radius:12px;border:1px solid rgba(255,255,255,.1);box-shadow:0 18px 45px rgba(0,0,0,.35);animation:previewIn .45s cubic-bezier(.16,1,.3,1)}
@keyframes previewIn{from{opacity:0;transform:scale(.96);filter:blur(2px)}to{opacity:1;transform:none;filter:none}}
.actions{display:flex;gap:10px;margin-top:30px;flex-wrap:wrap}
.save-btn,.questions-btn{position:relative;overflow:hidden;border-radius:10px;padding:13px 19px;font-size:12px;font-weight:800;cursor:pointer;transition:all .3s cubic-bezier(.16,1,.3,1)}
.save-btn{border:none;background:linear-gradient(135deg,#22c55e,#16a34a);color:#031109;box-shadow:0 12px 32px rgba(34,197,94,.12)}
.save-btn:before{content:"";position:absolute;top:0;bottom:0;left:-80%;width:55%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.28),transparent);transform:skewX(-20deg);transition:left .65s ease}
.save-btn:hover{transform:translateY(-3px);box-shadow:0 17px 38px rgba(34,197,94,.2)}
.save-btn:hover:before{left:135%}
.questions-btn{text-decoration:none;background:rgba(255,255,255,.035);color:#b8c4d2;border:1px solid var(--line)}
.questions-btn:hover{color:#fff;border-color:rgba(255,255,255,.15);transform:translateY(-3px);background:rgba(255,255,255,.055)}
.alert{padding:13px 15px;border-radius:11px;margin-bottom:18px;font-size:12px;animation:alertIn .5s cubic-bezier(.16,1,.3,1) both}
.success{background:rgba(34,197,94,.07);color:#86efac;border:1px solid rgba(34,197,94,.2)}
.error{background:rgba(239,68,68,.07);color:#fca5a5;border:1px solid rgba(239,68,68,.2)}
@keyframes alertIn{from{opacity:0;transform:translateY(-10px);filter:blur(2px)}to{opacity:1;transform:none;filter:none}}
.preview-box{margin-top:25px;display:none;padding:20px;animation:panelIn .5s cubic-bezier(.16,1,.3,1)}
.preview-question{font-weight:750;margin-bottom:15px;color:#f1f5f9}.preview-option{padding:8px 0;font-size:13px;color:#94a3b8}
@media(max-width:700px){.topbar{padding:0 15px;height:64px}.brand{font-size:16px}.back-btn{padding:8px 10px}.container{padding:24px 13px 50px}.card{padding:21px}.test-card{padding:18px}.correct-buttons{gap:8px}h1{font-size:21px}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
</style>

</head>


<body>


<div class="topbar">

    <div class="brand">
        MODUS CBT
    </div>


    <a
        href="questions.php?test_id=<?= $test_id ?>"
        class="back-btn"
    >
        ← Back to Questions
    </a>

</div>



<div class="container">


    <!-- TEST -->

    <div class="test-card">

        <div class="test-label">
            Adding question to test
        </div>


        <div class="test-title">

            <?= htmlspecialchars($test['title']) ?>

        </div>

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



    <div class="card">


        <h1>
            Add Question
        </h1>


        <form
            method="POST"
            enctype="multipart/form-data"
            id="questionForm"
        >


            <input
                type="hidden"
                name="test_id"
                value="<?= $test_id ?>"
            >


            <!-- ONE QUESTION BOX -->

            <div>

                <label for="question_input">

                    Question + Options

                </label>


                <textarea
                    id="question_input"
                    name="question_input"
                    placeholder="Type the complete question here...

Example:

What is the powerhouse of the cell?

A) Nucleus
B) Ribosome
C) Mitochondria
D) Golgi body"
                    required
                ><?= htmlspecialchars($question_input) ?></textarea>


                <div class="hint">

                    Type the question and all four options in one box.
                    Use A), B), C), D) or A., B., C., D.

                </div>


                <div class="example">

<strong>Example:</strong>

What is the powerhouse of the cell?

A) Nucleus
B) Ribosome
C) Mitochondria
D) Golgi body

                </div>

            </div>



            <!-- CORRECT ANSWER -->

            <div class="correct-section">

                <label>
                    Correct Answer
                </label>


                <div class="correct-buttons">


                    <?php foreach (
                        ['A', 'B', 'C', 'D']
                        as $option
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

            </div>



            <!-- MARKING -->

            <div class="marking">

                <div class="mark-box">

                    Correct: +4

                </div>


                <div class="mark-box">

                    Wrong: -1

                </div>


                <div class="mark-box">

                    Unanswered: 0

                </div>

            </div>



            <!-- IMAGE -->

            <div class="image-section">

                <label for="question_image">

                    Question Image
                    <span style="font-weight:normal;">
                        (Optional)
                    </span>

                </label>


                <input
                    type="file"
                    id="question_image"
                    name="question_image"
                    accept=".jpg,.jpeg,.png,.webp"
                >


                <div class="hint">

                    JPG, PNG or WEBP — maximum 5 MB.

                </div>


                <img
                    id="imagePreview"
                    class="image-preview"
                    alt="Question image preview"
                >

            </div>



            <!-- PARSED PREVIEW -->

            <div
                class="preview-box"
                id="previewBox"
            >

                <div
                    class="preview-question"
                    id="previewQuestion"
                ></div>


                <div
                    class="preview-option"
                    id="previewA"
                ></div>


                <div
                    class="preview-option"
                    id="previewB"
                ></div>


                <div
                    class="preview-option"
                    id="previewC"
                ></div>


                <div
                    class="preview-option"
                    id="previewD"
                ></div>

            </div>



            <!-- ACTIONS -->

            <div class="actions">


                <button
                    type="submit"
                    name="save_next"
                    value="1"
                    class="save-btn"
                >

                    Save & Add Next Question

                </button>


                <a
                    href="questions.php?test_id=<?= $test_id ?>"
                    class="questions-btn"
                >

                    View Test Questions

                </a>


            </div>


        </form>

    </div>

</div>



<script>


// =========================================================
// CORRECT ANSWER BUTTONS
// =========================================================

const correctButtons =
    document.querySelectorAll('.correct-btn');


const correctInput =
    document.getElementById(
        'correct_option'
    );


correctButtons.forEach(button => {

    button.addEventListener(
        'click',
        function () {

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

        }
    );

});



// =========================================================
// PARSE QUESTION IN BROWSER
// =========================================================

const questionInput =
    document.getElementById(
        'question_input'
    );


const previewBox =
    document.getElementById(
        'previewBox'
    );


function parseQuestion(text) {

    const result = {

        question: '',

        A: '',
        B: '',
        C: '',
        D: ''

    };


    /*
     * Detect:
     *
     * A) text
     * A. text
     * (A) text
     */


    const regex =
        /(?:^|\n)\s*\(?([ABCD])\)?\s*[\)\.]\s*(.*?)(?=\n\s*\(?[ABCD]\)?\s*[\)\.]|\s*$)/gis;


    const matches =
        [...text.matchAll(regex)];


    matches.forEach(match => {

        const letter =
            match[1].toUpperCase();


        const value =
            match[2].trim();


        if (
            ['A', 'B', 'C', 'D']
            .includes(letter)
        ) {

            result[letter] =
                value;

        }

    });


    /*
     * Question text = everything before A)
     */


    const firstOption =
        text.search(
            /(?:^|\n)\s*\(?A\)?\s*[\)\.]\s*/i
        );


    if (firstOption >= 0) {

        result.question =
            text
                .substring(
                    0,
                    firstOption
                )
                .trim();

    } else {

        result.question =
            text.trim();

    }


    return result;
}



// =========================================================
// LIVE PREVIEW
// =========================================================

function updatePreview() {

    const text =
        questionInput.value.trim();


    if (!text) {

        previewBox.style.display =
            'none';

        return;
    }


    const data =
        parseQuestion(text);


    document.getElementById(
        'previewQuestion'
    ).textContent =
        data.question;


    document.getElementById(
        'previewA'
    ).textContent =
        'A) ' + data.A;


    document.getElementById(
        'previewB'
    ).textContent =
        'B) ' + data.B;


    document.getElementById(
        'previewC'
    ).textContent =
        'C) ' + data.C;


    document.getElementById(
        'previewD'
    ).textContent =
        'D) ' + data.D;


    previewBox.style.display =
        'block';
}


questionInput.addEventListener(
    'input',
    updatePreview
);



// =========================================================
// IMAGE PREVIEW
// =========================================================

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



// =========================================================
// KEYBOARD SHORTCUTS
// 1 = A
// 2 = B
// 3 = C
// 4 = D
// =========================================================

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


        if (
            shortcuts[event.key]
        ) {

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