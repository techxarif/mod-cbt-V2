<?php
// qr/index.php — Faculty QR Dashboard (Responsive & Status-Flexible)
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$test_id = int_param('test_id', 0);
$all_tests = get_all_tests();

// If test_id is not specified, prioritize completed tests, else active/waiting, else first test
if ($test_id <= 0 && !empty($all_tests)) {
    foreach ($all_tests as $t) {
        if ($t['status'] === 'completed') {
            $test_id = (int)$t['id'];
            break;
        }
    }
    if ($test_id <= 0) {
        $test_id = (int)$all_tests[0]['id'];
    }
}

$selected_test = $test_id > 0 ? get_test($test_id) : null;
$results = [];

if ($selected_test) {
    $results = get_test_results($test_id);
}

$page_title = 'Faculty QR Export — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'qr');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Faculty QR Code</h1>
            <p class="page-subtitle">Encrypted examination package for quick scoring sync and faculty records</p>
        </div>
        <?php if ($selected_test): ?>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="export_modus.php?test_id=<?= $selected_test['id'] ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                    📦 Get .modus File
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Test Selector Filter -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 22px;">
        <form method="GET" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
            <label class="form-label" style="margin: 0; white-space: nowrap; font-weight: 700;">Select Examination:</label>
            <div style="flex: 1; min-width: min(100%, 280px); max-width: min(440px, 100%);">
                <select name="test_id" class="form-select" onchange="this.form.submit()">
                    <?php if (empty($all_tests)): ?>
                        <option value="">No tests available in system</option>
                    <?php else: ?>
                        <?php foreach ($all_tests as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['id'] == $test_id ? 'selected' : '' ?>>
                                <?= e($t['title']) ?> (Status: <?= ucfirst($t['status']) ?>) #<?= $t['id'] ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if (!$selected_test): ?>
        <div class="card" style="text-align: center; padding: 48px; color: var(--muted);">
            No tests found in the database. Please create a test first.
        </div>
    <?php else: ?>
        <style>
            .qr-layout-grid {
                display: grid;
                grid-template-columns: minmax(280px, 380px) 1fr;
                gap: 22px;
                align-items: start;
                margin-bottom: 24px;
            }
            .qr-card-center {
                text-align: center;
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .qr-image-box {
                background: #ffffff;
                padding: 18px;
                border-radius: 16px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.45);
                display: inline-block;
                max-width: 100%;
                box-sizing: border-box;
                margin-bottom: 18px;
            }
            .qr-image-box img {
                display: block;
                max-width: 100%;
                width: 260px;
                height: auto;
                aspect-ratio: 1/1;
                margin: 0 auto;
            }

            @media (max-width: 860px) {
                .qr-layout-grid {
                    grid-template-columns: 1fr !important;
                }
                .qr-image-box {
                    max-width: 280px !important;
                    width: 100% !important;
                    padding: 14px !important;
                }
            }
        </style>

        <div class="qr-layout-grid">
            <!-- QR Display Card -->
            <div class="card qr-card-center" style="padding: 24px;">
                <h3 class="card-title" style="margin-bottom: 6px;">Encrypted Result Package</h3>
                <p style="font-size: 13px; color: var(--muted); margin-bottom: 20px; line-height: 1.5; max-width: 320px;">
                    Scan via mobile scanner or download the 2-way encrypted <code>.modus</code> package for Faculty gradebook sync.
                </p>

                <div class="qr-image-box">
                    <img src="image.php?test_id=<?= $selected_test['id'] ?>" alt="Faculty QR Code">
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; width: 100%;">
                    <a href="image.php?test_id=<?= $selected_test['id'] ?>" download="qr_test_<?= $selected_test['id'] ?>.png" class="btn btn-secondary btn-sm" style="flex: 1; min-width: 130px;">
                        💾 Download QR
                    </a>
                    <a href="export_modus.php?test_id=<?= $selected_test['id'] ?>" class="btn btn-primary btn-sm" style="flex: 1; min-width: 150px;">
                        📦 Get .modus File
                    </a>
                </div>
            </div>

            <!-- Candidate Roster in Package -->
            <div class="card" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h3 class="card-title" style="margin: 0;">Included Records (<?= count($results) ?> candidates)</h3>
                        <div style="font-size: 12px; color: var(--muted); margin-top: 4px;">
                            Packaged with fixed salt <code>"MODUS CBT"</code> & 2-way AES-256-CBC cipher.
                        </div>
                    </div>
                    <span style="font-size: 11.5px; color: var(--muted); font-weight: 600;">Status: <strong style="color: var(--green);"><?= strtoupper($selected_test['status']) ?></strong></span>
                </div>

                <div class="data-table-wrapper" style="max-height: 440px; overflow-y: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>UID</th>
                                <th>Name</th>
                                <th>Score</th>
                                <th>Correct</th>
                                <th>Wrong</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 24px; color: var(--muted);">
                                        No student submissions recorded for this test yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $r): ?>
                                    <tr>
                                        <td><code><?= e($r['uid']) ?></code></td>
                                        <td><?= e($r['name']) ?></td>
                                        <td><strong><?= format_score($r['score']) ?></strong></td>
                                        <td style="color: var(--green);"><?= (int)$r['correct_answers'] ?></td>
                                        <td style="color: var(--red);"><?= (int)$r['wrong_answers'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php layout_footer(); ?>
