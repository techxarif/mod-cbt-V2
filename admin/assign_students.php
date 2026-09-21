<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| Handle assignment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $test_id = isset($_POST['test_id'])
        ? (int) $_POST['test_id']
        : 0;

    if ($test_id <= 0) {

        $error = "Please select a test.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Check test
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    title,
                    status
                FROM tests
                WHERE id = ?
                LIMIT 1
            ");

            $stmt->execute([$test_id]);

            $test = $stmt->fetch();

            if (!$test) {

                throw new Exception(
                    "Selected test does not exist."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Get all active students
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->query("
                SELECT id
                FROM students
                WHERE status = 'active'
                ORDER BY id ASC
            ");

            $students = $stmt->fetchAll();


            if (!$students) {

                throw new Exception(
                    "No active students found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Assign students
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            $insert = $pdo->prepare("
                INSERT IGNORE INTO student_tests (
                    student_id,
                    test_id,
                    status
                )
                VALUES (
                    ?,
                    ?,
                    'assigned'
                )
            ");

            $assigned = 0;

            foreach ($students as $student) {

                $insert->execute([
                    $student['id'],
                    $test_id
                ]);

                if ($insert->rowCount() > 0) {
                    $assigned++;
                }
            }

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            $message =
                $assigned .
                " student(s) assigned to \"" .
                $test['title'] .
                "\" successfully.";

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
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
        status
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Assignment counts
|--------------------------------------------------------------------------
*/

$assignment_counts = [];

$stmt = $pdo->query("
    SELECT
        test_id,
        COUNT(*) AS total_students
    FROM student_tests
    GROUP BY test_id
");

foreach ($stmt->fetchAll() as $row) {

    $assignment_counts[$row['test_id']] =
        (int)$row['total_students'];
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
    Assign Students - MODUS CBT
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #111827;
}

.topbar {
    background: #111827;
    color: white;
    padding: 18px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logo {
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
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
}

label {
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
}

select {
    width: 100%;
    padding: 13px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    background: white;
}

button {
    margin-top: 18px;
    border: none;
    background: #2563eb;
    color: white;
    padding: 13px 22px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 15px;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
}

th {
    background: #f9fafb;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
}

.draft {
    background: #e5e7eb;
    color: #374151;
}

.waiting {
    background: #fef3c7;
    color: #92400e;
}

.active {
    background: #dcfce7;
    color: #166534;
}

.completed {
    background: #dbeafe;
    color: #1e40af;
}

</style>

</head>

<body>

<div class="topbar">

    <div class="logo">
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

    <h1>Assign Students</h1>

    <div class="subtitle">
        Assign all active students to an examination.
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

        <form method="POST">

            <label for="test_id">
                Select Test
            </label>

            <select
                name="test_id"
                id="test_id"
                required
            >

                <option value="">
                    -- Select Test --
                </option>

                <?php foreach ($tests as $test): ?>

                    <option
                        value="<?= (int)$test['id'] ?>"
                    >

                        <?= htmlspecialchars($test['title']) ?>

                        —
                        <?= htmlspecialchars(strtoupper($test['status'])) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button type="submit">

                Assign All Active Students

            </button>

        </form>

    </div>


    <div class="panel">

        <h2>
            Assignment Overview
        </h2>

        <table>

            <thead>

                <tr>

                    <th>
                        Test
                    </th>

                    <th>
                        Duration
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Assigned Students
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if ($tests): ?>

                <?php foreach ($tests as $test): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($test['title']) ?>
                        </td>

                        <td>
                            <?= (int)$test['duration_minutes'] ?>
                            min
                        </td>

                        <td>

                            <span
                                class="badge <?= htmlspecialchars($test['status']) ?>"
                            >

                                <?= htmlspecialchars(
                                    strtoupper($test['status'])
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <?= $assignment_counts[$test['id']] ?? 0 ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="4"
                        style="text-align:center;"
                    >
                        No tests found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>

</html>