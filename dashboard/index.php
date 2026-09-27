<?php

// ============================================================
// MODUS CBT — Dashboard
// Was: admin/dashboard.php
// Now: dashboard/index.php
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';

requireTeacher();

$teacher = currentTeacher();

try {
    $stats_row = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM students WHERE status = 'active') AS total_students,
            (SELECT COUNT(*) FROM tests)                             AS total_tests,
            (SELECT COUNT(*) FROM tests WHERE status = 'completed') AS completed_tests,
            (SELECT COUNT(*) FROM questions)                        AS total_questions,
            (SELECT COUNT(*) FROM student_tests WHERE status = 'submitted') AS total_submissions
    ")->fetch();

    $total_students   = (int) ($stats_row['total_students']   ?? 0);
    $total_tests      = (int) ($stats_row['total_tests']      ?? 0);
    $completed_tests  = (int) ($stats_row['completed_tests']  ?? 0);
    $total_questions  = (int) ($stats_row['total_questions']  ?? 0);
    $total_submissions= (int) ($stats_row['total_submissions'] ?? 0);

    // Current exam (waiting or active)
    $current_exam = $pdo->query("
        SELECT id, title, duration_minutes, total_marks, status
        FROM tests
        WHERE status IN ('waiting', 'active')
        ORDER BY CASE WHEN status = 'active' THEN 0 ELSE 1 END, id DESC
        LIMIT 1
    ")->fetch();

    $current_stats = ['assigned' => 0, 'submitted' => 0, 'started' => 0];

    if ($current_exam) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*)                      AS assigned,
                SUM(status = 'submitted')     AS submitted,
                SUM(status = 'started')       AS started
            FROM student_tests
            WHERE test_id = ?
        ");
        $stmt->execute([(int)$current_exam['id']]);
        $r = $stmt->fetch();
        $current_stats = [
            'assigned'  => (int) ($r['assigned']  ?? 0),
            'submitted' => (int) ($r['submitted']  ?? 0),
            'started'   => (int) ($r['started']    ?? 0),
        ];
    }

    // Recent tests
    $recent_tests = $pdo->query("
        SELECT
            t.id, t.title, t.duration_minutes, t.status,
            COUNT(st.id) AS student_count
        FROM tests t
        LEFT JOIN student_tests st ON st.test_id = t.id
        GROUP BY t.id, t.title, t.duration_minutes, t.status
        ORDER BY t.id DESC
        LIMIT 6
    ")->fetchAll();

} catch (Throwable $e) {
    $ref = app_log('error', 'dashboard error: ' . $e->getMessage());
    abort_html(500, 'Dashboard Error', 'Unable to load dashboard data. Ref: ' . $ref);
}

$page_title = 'Dashboard — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'dashboard');
?>

