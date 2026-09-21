<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');

    $duration = (int)($_POST['duration_minutes'] ?? 0);

    $totalMarks = (float)($_POST['total_marks'] ?? 0);

    $negativeMarks = (float)($_POST['negative_marks'] ?? 0);


    if ($title === '') {

        $error = 'Please enter a test name.';

    } elseif ($duration <= 0) {

        $error = 'Duration must be greater than 0.';

    } elseif ($totalMarks < 0) {

        $error = 'Total marks cannot be negative.';

    } elseif ($negativeMarks < 0) {

        $error = 'Negative marks cannot be negative.';

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO tests
            (
                title,
                duration_minutes,
                total_marks,
                negative_marks,
                status
            )
            VALUES (?, ?, ?, ?, 'draft')
        ");

        $stmt->execute([
            $title,
            $duration,
            $totalMarks,
            $negativeMarks
        ]);

        header('Location: tests.php');
        exit;
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

    <title>Create Test - MODUS CBT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #111;
        }

        .topbar {
            height: 60px;
            background: #111;
            color: white;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 0 25px;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
        }

        .topbar a {
            color: white;
            text-decoration: none;
        }

        .container {
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .box {
            background: white;
            padding: 30px;
            border-radius: 12px;

            box-shadow:
                0 5px 20px rgba(0,0,0,0.05);
        }

        h1 {
            margin-top: 0;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 7px;

            font-weight: 600;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;

            border: 1px solid #ddd;
            border-radius: 7px;

            font-size: 15px;
        }

        input:focus {
            outline: none;
            border-color: #111;
        }

        .error {
            background: #fff0f0;
            color: #a00000;

            padding: 12px;
            border-radius: 7px;

            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            margin-top: 25px;
        }

        button,
        .back {
            padding: 12px 18px;
            border-radius: 7px;

            text-decoration: none;
            font-size: 14px;
        }

        button {
            background: #111;
            color: white;
            border: none;
            cursor: pointer;
        }

        .back {
            background: white;
            color: #111;
            border: 1px solid #ddd;
        }

    </style>

</head>

<body>


<div class="topbar">

    <div class="logo">
        MODUS CBT
    </div>

    <div>

        <a href="dashboard.php">
            Dashboard
        </a>

        &nbsp; | &nbsp;

        <a href="../logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">

    <div class="box">

        <h1>Create Test</h1>

        <div class="subtitle">
            Create the basic test configuration first.
            Questions will be added separately.
        </div>


        <?php if ($error): ?>

            <div class="error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <label>
                Test Name
            </label>

            <input
                type="text"
                name="title"
                placeholder="Example: NEET Biology Minor Test 01"
                required
            >


            <label>
                Duration (minutes)
            </label>

            <input
                type="number"
                name="duration_minutes"
                value="60"
                min="1"
                required
            >


            <label>
                Total Marks
            </label>

            <input
                type="number"
                name="total_marks"
                value="100"
                min="0"
                step="0.01"
                required
            >


            <label>
                Negative Marks Per Wrong Answer
            </label>

            <input
                type="number"
                name="negative_marks"
                value="0"
                min="0"
                step="0.01"
                required
            >


            <div class="buttons">

                <button type="submit">
                    Create Test
                </button>

                <a
                    href="tests.php"
                    class="back"
                >
                    Cancel
                </a>

            </div>


        </form>

    </div>

</div>

</body>

</html>