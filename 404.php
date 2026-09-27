<?php
// 404.php — MODUS CBT Custom 404 Not Found Page
require_once __DIR__ . '/includes/bootstrap.php';

http_response_code(404);
$base = app_base_url();
$app_name = defined('APP_NAME') ? APP_NAME : 'MODUS CBT';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | <?= e($app_name) ?></title>
    <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $base ?>assets/css/responsive.css">
    <style>
        .error-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 24px;
        }
        .error-card {
            background: linear-gradient(145deg, rgba(14, 21, 31, 0.95), rgba(9, 14, 21, 0.92));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 48px 36px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 70px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            animation: cardIn 0.6s var(--ease) both;
            position: relative;
            overflow: hidden;
        }
        .error-card:before {
            content: "";
            position: absolute;
            width: 160px;
            height: 160px;
            top: -80px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--green);
            filter: blur(75px);
            opacity: 0.15;
            pointer-events: none;
        }
        .error-code {
            font-size: 88px;
            font-weight: 950;
            line-height: 1;
            letter-spacing: -2px;
            background: linear-gradient(135deg, var(--green), #55a8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
        }
        .error-title {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 12px;
        }
        .error-desc {
            font-size: 13.5px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .error-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<div class="background">
    <div class="grid"></div>
    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>
</div>

<div class="error-page">
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-desc">
            The page or examination session you are looking for does not exist, has been moved, or the link is invalid.
        </p>
        <div class="error-actions">
            <?php if (teacherLoggedIn()): ?>
                <a href="<?= $base ?>dashboard/index.php" class="btn btn-primary" style="font-weight: 800;">
                    ⌂ Teacher Dashboard
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ← Go Back
                </a>
            <?php elseif (studentLoggedIn()): ?>
                <a href="<?= $base ?>exam_session/index.php" class="btn btn-primary" style="font-weight: 800;">
                    ▶ Return to Exam Lobby
                </a>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ← Go Back
                </a>
            <?php else: ?>
                <a href="<?= $base ?>index.php" class="btn btn-primary" style="font-weight: 800;">
                    ⌂ Return to Home
                </a>
                <a href="<?= $base ?>exam_session/login.php" class="btn btn-secondary">
                    Candidate Login
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
