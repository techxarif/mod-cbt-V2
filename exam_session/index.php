<?php
// exam_session/index.php — Candidate Waiting Room & Exam Gateway
require_once __DIR__ . '/../includes/bootstrap.php';
requireStudent();

$student_id = (int)$_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Candidate';
$student_uid  = $_SESSION['student_uid'] ?? '';

// P1 Fix: Scope exam lookup to student's actual assigned tests only
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id, t.title, t.duration_minutes, t.total_marks, t.negative_marks,
            t.status AS test_status, st.status AS attempt_status
        FROM tests t
        INNER JOIN student_tests st ON st.test_id = t.id
        WHERE st.student_id = ?
          AND t.status IN ('waiting', 'active')
        ORDER BY t.id DESC
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    $test = $stmt->fetch();
} catch (Throwable $e) {
    $ref = app_log('error', 'Waiting room query error: ' . $e->getMessage());
    $test = null;
}

// If already submitted, direct to finished screen
if ($test && $test['attempt_status'] === 'submitted') {
    redirect('finish.php?test_id=' . $test['id']);
}

$page_title = 'Examination Waiting Room — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= e($page_title) ?></title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/img/logo.png">
    <link rel="apple-touch-icon" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/exam.css">
    <link rel="stylesheet" href="../assets/css/responsive.css">
    <style>
        .waiting-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #063b68;
            color: #1f2937;
            font-family: Arial, Helvetica, sans-serif;
        }

        .waiting-logout-btn {
            margin-left: 12px;
            padding: 6px 12px;
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .waiting-logout-btn:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
            transform: translateY(-1px);
        }

        .main-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .waiting-card {
            background: #ffffff;
            border-radius: 14px;
            max-width: 560px;
            width: 100%;
            padding: 36px 30px;
            text-align: center;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.1);
            box-sizing: border-box;
            border-top: 4px solid #063b68;
        }

        .pulse-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 14px;
            background: rgba(2, 132, 199, 0.1);
            border: 1px solid rgba(2, 132, 199, 0.25);
            border-radius: 999px;
            color: #0284c7;
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: #0284c7;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(2, 132, 199, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
        }

        .exam-specs-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            text-align: left;
            font-size: 13px;
            line-height: 1.8;
            color: #334155;
            margin-bottom: 24px;
        }

        .exam-specs-box code {
            background: #e2e8f0;
            padding: 1px 4px;
            border-radius: 3px;
            font-family: Consolas, monospace;
            color: #0f172a;
        }

        .btn-enter-exam {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 24px;
            font-size: 15px;
            font-weight: 800;
            color: #ffffff;
            background: #063b68;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(6, 59, 104, 0.3);
            transition: all 0.2s ease;
        }

        .btn-enter-exam:hover {
            background: #042745;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(6, 59, 104, 0.4);
        }

        @media (max-width: 820px) {
            .waiting-logout-btn {
                margin-left: auto;
            }
        }

        @media (max-width: 480px) {
            .waiting-card {
                padding: 24px 18px;
            }
            .exam-specs-box {
                padding: 12px 14px;
                font-size: 12px;
            }
        }
    </style>
</head>
<body class="waiting-page">

<header class="header">
    <div class="brand">
        <div class="brand-logo-wrap">
            <img src="../assets/img/logo.png" alt="<?= e(APP_NAME) ?> Logo" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
            <div class="logo logo-fallback" style="display:none;">M</div>
        </div>
        <div class="brand-text">
            <div class="brand-name"><?= e(APP_NAME) ?></div>
            <div class="brand-subtitle">EXCELLENCE IN COMPUTER BASED TESTING</div>
        </div>
    </div>

    <div class="candidate-area">
        <div class="candidate-photo" title="Candidate Identity">👤</div>
        <div class="candidate-info">
            <div>
                <span class="candidate-label">Candidate</span> :
                <span class="candidate-value"><strong><?= e($student_name) ?></strong></span>
            </div>
            <div>
                <span class="candidate-label">Student UID</span> :
                <span class="candidate-value"><?= e($student_uid) ?></span>
            </div>
        </div>
        <a href="logout.php" class="waiting-logout-btn" title="Sign out of examination">
            <span>Logout</span> ➔
        </a>
    </div>
</header>

<main class="main-container">
    <div class="waiting-card">
        <?php if (!$test): ?>
            <div style="font-size: 48px; margin-bottom: 16px;">☕</div>
            <h2 style="font-size: 22px; font-weight: 800; margin-bottom: 10px; color: #063b68;">No Active Examination</h2>
            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-bottom: 20px;">
                You are not currently assigned to any scheduled examination, or the test has already concluded.
                Please check back later or contact your invigilator.
            </p>
            <div style="font-size: 12px; color: #94a3b8;">
                Logged in as <strong><?= e($student_name) ?></strong> (<?= e($student_uid) ?>)
            </div>
        <?php elseif ($test['test_status'] === 'waiting'): ?>
            <div class="pulse-badge">
                <span class="pulse-dot"></span>
                <span>EXAMINATION WAITING ROOM</span>
            </div>
            <h2 style="font-size: 24px; font-weight: 800; margin: 0 0 8px 0; color: #063b68;"><?= e($test['title']) ?></h2>
            <p style="color: #64748b; font-size: 13.5px; margin-bottom: 22px;">The examination will commence shortly. Please remain on this screen.</p>
            
            <div class="exam-specs-box">
                <div>⏱️ <strong>Duration:</strong> <?= (int)$test['duration_minutes'] ?> minutes</div>
                <div>📊 <strong>Scoring Scheme:</strong> +<?= (float)$test['total_marks'] ?> per correct / -<?= (float)$test['negative_marks'] ?> per wrong</div>
                <div>🔒 <strong>Rules:</strong> Fullscreen mode, automatic periodic answer saving.</div>
            </div>

            <p style="font-size: 12px; color: #64748b; margin: 0;">⚡ This screen auto-refreshes every 3 seconds to launch your exam as soon as it begins.</p>
        <?php elseif ($test['test_status'] === 'active'): ?>
            <div style="font-size: 48px; margin-bottom: 16px;">🚀</div>
            <h2 style="font-size: 24px; font-weight: 800; margin: 0 0 8px 0; color: #063b68;">Your Exam is Live!</h2>
            <p style="color: #64748b; font-size: 14px; margin-bottom: 24px;"><strong><?= e($test['title']) ?></strong> is active and awaiting your participation.</p>
            <a href="exam.php?test_id=<?= $test['id'] ?>" class="btn-enter-exam">
                <span>Enter Examination Now</span> ➔
            </a>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    © <?= date('Y') ?> <?= e(APP_NAME) ?> &nbsp;|&nbsp; Computer Based Testing Platform
</footer>

<script>
// Auto-poll exam status every 3 seconds to auto-transition when exam is activated
<?php if ($test && $test['test_status'] === 'waiting'): ?>
setInterval(async () => {
    try {
        const res = await fetch('../exam_control/status.php');
        const data = await res.json();
        if (data.status === 'active') {
            window.location.href = 'exam.php?test_id=' + data.test_id;
        }
    } catch (e) {}
}, 3000);
<?php endif; ?>
</script>

<script src="../assets/js/ui.js"></script>
</body>
</html>
