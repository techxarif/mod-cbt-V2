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
    Edit Question - MODUS CBT
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

    letter-spacing: .5px;
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


.question-number {

    margin-top: 8px;

    font-size: 13px;

    color: #6b7280;
}


.card {

    background: white;

    border: 1px solid #e5e7eb;

    border-radius: 12px;

    padding: 28px;
}


h1 {

    margin-top: 0;

    margin-bottom: 25px;

    font-size: 24px;
}


label {

    display: block;

    font-size: 14px;

    font-weight: 700;

    margin-bottom: 8px;
}


textarea,
input[type="text"],
input[type="number"],
input[type="file"] {

    width: 100%;

    border: 1px solid #d1d5db;

    border-radius: 8px;

    padding: 12px;

    font-size: 15px;

    outline: none;

    background: white;
}


textarea {

    min-height: 140px;

    resize: vertical;
}


textarea:focus,
input:focus {

    border-color: #111827;
}


.field {

    margin-bottom: 22px;
}


.options {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 16px;
}


.option-box {

    position: relative;
}


.option-box > label {

    position: absolute;

    left: 12px;

    top: 39px;

    width: 30px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    background: #111827;

    color: white;

    border-radius: 6px;

    font-weight: 700;

    z-index: 2;

    pointer-events: none;
}


.option-box input {

    padding-left: 55px;
}


.correct-section {

    margin-top: 25px;
}


.correct-buttons {

    display: flex;

    gap: 12px;

    flex-wrap: wrap;
}


.correct-btn {

    border: 1px solid #d1d5db;

    background: white;

    color: #374151;

    padding: 12px 25px;

    border-radius: 8px;

    font-weight: 700;

    cursor: pointer;

    transition: .15s;
}


.correct-btn:hover {

    border-color: #111827;
}


.correct-btn.selected {

    background: #111827;

    color: white;

    border-color: #111827;
}


.marks-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 16px;

    margin-top: 25px;
}


.current-image {

    margin-top: 10px;

    max-width: 400px;

    max-height: 250px;

    display: block;

    border-radius: 8px;

    border: 1px solid #ddd;
}


.image-preview {

    margin-top: 12px;

    max-width: 400px;

    max-height: 250px;

    border-radius: 8px;

    display: none;

    border: 1px solid #ddd;
}


.remove-image {

    margin-top: 10px;

    display: flex;

    align-items: center;

    gap: 8px;

    font-size: 13px;

    color: #991b1b;
}


.remove-image input {

    width: auto;
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

    padding: 13px 24px;

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

    padding: 13px 20px;

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


.hint {

    font-size: 12px;

    color: #6b7280;

    margin-top: 6px;
}


@media (max-width: 700px) {

    .options,
    .marks-grid {

        grid-template-columns: 1fr;
    }


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


    <!-- TEST INFORMATION -->

    <div class="test-card">

        <div class="test-label">
            Editing question in
        </div>


        <div class="test-title">

            <?= htmlspecialchars($test['title']) ?>

        </div>


        <div class="question-number">

            Question
            #<?= (int)$question['question_order'] ?>

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
            Edit Question
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



            <!-- QUESTION -->

            <div class="field">

                <label for="question_text">
                    Question
                </label>


                <textarea
                    id="question_text"
                    name="question_text"
                    required
                ><?= htmlspecialchars($question_text) ?></textarea>

            </div>



            <!-- OPTIONS -->

            <div class="field">

                <label>
                    Options
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



            <!-- CORRECT ANSWER -->

            <div class="correct-section">

                <label>
                    Correct Answer
                </label>


                <div class="correct-buttons">


                    <?php

                    foreach (
                        ['A', 'B', 'C', 'D']
                        as $option
                    ):

                    ?>

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

                    Click A, B, C or D to select the correct answer.

                </div>

            </div>



            <!-- MARKS -->

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



            <!-- IMAGE -->

            <div
                class="field"
                style="margin-top:25px;"
            >

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



                <div style="margin-top:15px;">

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

                    JPG, PNG or WEBP. Maximum 5 MB.

                </div>


                <img
                    id="imagePreview"
                    class="image-preview"
                    alt="New image preview"
                >

            </div>



            <!-- ACTIONS -->

            <div class="actions">


                <button
                    type="submit"
                    class="save-btn"
                >

                    Save Changes

                </button>


                <a
                    href="questions.php?test_id=<?= $test_id ?>"
                    class="questions-btn"
                >

                    Cancel

                </a>


            </div>


        </form>

    </div>

</div>



<script>


// =========================================================
// CORRECT ANSWER
// =========================================================

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