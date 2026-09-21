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

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #f5f7f9;

    color: #17202a;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


.topbar {

    background: #111827;

    color: white;

    padding: 18px 30px;

    display: flex;

    align-items: center;

    justify-content: space-between;
}


.brand {

    font-size: 22px;

    font-weight: 700;
}


.back-btn {

    text-decoration: none;

    color: white;

    background: #374151;

    padding: 9px 15px;

    border-radius: 7px;

    font-size: 14px;
}


.container {

    max-width: 1000px;

    margin: 35px auto;

    padding: 0 20px;
}


.test-card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 20px 24px;

    margin-bottom: 20px;
}


.test-label {

    font-size: 12px;

    text-transform: uppercase;

    color: #6b7280;

    font-weight: 700;

    letter-spacing: .7px;
}


.test-title {

    margin-top: 6px;

    font-size: 22px;

    font-weight: 700;
}


.card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 28px;
}


h1 {

    margin-top: 0;

    font-size: 24px;
}


label {

    display: block;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 8px;
}


textarea {

    width: 100%;

    min-height: 300px;

    resize: vertical;

    border: 1px solid #d1d5db;

    border-radius: 9px;

    padding: 16px;

    font-size: 16px;

    line-height: 1.6;

    font-family: Arial, sans-serif;

    outline: none;
}


textarea:focus {

    border-color: #111827;
}


.hint {

    margin-top: 8px;

    font-size: 13px;

    color: #6b7280;
}


.example {

    background: #f8fafc;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    padding: 14px;

    margin-top: 12px;

    font-size: 14px;

    line-height: 1.7;

    white-space: pre-line;
}


.correct-section {

    margin-top: 25px;
}


.correct-buttons {

    display: flex;

    gap: 12px;
}


.correct-btn {

    width: 60px;

    height: 48px;

    border: 1px solid #d1d5db;

    background: white;

    color: #374151;

    border-radius: 8px;

    font-size: 16px;

    font-weight: 700;

    cursor: pointer;
}


.correct-btn:hover {

    border-color: #111827;
}


.correct-btn.selected {

    background: #111827;

    color: white;

    border-color: #111827;
}


.marking {

    display: flex;

    gap: 12px;

    margin-top: 25px;

    flex-wrap: wrap;
}


.mark-box {

    padding: 12px 18px;

    background: #f8fafc;

    border: 1px solid #e5e7eb;

    border-radius: 8px;

    font-weight: 700;
}


.image-section {

    margin-top: 25px;
}


input[type="file"] {

    width: 100%;

    padding: 12px;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    background: white;
}


.image-preview {

    display: none;

    margin-top: 12px;

    max-width: 400px;

    max-height: 250px;

    border-radius: 8px;

    border: 1px solid #ddd;
}


.actions {

    display: flex;

    gap: 12px;

    margin-top: 30px;

    flex-wrap: wrap;
}


.save-btn {

    border: none;

    background: #111827;

    color: white;

    padding: 14px 24px;

    border-radius: 8px;

    font-size: 15px;

    font-weight: 700;

    cursor: pointer;
}


.save-btn:hover {

    background: #000;
}


.questions-btn {

    text-decoration: none;

    background: #e5e7eb;

    color: #111827;

    padding: 14px 20px;

    border-radius: 8px;

    font-weight: 700;
}


.alert {

    padding: 14px 16px;

    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}


.success {

    background: #ecfdf5;

    color: #065f46;

    border: 1px solid #a7f3d0;
}


.error {

    background: #fef2f2;

    color: #991b1b;

    border: 1px solid #fecaca;
}


.preview-box {

    margin-top: 25px;

    display: none;

    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 20px;

    background: #fafafa;
}


.preview-question {

    font-weight: 700;

    margin-bottom: 15px;
}


.preview-option {

    padding: 8px 0;

    font-size: 14px;
}


@media (max-width: 700px) {

    .card {

        padding: 20px;
    }

    .topbar {

        padding: 15px;
    }

}

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