<?php
// ============================================================
// MODUS CBT — Exam Control Cockpit & Live Monitor
// Was: admin/exam_control.php
// Now: exam_control/index.php
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$message = '';
$error   = '';

/*
|--------------------------------------------------------------------------
| Handle State Transitions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $test_id = int_param('test_id', 0, 'post');
    $action  = str_param('action', '', 'post');

    if ($test_id <= 0) {
        $error = 'Please select a valid test.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, title, status
                FROM tests
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$test_id]);
            $test_row = $stmt->fetch();

            if (!$test_row) {
                $pdo->rollBack();
                $error = 'Test not found.';
            } else {
                switch ($action) {
                    case 'prepare':
                        $stmt = $pdo->prepare("
                            UPDATE tests SET status = 'waiting'
                            WHERE id = ? AND status = 'draft'
                        ");
                        $stmt->execute([$test_id]);
                        if ($stmt->rowCount() > 0) {
                            $message = 'Exam prepared! Candidate waiting room is now open.';
                        } else {
                            $error = 'Test must be in DRAFT status to prepare.';
                        }
                        break;

                    case 'start':
                        // Auto-assign active students if not yet assigned
                        $pdo->prepare("
                            INSERT IGNORE INTO student_tests (student_id, test_id, status)
                            SELECT id, :tid, 'assigned' FROM students WHERE status = 'active'
                        ")->execute([':tid' => $test_id]);

                        $stmt = $pdo->prepare("
                            UPDATE tests
                            SET status = 'active', start_time = NOW()
                            WHERE id = ? AND status = 'waiting'
                        ");
                        $stmt->execute([$test_id]);
                        if ($stmt->rowCount() > 0) {
                            $message = 'Exam has STARTED! Countdown timer is active and candidates can answer questions.';
                        } else {
                            $error = 'Test must be in WAITING status to start.';
                        }
                        break;

                    case 'end':
                        $stmt = $pdo->prepare("
                            UPDATE tests
                            SET status = 'completed', end_time = NOW()
                            WHERE id = ? AND status = 'active'
                        ");
                        $stmt->execute([$test_id]);
                        if ($stmt->rowCount() > 0) {
                            $message = 'Exam concluded successfully. All submissions are locked.';
                        } else {
                            $error = 'Test is not currently active.';
                        }
                        break;

                    default:
                        $error = 'Invalid action requested.';
                }

                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $ref   = app_log('error', 'exam_control transition error: ' . $e->getMessage());
            $error = 'A system error occurred. Ref: ' . $ref;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load tests & Selected test info
|--------------------------------------------------------------------------
*/

$tests = get_all_tests();

$selected_test_id = int_param('test_id', 0, 'get');
if ($selected_test_id <= 0) {
    $selected_test_id = int_param('test_id', 0, 'post');
}

$selected_test = null;
$student_stats = ['total' => 0, 'submitted' => 0, 'started' => 0, 'assigned' => 0];
$student_roster = [];