<div class="page-content">

    <!-- Page Header with Live Server Time -->
    <div class="page-header dashboard-header">
        <div class="dashboard-header-info">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Examination monitoring & telemetry overview</p>
        </div>
        <div class="server-time-card" id="serverClockCard" title="Live Server Clock">
            <div class="server-time-meta">
                <div class="server-time-header">
                    <span class="pulse-beacon"></span>
                    <span class="server-time-tag">SERVER TIME</span>
                </div>
                <div class="server-time-tz">IST (UTC+05:30)</div>
            </div>
            <div class="server-time-display" id="serverClockText">
                <?= date('D, d M Y — H:i:s') ?> IST
            </div>
        </div>
    </div>

    <!-- Stats Cards Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_tests ?></div>
            <div class="stat-label">Total Tests</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $completed_tests ?></div>
            <div class="stat-label">Completed Tests</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_questions ?></div>
            <div class="stat-label">Questions</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $total_submissions ?></div>
            <div class="stat-label">Submissions</div>
        </div>
    </div>

    <!-- =========================================================
         CURRENT EXAM (Enhanced High-Fidelity UI)
         ========================================================= -->
    <?php if ($current_exam): ?>
    <?php
        $totalAssigned = max(1, $current_stats['assigned']);
        $submittedPct  = min(100, round(($current_stats['submitted'] / $totalAssigned) * 100));
        $startedPct    = min(100, round(($current_stats['started'] / $totalAssigned) * 100));
    ?>
    <div class="card current-exam-card" style="border: 1px solid rgba(32, 229, 150, 0.35); background: linear-gradient(145deg, rgba(14,21,31,0.96), rgba(9,14,21,0.92)); position: relative; overflow: hidden; padding: 24px; margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 18px;">
            <div>
                <div style="display: inline-flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 800; color: var(--green); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;">
                    <span class="pulse-beacon"></span>
                    Current Ongoing Examination
                </div>
                <h2 style="font-size: 22px; font-weight: 850; color: #fff; margin: 0 0 8px 0;">
                    <?= e($current_exam['title']) ?>
                </h2>
                <div style="display: flex; gap: 12px; font-size: 12.5px; color: var(--muted); align-items: center; flex-wrap: wrap;">
                    <span>⏱ <strong><?= (int)$current_exam['duration_minutes'] ?></strong> mins</span>
                    <span>🎯 <strong><?= (float)$current_exam['total_marks'] ?></strong> marks</span>
                    <span class="badge badge-<?= e($current_exam['status']) ?>"><?= e(status_label($current_exam['status'])) ?></span>
                </div>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="../exam_control/index.php?test_id=<?= (int)$current_exam['id'] ?>" class="btn btn-primary" style="padding: 10px 20px; font-size: 13px; font-weight: 800;">
                    ⚙ Manage Exam Control →
                </a>
            </div>
        </div>

        <!-- Telemetry Progress Bar -->
        <div style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--muted); margin-bottom: 7px;">
                <span>Candidate Progress: <strong style="color: #fff;"><?= $current_stats['submitted'] ?> of <?= $current_stats['assigned'] ?> submitted (<?= $submittedPct ?>%)</strong></span>
                <span>Active Answering: <strong style="color: var(--green);"><?= $current_stats['started'] ?></strong></span>
            </div>
            <div style="height: 8px; background: rgba(255,255,255,0.06); border-radius: 999px; overflow: hidden; display: flex;">
                <div style="width: <?= $submittedPct ?>%; background: linear-gradient(90deg, #20e596, #0dbb76); transition: width 0.5s ease;" title="Submitted"></div>
                <div style="width: <?= $startedPct ?>%; background: linear-gradient(90deg, #55a8ff, #3b82f6); transition: width 0.5s ease;" title="In Progress"></div>
            </div>
        </div>

        <!-- Metric Counters -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px;">
            <div style="background: rgba(255,255,255,0.02); padding: 12px 14px; border-radius: 10px; border: 1px solid var(--line);">
                <div style="font-size: 10px; color: var(--muted); text-transform: uppercase; font-weight: 750;">Total Assigned</div>
                <div style="font-size: 22px; font-weight: 850; color: #fff; margin-top: 2px;"><?= $current_stats['assigned'] ?></div>
            </div>
            <div style="background: rgba(32,229,150,0.04); padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(32,229,150,0.25);">
                <div style="font-size: 10px; color: var(--green); text-transform: uppercase; font-weight: 750;">In Progress (Active)</div>
                <div style="font-size: 22px; font-weight: 850; color: var(--green); margin-top: 2px;"><?= $current_stats['started'] ?></div>
            </div>
            <div style="background: rgba(85,168,255,0.04); padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(85,168,255,0.25);">
                <div style="font-size: 10px; color: var(--blue); text-transform: uppercase; font-weight: 750;">Submitted (Done)</div>
                <div style="font-size: 22px; font-weight: 850; color: var(--blue); margin-top: 2px;"><?= $current_stats['submitted'] ?></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- =========================================================
         RECENT TESTS TABLE (Pill Action Buttons)
         ========================================================= -->
    <div class="card" style="padding: 0; overflow: hidden;">
        <div style="padding: 18px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
            <h2 class="card-title" style="margin: 0; font-size: 16px;">Recent Tests</h2>
            <a href="../tests/create.php" class="btn btn-secondary btn-sm" style="font-size: 12px;">+ Create Test</a>
        </div>
        <div class="data-table-wrapper" style="border: none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Students</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tests as $t): ?>
                    <tr>
                        <td>
                            <strong style="color: #fff; font-size: 13.5px;"><?= e($t['title']) ?></strong>
                        </td>
                        <td><?= (int)$t['duration_minutes'] ?> min</td>
                        <td><?= (int)$t['student_count'] ?> enrolled</td>
                        <td>
                            <span class="badge badge-<?= e($t['status']) ?>"><?= e(status_label($t['status'])) ?></span>
                        </td>
                        <td>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <a href="../results/index.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 5px 12px; font-size: 11.5px; font-weight: 700;">
                                    📊 Results
                                </a>
                                <a href="../exam_control/index.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-primary btn-sm" style="padding: 5px 12px; font-size: 11.5px; font-weight: 750;">
                                    ⚙ Control
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent_tests): ?>
                    <tr><td colspan="5" style="text-align: center; padding: 36px; color: var(--muted);">No tests created yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
// Live Server Time Ticking Clock (Indian Standard Time - IST 24-Hour)
(function() {
    var serverEpoch = <?= time() ?> * 1000;
    var clientStart = Date.now();
    var clockEl = document.getElementById('serverClockText');

    function updateClock() {
        var elapsed = Date.now() - clientStart;
        var current = new Date(serverEpoch + elapsed);
        
        try {
            var parts = new Intl.DateTimeFormat('en-IN', {
                timeZone: 'Asia/Kolkata',
                weekday: 'short',
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            }).formatToParts(current);
            var m = {};
            parts.forEach(function(p) { m[p.type] = p.value; });
            if (clockEl) {
                clockEl.textContent = m.weekday + ', ' + m.day + ' ' + m.month + ' ' + m.year + ' — ' + m.hour + ':' + m.minute + ':' + m.second + ' IST';
            }
        } catch (e) {
            if (clockEl) {
                clockEl.textContent = current.toLocaleTimeString('en-GB', { hour12: false }) + ' IST';
            }
        }
    }
    setInterval(updateClock, 1000);
})();
</script>

<?php layout_footer(); ?>
