<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Handle actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $test_id = isset($_POST['test_id']) ? (int) $_POST['test_id'] : 0;
    $action  = $_POST['action'] ?? '';

    if ($test_id <= 0) {

        $error = "Please select a test.";

    } else {

        try {

            /*
            |------------------------------------------------------------------
            | Prepare Exam
            | draft → waiting
            |------------------------------------------------------------------
            */
            if ($action === 'prepare') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET status = 'waiting'
                    WHERE id = ?
                      AND status = 'draft'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam prepared successfully. Students can now enter the waiting room.";
                } else {
                    $error = "This test cannot be prepared. Make sure it is currently in draft status.";
                }
            }

            /*
            |------------------------------------------------------------------
            | Start Exam
            | waiting → active
            |------------------------------------------------------------------
            */
            elseif ($action === 'start') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET
                        status = 'active',
                        start_time = NOW()
                    WHERE id = ?
                      AND status = 'waiting'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam started successfully. Students will enter the CBT automatically.";
                } else {
                    $error = "This test cannot be started. Prepare the exam first.";
                }
            }

            /*
            |------------------------------------------------------------------
            | End Exam
            | active → completed
            |------------------------------------------------------------------
            */
            elseif ($action === 'end') {

                $stmt = $pdo->prepare("
                    UPDATE tests
                    SET
                        status = 'completed',
                        end_time = NOW()
                    WHERE id = ?
                      AND status = 'active'
                ");

                $stmt->execute([$test_id]);

                if ($stmt->rowCount() > 0) {
                    $message = "Exam ended successfully.";
                } else {
                    $error = "This test is not currently active.";
                }
            }

        } catch (PDOException $e) {

            $error = "Database error: " . $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load tests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        negative_marks,
        start_time,
        end_time,
        status
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Selected test
|--------------------------------------------------------------------------
*/

$selected_test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : (isset($_POST['test_id']) ? (int) $_POST['test_id'] : 0);

$selected_test = null;

if ($selected_test_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            duration_minutes,
            total_marks,
            negative_marks,
            start_time,
            end_time,
            status
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$selected_test_id]);

    $selected_test = $stmt->fetch();
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

    <title>Exam Control - MODUS CBT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .topbar {
            background: #111827;
            color: white;
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
        }

        .back {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .container {
            max-width: 1100px;
            margin: 30px auto;
            padding: 0 20px;
        }

        h1 {
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 25px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 15px;
            background: white;
        }

        .test-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-top: 25px;
        }

        .info-box {
            background: #f9fafb;
            border-radius: 10px;
            padding: 18px;
        }

        .info-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 7px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-draft {
            background: #e5e7eb;
            color: #374151;
        }

        .status-waiting {
            background: #fef3c7;
            color: #92400e;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        button {
            border: none;
            padding: 13px 22px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
        }

        .prepare {
            background: #f59e0b;
            color: white;
        }

        .start {
            background: #16a34a;
            color: white;
        }

        .end {
            background: #dc2626;
            color: white;
        }

        button:disabled {
            background: #d1d5db;
            color: #6b7280;
            cursor: not-allowed;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #6b7280;
        }

        @media (max-width: 700px) {

            .test-info {
                grid-template-columns: repeat(2, 1fr);
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
        href="dashboard.php"
        class="back"
    >
        ← Dashboard
    </a>

</div>

<div class="container">

    <h1>Exam Control</h1>

    <div class="subtitle">
        Prepare, start and end your examination.
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


    <div class="panel">

        <form method="GET">

            <label for="test_id">
                Select Test
            </label>

            <select
                name="test_id"
                id="test_id"
                onchange="this.form.submit()"
            >

                <option value="">
                    -- Select a test --
                </option>

                <?php foreach ($tests as $test): ?>

                    <option
                        value="<?= (int)$test['id'] ?>"
                        <?= $selected_test_id == $test['id'] ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($test['title']) ?>
                        —
                        <?= htmlspecialchars(strtoupper($test['status'])) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </form>

    </div>


    <?php if ($selected_test): ?>

        <div class="panel">

            <h2>
                <?= htmlspecialchars($selected_test['title']) ?>
            </h2>

            <div style="margin-top:10px;">

                <?php

                $status = $selected_test['status'];

                ?>

                <span class="status status-<?= htmlspecialchars($status) ?>">
                    <?= htmlspecialchars($status) ?>
                </span>

            </div>


            <div class="test-info">

                <div class="info-box">

                    <div class="info-label">
                        Duration
                    </div>

                    <div class="info-value">
                        <?= (int)$selected_test['duration_minutes'] ?> min
                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Total Marks
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars($selected_test['total_marks']) ?>
                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Negative Marks
                    </div>

                    <div class="info-value">
                        <?= htmlspecialchars($selected_test['negative_marks']) ?>
                    </div>

                </div>


                <div class="info-box">

                    <div class="info-label">
                        Start Time
                    </div>

                    <div class="info-value">

                        <?php if ($selected_test['start_time']): ?>

                            <?= htmlspecialchars($selected_test['start_time']) ?>

                        <?php else: ?>

                            —

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <div class="actions">


                <!-- PREPARE -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="prepare"
                    >

                    <button
                        type="submit"
                        class="prepare"
                        <?= $status !== 'draft' ? 'disabled' : '' ?>
                    >
                        Prepare Exam
                    </button>

                </form>


                <!-- START -->

                <form method="POST">

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="start"
                    >

                    <button
                        type="submit"
                        class="start"
                        <?= $status !== 'waiting' ? 'disabled' : '' ?>
                    >
                        Start Exam
                    </button>

                </form>


                <!-- END -->

                <form
                    method="POST"
                    onsubmit="return confirm('Are you sure you want to end this exam?');"
                >

                    <input
                        type="hidden"
                        name="test_id"
                        value="<?= (int)$selected_test['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="end"
                    >

                    <button
                        type="submit"
                        class="end"
                        <?= $status !== 'active' ? 'disabled' : '' ?>
                    >
                        End Exam
                    </button>

                </form>

            </div>

        </div>

    <?php else: ?>

        <div class="panel empty">

            Select a test above to control the examination.

        </div>

    <?php endif; ?>

</div>

</body>

</html>