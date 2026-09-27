<?php
// results/index.php — Results Overview & Leaderboards
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('test_id', 0);
$tests   = get_all_tests();

if ($test_id <= 0 && !empty($tests)) {
    $test_id = (int)$tests[0]['id'];
}

$selected_test = $test_id > 0 ? get_test($test_id) : null;
$results = [];
$stats   = null;

if ($selected_test) {
    $results = get_test_results($test_id);
    $stats   = result_stats($results);
}

$page_title = 'Examination Results — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'results');
?>

<style>
/* ── Restart modal overlay ─────────────────────────────────── */
#restartOverlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.65);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}
#restartOverlay.active { display: flex; }

.restart-modal {
    background: #0f172a;
    border: 1px solid #1e293b;
    border-radius: 16px;
    width: min(440px, 94vw);
    padding: 32px 28px 28px;
    box-shadow: 0 25px 60px rgba(0,0,0,.75);
    animation: slideUpModal .28s cubic-bezier(.4,0,.2,1);
    position: relative;
    text-align: center;
}
@keyframes slideUpModal {
    from { transform: translateY(28px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
.restart-icon {
    font-size: 46px;
    line-height: 1;
    margin-bottom: 14px;
    display: block;
    animation: pulse-warn 1.8s ease-in-out infinite;
}
@keyframes pulse-warn {
    0%,100% { transform: scale(1); }
    50%      { transform: scale(1.12); }
}
.restart-modal h3 {
    font-size: 20px;
    font-weight: 800;
    color: #f1f5f9;
    margin: 0 0 8px;
}
.restart-modal p {
    font-size: 13px;
    color: #94a3b8;
    margin: 0 0 22px;
    line-height: 1.6;
}
.restart-type-label {
    font-size: 11.5px;
    font-weight: 700;
    color: #ef4444;
    letter-spacing: .8px;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: block;
}
.restart-input-wrap { margin-bottom: 20px; }
.restart-input-wrap input {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 16px;
    border: 2px solid #334155;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: 3px;
    text-align: center;
    text-transform: uppercase;
    background: #1e293b;
    color: #f8fafc;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.restart-input-wrap input::placeholder {
    letter-spacing: 1px;
    font-weight: 400;
    color: #475569;
    text-transform: none;
}
.restart-input-wrap input:focus { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.18); }
.restart-input-wrap input.input-valid { border-color: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.15); }
.restart-actions { display: flex; gap: 10px; }
.restart-actions .btn-cancel {
    flex: 1;
    padding: 11px;
    border: 1.5px solid #334155;
    border-radius: 8px;
    background: transparent;
    color: #94a3b8;
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: all .15s;
}
.restart-actions .btn-cancel:hover { background: #1e293b; color: #f1f5f9; }
.restart-actions .btn-confirm {
    flex: 2;
    padding: 11px;
    border: none;
    border-radius: 8px;
    background: #ef4444;
    color: #fff;
    font-size: 13.5px;
    font-weight: 800;
    cursor: pointer;
    transition: all .18s;
    opacity: .4;
    pointer-events: none;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
}
.restart-actions .btn-confirm.unlocked { opacity: 1; pointer-events: auto; }
.restart-actions .btn-confirm.unlocked:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(239,68,68,.38); }
.restart-modal .close-x {
    position: absolute;
    top: 14px; right: 16px;
    background: none;
    border: none;
    color: #475569;
    font-size: 20px;
    cursor: pointer;
    padding: 4px 6px;
    border-radius: 4px;
    line-height: 1;
    transition: color .15s, background .15s;
}
.restart-modal .close-x:hover { color: #f1f5f9; background: #1e293b; }
#restartSpinner { display: none; }
.btn-confirm.loading #restartSpinner { display: inline-block; }
.btn-confirm.loading .btn-confirm-text { display: none; }
</style>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Results &amp; Analysis</h1>
            <p class="page-subtitle">Examine candidate performance, leaderboards, and scoring reports</p>
        </div>
        <?php if ($selected_test): ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <a href="../qr/export_modus.php?test_id=<?= $selected_test['id'] ?>"
                   class="btn btn-primary"
                   style="display:inline-flex;align-items:center;gap:7px;">
                    📦 Get .modus File
                </a>
                <a href="../qr/index.php?test_id=<?= $selected_test['id'] ?>"
                   class="btn btn-secondary"
                   style="display:inline-flex;align-items:center;gap:7px;">
                    📱 Faculty QR
                </a>
                <?php if ($selected_test['status'] === 'completed'): ?>
                    <button type="button"
                        onclick="openRestartModal(<?= (int)$selected_test['id'] ?>, <?= htmlspecialchars(json_encode($selected_test['title']), ENT_QUOTES) ?>)"
                        style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:8px;border:1.5px solid #ef4444;background:#7f1d1d;color:#fff;font-size:13.5px;font-weight:700;cursor:pointer;transition:background .15s ease;">
                        🔄 Restart Test
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Test Selector Filter -->
    <div class="card" style="padding: 16px 20px;">
        <form method="GET" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <label class="form-label" style="margin:0;white-space:nowrap;">Select Test:</label>
            <select name="test_id" class="form-select" style="max-width:400px;" onchange="this.form.submit()">
                <?php foreach ($tests as $t): ?>
                    <option value="<?= $t['id'] ?>" <?= $t['id'] == $test_id ? 'selected' : '' ?>>
                        <?= e($t['title']) ?> [<?= e(status_label($t['status'])) ?>]
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($stats): ?>
        <!-- Performance Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--primary);"><?= $stats['submitted'] ?> / <?= $stats['total'] ?></div>
                <div class="stat-label">Submissions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--success);"><?= format_score($stats['highest']) ?></div>
                <div class="stat-label">Highest Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= format_score($stats['average']) ?></div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--danger);"><?= format_score($stats['lowest']) ?></div>
                <div class="stat-label">Lowest Score</div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card" style="padding:0;overflow:hidden;">
        <div class="data-table-wrapper" style="border:none;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>UID</th>
                        <th>Candidate Name</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Correct</th>
                        <th>Wrong</th>
                        <th>Skipped</th>
                        <th>Submitted At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="10" style="text-align:center;padding:36px;color:var(--text-muted);">
                                No results recorded yet for this examination.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $rank = 0;
                        foreach ($results as $r):
                            if ($r['status'] === 'submitted') $rank++;
                        ?>
                            <tr>
                                <td><?= $r['status'] === 'submitted' ? "<strong>#$rank</strong>" : '—' ?></td>
                                <td><code><?= e($r['uid']) ?></code></td>
                                <td><strong><?= e($r['name']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $r['status'] === 'submitted' ? 'completed' : 'draft' ?>">
                                        <?= e(status_label($r['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color:<?= (float)$r['score'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-size:15px;">
                                        <?= $r['score'] !== null ? format_score($r['score']) : '—' ?>
                                    </strong>
                                </td>
                                <td style="color:var(--success);"><?= (int)$r['correct_answers'] ?></td>
                                <td style="color:var(--danger);"><?= (int)$r['wrong_answers'] ?></td>
                                <td style="color:var(--text-muted);"><?= (int)$r['unanswered'] ?></td>
                                <td style="font-size:12px;color:var(--text-muted);">
                                    <?= $r['submitted_at'] ? date('H:i:s, M j', strtotime($r['submitted_at'])) : '—' ?>
                                </td>
                                <td>
                                    <a href="student.php?student_test_id=<?= $r['student_test_id'] ?>" class="btn btn-secondary btn-sm">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── RESTART CONFIRMATION MODAL ─────────────────────────── -->
<div id="restartOverlay" role="dialog" aria-modal="true" aria-labelledby="restartTitle">
    <div class="restart-modal">
        <button class="close-x" onclick="closeRestartModal()" aria-label="Close">&#x2715;</button>
        <span class="restart-icon">&#x26A0;&#xFE0F;</span>
        <h3 id="restartTitle">Restart Examination?</h3>
        <p>
            This will permanently erase all student results, answers and sessions for<br>
            <strong id="restartTestName" style="color:#f1f5f9;"></strong>.<br><br>
            Assigned students and questions will be <strong style="color:#22c55e;">kept</strong>.
            This action <strong style="color:#ef4444;">cannot be undone</strong>.
        </p>

        <span class="restart-type-label">Type RESTART to confirm</span>
        <div class="restart-input-wrap">
            <input
                type="text"
                id="restartConfirmInput"
                placeholder="Type RESTART here..."
                autocomplete="off"
                spellcheck="false"
                oninput="onRestartInput(this)"
            >
        </div>

        <div class="restart-actions">
            <button class="btn-cancel" onclick="closeRestartModal()">Cancel</button>
            <button class="btn-confirm" id="restartConfirmBtn" onclick="submitRestart()">
                <span id="restartSpinner">&#x23F3;</span>
                <span class="btn-confirm-text">&#x1F504; Restart Now</span>
            </button>
        </div>
    </div>
</div>

<script>
var _restartTestId = 0;
var _csrfToken = <?= json_encode(csrf_token()) ?>;

function openRestartModal(testId, testName) {
    _restartTestId = testId;
    document.getElementById('restartTestName').textContent = testName;
    document.getElementById('restartConfirmInput').value = '';
    var btn = document.getElementById('restartConfirmBtn');
    btn.classList.remove('unlocked', 'loading');
    document.getElementById('restartConfirmInput').classList.remove('input-valid');
    document.getElementById('restartOverlay').classList.add('active');
    setTimeout(function() { document.getElementById('restartConfirmInput').focus(); }, 120);
}

function closeRestartModal() {
    document.getElementById('restartOverlay').classList.remove('active');
}

function onRestartInput(el) {
    var val = el.value.toUpperCase();
    var btn = document.getElementById('restartConfirmBtn');
    if (val === 'RESTART') {
        el.classList.add('input-valid');
        btn.classList.add('unlocked');
    } else {
        el.classList.remove('input-valid');
        btn.classList.remove('unlocked');
    }
}

function submitRestart() {
    var btn = document.getElementById('restartConfirmBtn');
    if (!btn.classList.contains('unlocked') || btn.classList.contains('loading')) return;
    btn.classList.add('loading');
    btn.classList.remove('unlocked');

    var fd = new FormData();
    fd.append('_csrf', _csrfToken);
    fd.append('test_id', _restartTestId);
    fd.append('confirm', 'RESTART');

    fetch('restart_test.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                closeRestartModal();
                alert('Test restarted successfully. All results have been cleared.');
                window.location.reload();
            } else {
                btn.classList.remove('loading');
                btn.classList.add('unlocked');
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(function() {
            btn.classList.remove('loading');
            btn.classList.add('unlocked');
            alert('Network error. Please try again.');
        });
}

document.getElementById('restartOverlay').addEventListener('click', function(e) {
    if (e.target === this) closeRestartModal();
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeRestartModal();
});
</script>

<?php layout_footer(); ?>
