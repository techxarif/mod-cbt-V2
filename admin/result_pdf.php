<?php

session_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

requireTeacher();

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;


/*
|--------------------------------------------------------------------------
| Get Test ID
|--------------------------------------------------------------------------
*/

$test_id = isset($_GET['test_id'])
    ? (int) $_GET['test_id']
    : 0;

if ($test_id <= 0) {
    die('Invalid test ID.');
}


/*
|--------------------------------------------------------------------------
| Get Test
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        duration_minutes,
        total_marks,
        negative_marks,
        status,
        start_time,
        end_time
    FROM tests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$test_id]);

$test = $stmt->fetch();

if (!$test) {
    die('Test not found.');
}


/*
|--------------------------------------------------------------------------
| Get Student Results
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Calculate Summary
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
        (float)$result['score'] > $highest_score
    ) {
        $highest_score = (float)$result['score'];
    }
}


/*
|--------------------------------------------------------------------------
| Build HTML
|--------------------------------------------------------------------------
*/

$html = '

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<style>

@page {
    margin: 25px 30px;
}

body {

    font-family: DejaVu Sans, sans-serif;

    font-size: 11px;

    color: #222;

}

.header {

    text-align: center;

    border-bottom: 2px solid #111;

    padding-bottom: 12px;

    margin-bottom: 20px;

}

.logo {

    font-size: 25px;

    font-weight: bold;

    letter-spacing: 2px;

}

.subtitle {

    font-size: 11px;

    color: #666;

    margin-top: 5px;

}

.test-title {

    font-size: 18px;

    font-weight: bold;

    margin-top: 15px;

}

.info {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 20px;

}

.info td {

    padding: 7px;

    border: 1px solid #ddd;

}

.info-label {

    font-weight: bold;

    background: #f3f4f6;

}

.stats {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 20px;

}

.stat {

    width: 25%;

    text-align: center;

    padding: 10px;

    border: 1px solid #ddd;

}

.stat-title {

    font-size: 9px;

    color: #666;

}

.stat-value {

    font-size: 16px;

    font-weight: bold;

    margin-top: 4px;

}

.results {

    width: 100%;

    border-collapse: collapse;

}

.results th {

    background: #111;

    color: white;

    padding: 8px;

    font-size: 9px;

}

.results td {

    border: 1px solid #ddd;

    padding: 7px;

    font-size: 9px;

}

.results tr:nth-child(even) {

    background: #f8f8f8;

}

.rank {

    font-weight: bold;

    text-align: center;

}

.center {

    text-align: center;

}

.score {

    font-weight: bold;

    text-align: center;

}

.footer {

    margin-top: 25px;

    padding-top: 10px;

    border-top: 1px solid #ddd;

    text-align: center;

    font-size: 9px;

    color: #777;

}

</style>

</head>

<body>


<!-- HEADER -->

<div class="header">

    <div class="logo">
        MODUS CBT
    </div>

    <div class="subtitle">
        Computer Based Testing Platform
    </div>

    <div class="test-title">
        ' . htmlspecialchars($test['title']) . '
    </div>

</div>


<!-- TEST INFORMATION -->

<table class="info">

<tr>

<td class="info-label">
Duration
</td>

<td>
' . (int)$test['duration_minutes'] . ' minutes
</td>

<td class="info-label">
Total Marks
</td>

<td>
' . htmlspecialchars($test['total_marks']) . '
</td>

</tr>


<tr>

<td class="info-label">
Negative Marks
</td>

<td>
' . htmlspecialchars($test['negative_marks']) . '
</td>

<td class="info-label">
Status
</td>

<td>
' . htmlspecialchars(ucfirst($test['status'])) . '
</td>

</tr>

</table>


<!-- SUMMARY -->

<table class="stats">

<tr>

<td class="stat">

<div class="stat-title">
STUDENTS
</div>

<div class="stat-value">
' . $total_students . '
</div>

</td>


<td class="stat">

<div class="stat-title">
SUBMITTED
</div>

<div class="stat-value">
' . $submitted . '
</div>

</td>


<td class="stat">

<div class="stat-title">
ABSENT
</div>

<div class="stat-value">
' . $absent . '
</div>

</td>


<td class="stat">

<div class="stat-title">
HIGHEST SCORE
</div>

<div class="stat-value">
' .
(
    $highest_score !== null
        ? number_format($highest_score, 2)
        : '-'
)
. '
</div>

</td>

</tr>

</table>


<!-- RESULTS TABLE -->

<table class="results">

<thead>

<tr>

<th>
Rank
</th>

<th>
Student UID
</th>

<th>
Mobile
</th>

<th>
Status
</th>

<th>
Correct
</th>

<th>
Wrong
</th>

<th>
Unanswered
</th>

<th>
Score
</th>

<th>
Submitted
</th>

</tr>

</thead>

<tbody>
';


/*
|--------------------------------------------------------------------------
| Add Results
|--------------------------------------------------------------------------
*/

$rank = 1;

foreach ($results as $result) {

    $submitted_time = '-';

    if (!empty($result['submitted_at'])) {

        $submitted_time =
            htmlspecialchars(
                $result['submitted_at']
            );

    }


    $html .= '

    <tr>

        <td class="rank">
            ' . $rank . '
        </td>

        <td>
            ' . htmlspecialchars(
                $result['uid']
            ) . '
        </td>

        <td>
            ' . htmlspecialchars(
                $result['mobile']
            ) . '
        </td>

        <td class="center">
            ' . htmlspecialchars(
                ucfirst($result['status'])
            ) . '
        </td>

        <td class="center">
            ' . (int)$result['correct_answers'] . '
        </td>

        <td class="center">
            ' . (int)$result['wrong_answers'] . '
        </td>

        <td class="center">
            ' . (int)$result['unanswered'] . '
        </td>

        <td class="score">
            ' . number_format(
                (float)$result['score'],
                2
            ) . '
        </td>

        <td>
            ' . $submitted_time . '
        </td>

    </tr>

    ';

    $rank++;
}


$html .= '

</tbody>

</table>


<!-- FOOTER -->

<div class="footer">

    Generated by MODUS CBT

</div>


</body>

</html>
';


/*
|--------------------------------------------------------------------------
| Dompdf Configuration
|--------------------------------------------------------------------------
*/

$options = new Options();

$options->set(
    'defaultFont',
    'DejaVu Sans'
);

$options->set(
    'isRemoteEnabled',
    false
);


$dompdf = new Dompdf($options);


/*
|--------------------------------------------------------------------------
| Load HTML
|--------------------------------------------------------------------------
*/

$dompdf->loadHtml($html);


/*
|--------------------------------------------------------------------------
| A4 Landscape
|--------------------------------------------------------------------------
*/

$dompdf->setPaper(
    'A4',
    'landscape'
);


/*
|--------------------------------------------------------------------------
| Render
|--------------------------------------------------------------------------
*/

$dompdf->render();


/*
|--------------------------------------------------------------------------
| File Name
|--------------------------------------------------------------------------
*/

$safe_title = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $test['title']
);

$filename =
    'MODUS_CBT_' .
    $safe_title .
    '_Results.pdf';


/*
|--------------------------------------------------------------------------
| Download PDF
|--------------------------------------------------------------------------
*/

$dompdf->stream(
    $filename,
    [
        'Attachment' => true
    ]
);

exit;