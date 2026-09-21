<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();


/*
|--------------------------------------------------------------------------
| Selected Test
|--------------------------------------------------------------------------
*/

$test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : 0;


/*
|--------------------------------------------------------------------------
| Get All Tests
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        title,
        status,
        duration_minutes,
        total_marks,
        negative_marks
    FROM tests
    ORDER BY id DESC
");

$tests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Selected Test Information
|--------------------------------------------------------------------------
*/

$selected_test = null;
$results = [];

if ($test_id > 0) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            title,
            status,
            duration_minutes,
            total_marks,
            negative_marks,
            start_time,
            end_time
        FROM tests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$test_id]);

    $selected_test = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Get Student Results
    |--------------------------------------------------------------------------
    */

    if ($selected_test) {

        $stmt = $pdo->prepare("
            SELECT
                st.id AS student_test_id,
                st.status,
                st.score,
                st.correct_answers,
                st.wrong_answers,
                st.unanswered,
                st.started_at,
                st.submitted_at,

                s.uid,
                s.name,
                s.mobile,
                s.date_of_birth

            FROM student_tests st

            INNER JOIN students s
                ON s.id = st.student_id

            WHERE st.test_id = ?

            ORDER BY
                st.score DESC,
                st.submitted_at ASC,
                s.uid ASC
        ");

        $stmt->execute([$test_id]);

        $results = $stmt->fetchAll();
    }
}


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total_students = count($results);

$submitted = 0;
$absent = 0;
$highest_score = null;

