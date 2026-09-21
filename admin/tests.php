<?php

require_once __DIR__ . '/../includes/auth.php';
requireTeacher();

require_once __DIR__ . '/../includes/db.php';

$stmt = $pdo->query("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        negative_marks,
        start_time,
        end_time,
        status,
        created_at
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Tests - MODUS CBT</title>

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
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;

            margin-bottom: 25px;
        }

        h1 {
            margin: 0;
        }

        .button {
            display: inline-block;

            padding: 11px 16px;

            border-radius: 7px;

            background: #111;
            color: white;

            text-decoration: none;
        }

        .button.secondary {
            background: white;
            color: #111;
            border: 1px solid #ddd;
        }

        .buttons {
            display: flex;
            gap: 10px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            overflow-x: auto;

            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #fafafa;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            text-transform: capitalize;
        }

        .draft {
            background: #eee;
            color: #555;
        }

        .waiting {
            background: #fff3cd;
            color: #856404;
        }

        .active {
            background: #dff5e7;
            color: #176b38;
        }

        .completed {
            background: #e2e8f0;
            color: #334155;
        }

        .empty {
            text-align: center;
            padding: 50px;
            color: #777;
        }

        @media(max-width: 700px) {

            .container {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

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


    <div class="header">

        <div>

            <h1>Tests</h1>

            <p>
                Total Tests:
                <strong><?= count($tests) ?></strong>
            </p>

        </div>


        <div class="buttons">

            <a
                href="create_test.php"
                class="button"
            >
                + Create Test
            </a>

            <a
                href="dashboard.php"
                class="button secondary"
            >
                Dashboard
            </a>

        </div>

    </div>


    <div class="table-container">

        <?php if (count($tests) === 0): ?>

            <div class="empty">

                <h3>No tests created yet</h3>

                <p>
                    Create your first CBT test.
                </p>

            </div>

        <?php else: ?>

            <table>

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Test Name</th>
                        <th>Duration</th>
                        <th>Total Marks</th>
                        <th>Negative Marks</th>
                        <th>Start Time</th>
                        <th>Status</th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($tests as $test): ?>

                    <tr>

                        <td>
                            <?= htmlspecialchars($test['id']) ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($test['title']) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($test['duration_minutes']) ?>
                            min
                        </td>

                        <td>
                            <?= htmlspecialchars($test['total_marks']) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($test['negative_marks']) ?>
                        </td>

                        <td>

                            <?php if ($test['start_time']): ?>

                                <?= htmlspecialchars($test['start_time']) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                        <td>

                            <span
                                class="status <?= htmlspecialchars($test['status']) ?>"
                            >
                                <?= htmlspecialchars($test['status']) ?>
                            </span>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        <?php endif; ?>

    </div>


</div>

</body>

</html>