if ($selected_test_id > 0) {
    $selected_test = get_test($selected_test_id);

    if ($selected_test) {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'submitted') AS submitted,
                SUM(status = 'started')   AS started,
                SUM(status = 'assigned')  AS assigned
            FROM student_tests
            WHERE test_id = ?
        ");
        $stmt->execute([$selected_test_id]);
        $row = $stmt->fetch();
        if ($row) {
            $student_stats = [
                'total'     => (int)($row['total'] ?? 0),
                'submitted' => (int)($row['submitted'] ?? 0),
                'started'   => (int)($row['started'] ?? 0),
                'assigned'  => (int)($row['assigned'] ?? 0),
            ];
        }

        // Fetch roster of assigned candidates
        $stmt = $pdo->prepare("
            SELECT st.id AS st_id, st.status AS exam_status, st.started_at, st.submitted_at, st.score,
                   s.id AS student_id, s.name, s.uid, s.mobile, s.date_of_birth
            FROM student_tests st
            JOIN students s ON s.id = st.student_id
            WHERE st.test_id = ?
            ORDER BY CASE st.status WHEN 'started' THEN 1 WHEN 'assigned' THEN 2 WHEN 'submitted' THEN 3 ELSE 4 END, s.name ASC
        ");
        $stmt->execute([$selected_test_id]);
        $student_roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$page_title = 'Exam Control — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'exam_control');
?>

<div class="page-content" style="max-width: 1200px; margin: 0 auto;">

    <!-- Page Header -->
    <div class="page-header" style="margin-bottom: 20px;">
        <div>
            <h1 class="page-title">Exam Control Cockpit</h1>
            <p class="page-subtitle">Real-time examination lifecycle orchestration and live candidate monitor</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <?php if ($selected_test): ?>
                <a href="index.php?test_id=<?= (int)$selected_test['id'] ?>" class="btn btn-secondary" title="Refresh Live Data">
                    🔄 Refresh
                </a>
                <a href="../results/index.php?test_id=<?= (int)$selected_test['id'] ?>" class="btn btn-secondary">
                    ▤ Results
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <?= alert_box('success', $message) ?>
    <?php endif; ?>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <!-- =========================================================
         EXAM SELECTOR DROPDOWN CARD
         ========================================================= -->
    <div class="card" style="padding: 18px 24px; margin-bottom: 22px; background: linear-gradient(145deg, rgba(17,26,37,0.95), rgba(11,18,26,0.92)); border: 1px solid rgba(255,255,255,0.1);">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <div style="flex: 1; min-width: 260px;">
                <label class="form-label" style="margin-bottom: 6px; color: var(--green); font-weight: 800;">Select Examination</label>
                <select id="examSelector" class="form-select" onchange="onExamSelect(this.value)">
                    <option value="">— Select an examination to manage —</option>
                    <?php foreach ($tests as $t): ?>
                        <option
                            value="<?= (int)$t['id'] ?>"
                            <?= $selected_test_id === (int)$t['id'] ? 'selected' : '' ?>
                        >
                            <?= e($t['title']) ?> (Status: <?= strtoupper(e($t['status'])) ?> | <?= (int)$t['duration_minutes'] ?> mins)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($selected_test): ?>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 18px;">
                    <span class="badge badge-<?= e($selected_test['status']) ?>" style="font-size: 12px; padding: 8px 14px;">
                        <?= e(strtoupper(status_label($selected_test['status']))) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_test): ?>

        <!-- =========================================================
             EXAM COCKPIT & LIVE METRICS
             ========================================================= -->
        <div class="card" style="border: 1px solid rgba(32, 229, 150, 0.25); background: linear-gradient(145deg, rgba(14,21,31,0.96), rgba(9,14,21,0.92)); margin-bottom: 22px; position: relative; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                        <h2 style="font-size: 22px; font-weight: 850; color: #fff; margin: 0;"><?= e($selected_test['title']) ?></h2>
                        <span class="badge badge-<?= e($selected_test['status']) ?>"><?= e(status_label($selected_test['status'])) ?></span>
                    </div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap; font-size: 13px; color: var(--muted); margin-top: 6px;">
                        <span>⏱ <strong><?= (int)$selected_test['duration_minutes'] ?></strong> Minutes</span>
                        <span>🎯 <strong><?= (float)$selected_test['total_marks'] ?></strong> Total Marks</span>
                        <span>➖ <strong>-<?= (float)$selected_test['negative_marks'] ?></strong> Penalty</span>
                    </div>
                </div>

                <!-- Action Button Form -->
                <div>
                    <form method="POST" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="test_id" value="<?= (int)$selected_test['id'] ?>">

                        <?php if ($selected_test['status'] === 'draft'): ?>
                            <button type="submit" name="action" value="prepare" class="btn btn-warning" style="padding: 10px 20px; font-size: 13px;">
                                ▶ Prepare Exam (Open Waiting Room)
                            </button>
                        <?php elseif ($selected_test['status'] === 'waiting'): ?>
                            <button type="submit" name="action" value="start" class="btn btn-success" style="padding: 10px 20px; font-size: 13px;"
                                onclick="return confirm('Start the exam now? All active candidates will be assigned and the countdown timer will begin.')">
                                ⚡ Start Exam Now (Launch CBT)
                            </button>
                        <?php elseif ($selected_test['status'] === 'active'): ?>
                            <button type="submit" name="action" value="end" class="btn btn-danger" style="padding: 10px 20px; font-size: 13px;"
                                onclick="return confirm('End the exam now? Any unfinished candidates will be submitted and locked.')">
                                ⏹ Conclude & End Exam
                            </button>
                        <?php elseif ($selected_test['status'] === 'completed'): ?>
                            <a href="../results/index.php?test_id=<?= (int)$selected_test['id'] ?>" class="btn btn-primary" style="padding: 10px 20px; font-size: 13px;">
                                ▤ View Exam Results & PDF
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Lifecycle Stepper Indicator -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 22px; background: rgba(0,0,0,0.3); padding: 12px; border-radius: 12px; text-align: center; font-size: 11.5px; font-weight: 750;">
                <div style="padding: 8px; border-radius: 8px; <?= $selected_test['status'] === 'draft' ? 'background: rgba(137,149,166,0.25); color:#fff; border: 1px solid var(--muted);' : 'color:var(--muted);' ?>">
                    1. Draft Setup
                </div>
                <div style="padding: 8px; border-radius: 8px; <?= $selected_test['status'] === 'waiting' ? 'background: rgba(255,180,84,0.25); color:var(--orange); border: 1px solid var(--orange);' : 'color:var(--muted);' ?>">
                    2. Waiting Lobby
                </div>
                <div style="padding: 8px; border-radius: 8px; <?= $selected_test['status'] === 'active' ? 'background: rgba(32,229,150,0.25); color:var(--green); border: 1px solid var(--green);' : 'color:var(--muted);' ?>">
                    3. Live CBT
                </div>
                <div style="padding: 8px; border-radius: 8px; <?= $selected_test['status'] === 'completed' ? 'background: rgba(85,168,255,0.25); color:var(--blue); border: 1px solid var(--blue);' : 'color:var(--muted);' ?>">
                    4. Completed
                </div>
            </div>

            <!-- Live KPI Stats Grid -->
            <div class="stats-grid" style="margin-bottom: 0;">
                <div class="stat-card">
                    <div class="stat-value"><?= $student_stats['total'] ?></div>
                    <div class="stat-label">Assigned Candidates</div>
                </div>
                <div class="stat-card" style="border-color: rgba(32,229,150,0.3);">
                    <div class="stat-value" style="color: var(--green);"><?= $student_stats['started'] ?></div>
                    <div class="stat-label">In Progress (Active)</div>
                </div>
                <div class="stat-card" style="border-color: rgba(85,168,255,0.3);">
                    <div class="stat-value" style="color: var(--blue);"><?= $student_stats['submitted'] ?></div>
                    <div class="stat-label">Submitted</div>
                </div>
                <div class="stat-card" style="border-color: rgba(255,180,84,0.3);">
                    <div class="stat-value" style="color: var(--orange);"><?= $student_stats['assigned'] ?></div>
                    <div class="stat-label">Waiting in Lobby</div>
                </div>
            </div>
        </div>

        <!-- =========================================================
             LIVE CANDIDATE ROSTER & PROGRESS MONITOR
             ========================================================= -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h3 style="font-size: 16px; font-weight: 800; color: #fff; margin: 0;">Live Candidate Roster</h3>
                    <p style="font-size: 12px; color: var(--muted); margin: 3px 0 0;">Monitoring candidate sessions and examination statuses</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="text" id="rosterSearch" placeholder="Search candidate..." class="form-input" style="width: 220px; padding: 7px 12px; font-size: 12px;" oninput="filterRoster(this.value)">
                    <span style="font-size: 12px; color: var(--muted);">
                        Total: <strong><?= count($student_roster) ?></strong>
                    </span>
                </div>
            </div>

            <div class="data-table-wrapper" style="border: none;">
                <table class="data-table" id="rosterTable">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>UID / Roll No</th>
                            <th>Mobile</th>
                            <th>Default Pass (DOB)</th>
                            <th>Status</th>
                            <th>Score</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($student_roster)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 36px; color: var(--muted);">
                                    No students assigned to this examination yet.
                                    <?php if ($selected_test['status'] === 'waiting'): ?>
                                        <br><small style="color: var(--green);">Active students will automatically be assigned when you click <strong>Start Exam</strong>.</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($student_roster as $r): ?>
                                <?php
                                $dobPass = !empty($r['date_of_birth']) ? date('dmY', strtotime($r['date_of_birth'])) : 'Student@123';
                                ?>
                                <tr class="roster-row" data-search="<?= strtolower(e($r['name'] . ' ' . $r['uid'] . ' ' . $r['mobile'])) ?>">
                                    <td>
                                        <strong style="color: #fff;"><?= e($r['name']) ?></strong>
                                    </td>
                                    <td>
                                        <code style="background: rgba(255,255,255,0.06); padding: 3px 8px; border-radius: 6px; color: var(--green); font-size: 12px; font-weight: 700;">
                                            <?= e($r['uid']) ?>
                                        </code>
                                    </td>
                                    <td><?= e($r['mobile'] ?? '—') ?></td>
                                    <td>
                                        <code style="background: rgba(255,255,255,0.04); padding: 2px 6px; border-radius: 4px; color: #a5f3fc; font-size: 11px;">
                                            <?= e($dobPass) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= e($r['exam_status']) ?>">
                                            <?= e(status_label($r['exam_status'])) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: <?= $r['score'] !== null ? 'var(--green)' : 'var(--muted)' ?>;">
                                        <?= $r['score'] !== null ? format_score($r['score']) : '—' ?>
                                    </td>
                                    <td>
                                        <?php if ($r['exam_status'] === 'submitted'): ?>
                                            <a href="../results/student.php?student_id=<?= (int)$r['student_id'] ?>&test_id=<?= (int)$selected_test['id'] ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 10px;">
                                                View Score
                                            </a>
                                        <?php else: ?>
                                            <span style="font-size: 11px; color: var(--muted);">In Lobby / Progress</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php else: ?>

        <!-- =========================================================
             EMPTY STATE / CHOOSE AN EXAM
             ========================================================= -->
        <div class="card" style="text-align: center; padding: 50px 24px; border: 1px dashed var(--line2);">
            <div style="font-size: 40px; margin-bottom: 14px;">🎯</div>
            <h2 style="font-size: 20px; font-weight: 850; color: #fff; margin-bottom: 8px;">Select an Examination Above</h2>
            <p style="font-size: 13.5px; color: var(--muted); max-width: 480px; margin: 0 auto 20px;">
                Choose an exam from the dropdown list to prepare waiting rooms, start active timers, and monitor student test progress in real time.
            </p>
            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <?php foreach (array_slice($tests, 0, 4) as $t): ?>
                    <a href="index.php?test_id=<?= (int)$t['id'] ?>" class="btn btn-secondary" style="font-size: 12px;">
                        <?= e($t['title']) ?> <span class="badge badge-<?= e($t['status']) ?>" style="margin-left: 6px; font-size: 9px;"><?= e($t['status']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    <?php endif; ?>

</div>

<script>
function onExamSelect(val) {
    if (val) {
        window.location.href = 'index.php?test_id=' + encodeURIComponent(val);
    } else {
        window.location.href = 'index.php';
    }
}

function filterRoster(query) {
    var term = (query || '').toLowerCase().trim();
    var rows = document.querySelectorAll('.roster-row');
    rows.forEach(function(row) {
        var text = row.getAttribute('data-search') || '';
        if (term === '' || text.indexOf(term) !== -1) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}
</script>

<?php layout_footer(); ?>