foreach ($results as $result) {

    if ($result['status'] === 'submitted') {
        $submitted++;
    }

    if ($result['status'] === 'absent') {
        $absent++;
    }

    if (
        $highest_score === null ||
        (float) $result['score'] > $highest_score
    ) {
        $highest_score = (float) $result['score'];
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results - MODUS CBT</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            color: #17202a;
        }

        .topbar {
            background: #111827;
            color: white;
            padding: 17px 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
        }

        .topbar-right {
            font-size: 14px;
            color: #d1d5db;
        }

        .container {
            max-width: 1250px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 20px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        h1 {
            margin-top: 0;
            margin-bottom: 8px;
        }

        h2 {
            margin-top: 0;
        }

        .subtitle {
            color: #6b7280;
            margin-bottom: 20px;
        }

        .test-selector {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        select {
            min-width: 320px;
            padding: 11px 13px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            font-size: 14px;
        }

        .btn {
            display: inline-block;
            padding: 11px 17px;
            border-radius: 8px;
            border: none;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-dark {
            background: #111827;
            color: white;
        }

        .btn-dark:hover {
            background: #000;
        }

        .btn-pdf {
            background: #111827;
            color: white;
            margin-top: 15px;
        }

        .btn-pdf:hover {
            background: #000;
        }

        .faculty-qr-btn {
            display: inline-block;
            padding: 11px 17px;
            border-radius: 8px;
            background: #111827;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-top: 15px;
            margin-left: 10px;
        }

        .faculty-qr-btn:hover {
            background: #000;
        }

        .test-info {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .info-box {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
            padding: 15px;
        }

        .info-label {
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 17px;
            font-weight: 700;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .stat-title {
            color: #6b7280;
            font-size: 12px;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 700;
            margin-top: 6px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #f9fafb;
            font-size: 12px;
            text-transform: uppercase;
            color: #4b5563;
        }

        td {
            font-size: 13px;
        }

        .student-name {
            font-weight: 700;
            color: #111827;
        }

        .rank, .score {
            font-weight: 700;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-submitted { background: #dcfce7; color: #166534; }
        .status-assigned { background: #fef3c7; color: #92400e; }
        .status-started { background: #dbeafe; color: #1e40af; }
        .status-absent { background: #fee2e2; color: #991b1b; }

        .empty {
            text-align: center;
            padding: 45px 20px;
            color: #6b7280;
        }

        @media (max-width: 900px) {
            .test-info, .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .test-info, .stats {
                grid-template-columns: 1fr;
            }

            select {
                min-width: 100%;
            }

            .test-selector {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>

<div class="topbar">
    <div class="brand">MODUS CBT</div>
    <div class="topbar-right">Results Dashboard</div>
</div>

<div class="container">
    <div class="card">
        <h1>Results</h1>
        <div class="subtitle">Select a test to view student performance.</div>

        <form method="GET" class="test-selector">
            <select name="test_id" required>
                <option value="">Select Test</option>
                <?php foreach ($tests as $test): ?>
                    <option value="<?= (int)$test['id'] ?>" <?= ($test_id === (int)$test['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($test['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-dark">View Results</button>
        </form>
    </div>

    <?php if ($selected_test): ?>
        <div class="card">
            <h2><?= htmlspecialchars($selected_test['title']) ?></h2>

            <div class="test-info">
                <div class="info-box">
                    <div class="info-label">Duration</div>
                    <div class="info-value"><?= (int) $selected_test['duration_minutes'] ?> min</div>
                </div>

                <div class="info-box">
                    <div class="info-label">Total Marks</div>
                    <div class="info-value"><?= htmlspecialchars($selected_test['total_marks']) ?></div>
                </div>

                <div class="info-box">
                    <div class="info-label">Negative Marks</div>
                    <div class="info-value"><?= htmlspecialchars($selected_test['negative_marks']) ?></div>
                </div>

                <div class="info-box">
                    <div class="info-label">Test Status</div>
                    <div class="info-value"><?= htmlspecialchars(ucfirst($selected_test['status'])) ?></div>
                </div>
            </div>

            <a href="result_pdf.php?test_id=<?= (int)$test_id ?>" target="_blank" class="btn btn-pdf">
                Download Result PDF
            </a>

            <?php if ($test_id > 0 && $selected_test['status'] === 'completed'): ?>
                <a href="faculty_qr.php?test_id=<?= (int) $test_id ?>" class="faculty-qr-btn" target="_blank">
                    ▣ Generate Faculty QR
                </a>
            <?php endif; ?>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-title">Students</div>
                <div class="stat-value"><?= $total_students ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Submitted</div>
                <div class="stat-value"><?= $submitted ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Absent</div>
                <div class="stat-value"><?= $absent ?></div>
            </div>

            <div class="stat">
                <div class="stat-title">Highest Score</div>
                <div class="stat-value">
                    <?= ($highest_score !== null) ? number_format($highest_score, 2) : '-' ?>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>Student Results</h2>

            <?php if (empty($results)): ?>
                <div class="empty">No students are assigned to this test yet.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student Name</th>
                                <th>Student UID</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th>Correct</th>
                                <th>Wrong</th>
                                <th>Unanswered</th>
                                <th>Score</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $rank = 1; foreach ($results as $result): ?>
                                <tr>
                                    <td class="rank"><?= $rank ?></td>
                                    <td class="student-name"><?= htmlspecialchars($result['name']) ?></td>
                                    <td><strong><?= htmlspecialchars($result['uid']) ?></strong></td>
                                    <td><?= htmlspecialchars($result['mobile']) ?></td>
                                    <td>
                                        <span class="status status-<?= htmlspecialchars($result['status']) ?>">
                                            <?= htmlspecialchars(ucfirst($result['status'])) ?>
                                        </span>
                                    </td>
                                    <td><?= (int) $result['correct_answers'] ?></td>
                                    <td><?= (int) $result['wrong_answers'] ?></td>
                                    <td><?= (int) $result['unanswered'] ?></td>
                                    <td class="score"><?= number_format((float) $result['score'], 2) ?></td>
                                    <td><?= !empty($result['submitted_at']) ? htmlspecialchars($result['submitted_at']) : '-' ?></td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="empty">Select a test above to view its results.</div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>