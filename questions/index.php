<?php
// questions/index.php — Questions Management & PDF Question Paper Generator
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$teacher = currentTeacher();
$teacherName = $teacher['name'] ?? 'Faculty';
$teacherUsername = $teacher['username'] ?? 'Teacher';

$test_id = int_param('test_id', 0);
$tests   = get_all_tests();

if ($test_id <= 0 && !empty($tests)) {
    $test_id = (int)$tests[0]['id'];
}

$selected_test = $test_id > 0 ? get_test($test_id) : null;
$questions     = [];

if ($selected_test) {
    $questions = get_test_questions($test_id, true);
}

// Pre-calculate questions array for preview JSON
$questions_json = json_encode(array_map(function($q) {
    return [
        'id'             => (int)$q['id'],
        'question_text'  => $q['question_text'],
        'question_image' => !empty($q['question_image']) ? '../' . ltrim($q['question_image'], '/') : '',
        'option_a'       => $q['option_a'],
        'option_b'       => $q['option_b'],
        'option_c'       => $q['option_c'],
        'option_d'       => $q['option_d'],
        'marks'          => (float)$q['marks'],
        'negative_marks' => (float)$q['negative_marks'],
        'correct_option' => $q['correct_option'] ?? 'A'
    ];
}, $questions), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

$page_title = 'Questions Management — ' . APP_NAME;
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'questions');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title">Questions</h1>
            <p class="page-subtitle">Manage examination questions, diagrams, and answer keys</p>
        </div>
        <?php if ($selected_test): ?>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <a href="add.php?test_id=<?= $selected_test['id'] ?>" class="btn btn-primary">+ Add Question</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Test Selector Filter & PDF Actions Area -->
    <div class="card" style="padding: 16px 20px; margin-bottom: 22px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
            <!-- Select Test Form -->
            <form method="GET" style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap; flex: 1; min-width: 260px;">
                <label class="form-label" style="margin: 0; white-space: nowrap; font-weight: 700;">Select Test:</label>
                <select name="test_id" class="form-select" style="max-width: 380px; flex: 1;" onchange="this.form.submit()">
                    <?php foreach ($tests as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $t['id'] == $test_id ? 'selected' : '' ?>>
                            <?= e($t['title']) ?> (<?= (int)$t['duration_minutes'] ?>m)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <?php if ($selected_test && count($questions) >= 1): ?>
                <!-- Download Question Paper Dropdown (Visible only when >= 1 question is added) -->
                <div class="pdf-dropdown-wrapper" id="pdfDropdownWrapper">
                    <button type="button" class="btn btn-secondary" onclick="togglePdfDropdown(event)" id="btnPdfDropdownTrigger" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700;">
                        <span>📄</span> Download Question Paper <span style="font-size: 10px; margin-left: 2px;">▼</span>
                    </button>
                    
                    <div class="pdf-dropdown-menu" id="pdfDropdownMenu">
                        <button type="button" class="pdf-dropdown-item" onclick="openPdfPreviewModal('normal')">
                            <div class="pdf-dropdown-item-title">
                                <span style="color: var(--green);">✨</span> Normal PDF (Aesthetic &amp; Spaced)
                            </div>
                            <div class="pdf-dropdown-item-desc">
                                Spacious exam layout with background watermark (<strong><?= e($teacherUsername) ?> &bull; CBT by Modus</strong>), diagram cards, and clean typography.
                            </div>
                        </button>
                        
                        <div style="height: 1px; background: var(--line); margin: 4px 6px;"></div>

                        <button type="button" class="pdf-dropdown-item" onclick="openPdfPreviewModal('minimal')">
                            <div class="pdf-dropdown-item-title">
                                <span style="color: #55a8ff;">📄</span> Minimal PDF (Paper Saver)
                            </div>
                            <div class="pdf-dropdown-item-desc">
                                Compact layout with reduced spacing and each question separated by a line. Designed to minimize printed pages.
                            </div>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$selected_test): ?>
        <div class="card" style="text-align: center; padding: 48px; color: var(--text-muted);">
            No tests available. Please <a href="../tests/create.php">create a test</a> first.
        </div>
    <?php elseif (empty($questions)): ?>
        <div class="card" style="text-align: center; padding: 48px; color: var(--text-muted);">
            <p style="font-size: 16px; margin-bottom: 16px;">No questions have been added to <strong><?= e($selected_test['title']) ?></strong> yet.</p>
            <a href="add.php?test_id=<?= $selected_test['id'] ?>" class="btn btn-primary">Add First Question</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($questions as $index => $q): ?>
                <div class="card" style="position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                        <span class="badge" style="background: var(--bg-surface); color: var(--text-primary); font-size: 13px;">
                            Q<?= $index + 1 ?> (Order: <?= (int)$q['question_order'] ?>)
                        </span>
                        <div style="display: flex; gap: 8px;">
                            <span class="badge badge-success">+<?= (float)$q['marks'] ?></span>
                            <?php if ((float)$q['negative_marks'] > 0): ?>
                                <span class="badge badge-draft">-<?= (float)$q['negative_marks'] ?></span>
                            <?php endif; ?>
                            <a href="edit.php?id=<?= $q['id'] ?>&test_id=<?= $selected_test['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" action="delete.php" style="display: inline-block;" onsubmit="return confirm('Delete this question?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                <input type="hidden" name="test_id" value="<?= $selected_test['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>

                    <div style="font-size: 15px; font-weight: 500; margin-bottom: 14px; line-height: 1.6;">
                        <?= nl2br(e($q['question_text'])) ?>
                    </div>

                    <?php if (!empty($q['question_image'])): ?>
                        <div style="margin-bottom: 16px;">
                            <img src="../<?= e($q['question_image']) ?>" alt="Question Image" style="max-height: 200px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px;">
                        <?php foreach (['A', 'B', 'C', 'D'] as $opt): 
                            $optKey = 'option_' . strtolower($opt);
                            $isCorrect = $q['correct_option'] === $opt;
                        ?>
                            <div style="padding: 10px 14px; border-radius: var(--radius-md); background: <?= $isCorrect ? 'var(--success-light)' : 'var(--bg-secondary)' ?>; border: 1px solid <?= $isCorrect ? 'var(--success)' : 'var(--border-subtle)' ?>; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                                <strong style="color: <?= $isCorrect ? 'var(--success)' : 'var(--text-secondary)' ?>;"><?= $opt ?>:</strong>
                                <span style="<?= $isCorrect ? 'color: #34d399; font-weight: 600;' : '' ?>"><?= e($q[$optKey]) ?></span>
                                <?php if ($isCorrect): ?>
                                    <span style="margin-left: auto; color: var(--success); font-weight: 700;">✓ Correct</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ============================================================
     QUESTION PAPER PREVIEW & CUSTOMIZATION MODAL (Mobile Responsive)
     ============================================================ -->
<?php if ($selected_test && count($questions) >= 1): ?>
<div class="modus-modal-backdrop" id="questionPaperPreviewModal" onclick="if(event.target===this) closePdfPreviewModal()">
    <div class="modus-modal-card" style="max-width: 880px; width: 96%; max-height: 94vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 18px; text-align: left;">
        
        <!-- Modal Top Bar -->
        <div style="padding: 16px 22px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; background: rgba(13, 20, 30, 0.98);">
            <div>
                <div style="font-size: 15px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                    <span>📄</span> Question Paper Preview &amp; PDF Customizer
                </div>
                <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">
                    <?= e($selected_test['title']) ?> &bull; <?= count($questions) ?> Questions &bull; <?= (int)$selected_test['duration_minutes'] ?> Mins
                </div>
            </div>
            <button type="button" style="background: none; border: none; color: var(--muted); font-size: 24px; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 6px;" onclick="closePdfPreviewModal()">&times;</button>
        </div>

        <!-- Customizer Toolbar (Mobile Responsive Controls) -->
        <div class="pdf-modal-toolbar">
            <div class="pdf-toolbar-group">
                <span style="font-size: 11.5px; font-weight: 750; color: var(--muted); text-transform: uppercase;">Mode:</span>
                <button type="button" class="pdf-seg-btn active" id="btnModeNormal" onclick="setPdfMode('normal')">
                    ✨ Normal (Spaced Layout)
                </button>
                <button type="button" class="pdf-seg-btn" id="btnModeMinimal" onclick="setPdfMode('minimal')">
                    📄 Minimal (Paper Saver)
                </button>
            </div>

            <div class="pdf-toolbar-group">
                <span style="font-size: 11.5px; font-weight: 750; color: var(--muted); text-transform: uppercase;">Size:</span>
                <select id="pdfFontSizeSelect" class="form-select" style="padding: 4px 8px; font-size: 12px; min-width: 105px; height: 32px;" onchange="setPdfFontSize(this.value)">
                    <option value="10">10px (Compact)</option>
                    <option value="11">11px (Small)</option>
                    <option value="12" selected>12px (Standard)</option>
                    <option value="13">13px (Default)</option>
                    <option value="14">14px (Medium)</option>
                    <option value="16">16px (Large)</option>
                </select>

                <span style="font-size: 11.5px; font-weight: 750; color: var(--muted); text-transform: uppercase; margin-left: 4px;">Style:</span>
                <button type="button" class="pdf-format-btn" id="btnPdfBold" onclick="togglePdfFormat('bold')" title="Toggle Bold">
                    B
                </button>
                <button type="button" class="pdf-format-btn" id="btnPdfItalic" onclick="togglePdfFormat('italic')" title="Toggle Italic">
                    <em>I</em>
                </button>
                <button type="button" class="pdf-format-btn" id="btnPdfUnderline" onclick="togglePdfFormat('underline')" title="Toggle Underline">
                    <u>U</u>
                </button>

                <label style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text); cursor: pointer; margin-left: 6px;">
                    <input type="checkbox" id="pdfIncludeAnswers" onchange="togglePdfAnswers(this.checked)" style="cursor: pointer;">
                    <span>Include Key</span>
                </label>
            </div>
        </div>

        <!-- Notice: Download PDF for best result -->
        <div style="padding: 10px 22px; background: rgba(59, 130, 246, 0.09); border-bottom: 1px solid rgba(59, 130, 246, 0.22); display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: #93c5fd; line-height: 1.4;">
            <span style="font-size: 16px; flex-shrink: 0;">ℹ️</span>
            <span>
                <strong>Important:</strong> Please download the PDF once for the best result. The in-browser preview is an approximation and does not exactly match the final PDF engine pagination, fonts, and layout.
            </span>
        </div>

        <!-- Scrollable A4 Multi-Page Simulation Viewport -->
        <div class="pdf-preview-viewport" id="pdfPreviewViewport">
            <!-- Dynamically populated with multi-page .pdf-sheet cards by JS -->
        </div>

        <!-- Modal Bottom Actions Bar -->
        <div style="padding: 14px 22px; background: rgba(10, 15, 22, 0.95); border-top: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div style="font-size: 12px; color: var(--muted);">
                <span id="pdfPreviewSummary">Ready to export &bull; A4 Vector Render</span>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closePdfPreviewModal()">
                    Close
                </button>
                <button type="button" class="btn btn-primary" onclick="downloadConfiguredPdf()" style="display: inline-flex; align-items: center; gap: 6px; font-weight: 750;">
                    <span>⬇</span> Download Official PDF
                </button>
            </div>
        </div>

    </div>
</div>

<script>
// ============================================================
// QUESTION PAPER PDF PREVIEW & CUSTOMIZATION LOGIC
// ============================================================
var questionsData = <?= $questions_json ?: '[]' ?>;
var testDetails = {
    id: <?= (int)($selected_test['id'] ?? 0) ?>,
    title: <?= json_encode($selected_test['title'] ?? '') ?>,
    duration: <?= (int)($selected_test['duration_minutes'] ?? 0) ?>,
    totalMarks: <?= (float)($selected_test['total_marks'] ?? 4) ?>,
    negativeMarks: <?= (float)($selected_test['negative_marks'] ?? 1) ?>,
    teacherName: <?= json_encode($teacherName) ?>,
    teacherUsername: <?= json_encode($teacherUsername) ?>
};

var pdfConfig = {
    mode: 'normal',
    fontSize: 12,
    bold: false,
    italic: false,
    underline: false,
    includeAnswers: false
};

// Dropdown Toggle
function togglePdfDropdown(e) {
    if (e) e.stopPropagation();
    var menu = document.getElementById('pdfDropdownMenu');
    if (menu) {
        menu.classList.toggle('show');
        if (menu.classList.contains('show')) {
            var rect = menu.getBoundingClientRect();
            if (rect.left < 10) {
                menu.style.left = '0px';
                menu.style.right = 'auto';
            } else if (rect.right > window.innerWidth - 10) {
                menu.style.right = '0px';
                menu.style.left = 'auto';
            }
        }
    }
}

document.addEventListener('click', function(e) {
    var menu = document.getElementById('pdfDropdownMenu');
    var trigger = document.getElementById('btnPdfDropdownTrigger');
    if (menu && menu.classList.contains('show')) {
        if (!menu.contains(e.target) && e.target !== trigger) {
            menu.classList.remove('show');
        }
    }
});

// Modal Open / Close
function openPdfPreviewModal(initialMode) {
    var menu = document.getElementById('pdfDropdownMenu');
    if (menu) menu.classList.remove('show');

    if (initialMode) {
        pdfConfig.mode = initialMode;
    }
    updateToolbarUi();
    renderPaperPreview();

    var modal = document.getElementById('questionPaperPreviewModal');
    if (modal) {
        modal.classList.add('active');
    }
}

function closePdfPreviewModal() {
    var modal = document.getElementById('questionPaperPreviewModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// Config Updaters
function setPdfMode(mode) {
    pdfConfig.mode = mode;
    updateToolbarUi();
    renderPaperPreview();
}

function setPdfFontSize(size) {
    pdfConfig.fontSize = parseInt(size, 10) || 12;
    renderPaperPreview();
}

function togglePdfFormat(fmt) {
    if (fmt === 'bold') pdfConfig.bold = !pdfConfig.bold;
    if (fmt === 'italic') pdfConfig.italic = !pdfConfig.italic;
    if (fmt === 'underline') pdfConfig.underline = !pdfConfig.underline;
    updateToolbarUi();
    renderPaperPreview();
}

function togglePdfAnswers(checked) {
    pdfConfig.includeAnswers = !!checked;
    renderPaperPreview();
}

function updateToolbarUi() {
    var btnNormal = document.getElementById('btnModeNormal');
    var btnMinimal = document.getElementById('btnModeMinimal');
    if (btnNormal && btnMinimal) {
        if (pdfConfig.mode === 'normal') {
            btnNormal.classList.add('active');
            btnMinimal.classList.remove('active');
        } else {
            btnMinimal.classList.add('active');
            btnNormal.classList.remove('active');
        }
    }

    var btnB = document.getElementById('btnPdfBold');
    var btnI = document.getElementById('btnPdfItalic');
    var btnU = document.getElementById('btnPdfUnderline');

    if (btnB) btnB.classList.toggle('active', pdfConfig.bold);
    if (btnI) btnI.classList.toggle('active', pdfConfig.italic);
    if (btnU) btnU.classList.toggle('active', pdfConfig.underline);

    var selSize = document.getElementById('pdfFontSizeSelect');
    if (selSize) selSize.value = pdfConfig.fontSize;
}

// Render Paper Preview HTML (Multi-Page Document Simulation)
function renderPaperPreview() {
    var viewport = document.getElementById('pdfPreviewViewport');
    if (!viewport) return;

    var qWeight = pdfConfig.bold ? '700' : '500';
    var qStyle = pdfConfig.italic ? 'italic' : 'normal';
    var qDecor = pdfConfig.underline ? 'underline' : 'none';
    var fontSize = pdfConfig.fontSize;
    var isMinimal = (pdfConfig.mode === 'minimal');

    // Faculty name & truncated username (add "..." if username is long)
    var rawUser = testDetails.teacherUsername || '';
    var truncUser = (rawUser.length > 12) ? rawUser.substring(0, 10) + '...' : rawUser;
    var rawName = testDetails.teacherName || '';
    var truncName = (rawName.length > 18) ? rawName.substring(0, 15) + '...' : rawName;
    var facultyFooterText = 'Faculty: ' + escapeHtml(truncName) + (truncUser ? ' (@' + escapeHtml(truncUser) + ')' : '');

    // Watermark HTML for each page in both Normal and Minimal modes (Above questions, very light, no underline)
    var watermarkHtml = '<div class="pdf-watermark-overlay" style="text-decoration: none !important; text-decoration-line: none !important; border: none !important; border-bottom: none !important; outline: none !important; z-index: 10; pointer-events: none; user-select: none;">' + escapeHtml(testDetails.teacherUsername) + ' &bull; CBT BY MODUS</div>';

    // Paginate questions across A4 pages
    // Page 1 budget: ~3.2 in Normal, ~6.5 in Minimal
    // Page 2+ budget: ~4.5 in Normal, ~8.0 in Minimal
    var pageList = [];
    var curPage = [];
    var curWeight = 0;
    var p1Budget = isMinimal ? 6.5 : 3.2;
    var subBudget = isMinimal ? 8.0 : 4.5;
    var curBudget = p1Budget;

    questionsData.forEach(function(q, idx) {
        var w = q.question_image ? (isMinimal ? 1.4 : 1.6) : (isMinimal ? 0.9 : 1.0);
        if (q.question_text && q.question_text.length > 180) {
            w += 0.4;
        }

        if (curPage.length > 0 && (curWeight + w) > curBudget) {
            pageList.push(curPage);
            curPage = [{ q: q, num: idx + 1 }];
            curWeight = w;
            curBudget = subBudget;
        } else {
            curPage.push({ q: q, num: idx + 1 });
            curWeight += w;
        }
    });

    if (curPage.length > 0) {
        pageList.push(curPage);
    }
    if (pageList.length === 0) {
        pageList.push([]);
    }

    var totalPages = pageList.length + (pdfConfig.includeAnswers ? 1 : 0);

    var viewportHtml = [];

    // Helper to generate each page's footer
    function makeFooter(pageNum) {
        var f = [];
        f.push('<div style="border-top: 1px solid #cbd5e1; padding-top: 6px; margin-top: 20px; font-size: 8.5px; color: #64748b; position: relative; z-index: 2;">');
        f.push('  <table style="width: 100%; border-collapse: collapse;">');
        f.push('    <tr>');
        f.push('      <td style="text-align: left; vertical-align: middle;">');
        f.push('        <img src="../assets/img/logo.png" style="height: 12px; vertical-align: -2px; margin-right: 4px;" alt="Logo">');
        f.push('        <strong style="color: #0f172a;">CBT by Modus</strong> &bull; ' + facultyFooterText);
        f.push('      </td>');
        f.push('      <td style="text-align: right; vertical-align: middle; font-weight: 700; color: #0f172a; white-space: nowrap;">');
        f.push('        Page ' + pageNum + ' of ' + totalPages);
        f.push('      </td>');
        f.push('    </tr>');
        f.push('  </table>');
        f.push('</div>');
        return f.join('');
    }

    // Render Question Pages
    pageList.forEach(function(items, pIdx) {
        var pageNum = pIdx + 1;
        viewportHtml.push('<div class="pdf-sheet">');
        viewportHtml.push(watermarkHtml);
        viewportHtml.push('<div style="flex: 1; position: relative; z-index: 2;">');

        // Header on Page 1
        if (pageNum === 1) {
            if (isMinimal) {
                // Minimal Mode: Single Line Candidate Header
                viewportHtml.push('<div style="margin-bottom: 6px; font-size: ' + Math.max(9, fontSize - 1) + 'px; color: #1e293b;">');
                viewportHtml.push('  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">');
                viewportHtml.push('    <span><strong>Name:</strong> ________________________________</span>');
                viewportHtml.push('    <span><strong>Correct:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]</span>');
                viewportHtml.push('    <span><strong>Wrong:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]</span>');
                viewportHtml.push('    <span><strong>Score:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]</span>');
                viewportHtml.push('  </div>');
                viewportHtml.push('</div>');
                viewportHtml.push('<hr style="border: 0; border-top: 1.5px solid #0f172a; margin: 4px 0 8px 0;">');
            } else {
                // Normal Mode: Full Institutional Header
                viewportHtml.push('<div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 14px;">');
                viewportHtml.push('  <div style="font-size: 11px; font-weight: 800; color: #059669; text-transform: uppercase; letter-spacing: 1.5px;">CBT BY MODUS &bull; OFFICIAL EXAMINATION PAPER</div>');
                viewportHtml.push('  <h2 style="font-size: 20px; font-weight: 800; color: #0f172a; margin: 3px 0 6px 0;">' + escapeHtml(testDetails.title) + '</h2>');
                viewportHtml.push('  <table style="width: 100%; border-collapse: collapse; font-size: ' + Math.max(9, fontSize - 1) + 'px; color: #334155;">');
                viewportHtml.push('    <tr>');
                viewportHtml.push('      <td style="width: 32%; text-align: left; padding: 2px;"><strong>Duration:</strong> ' + testDetails.duration + ' Mins</td>');
                viewportHtml.push('      <td style="width: 34%; text-align: center; padding: 2px;"><strong>No. of Correct:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]</td>');
                viewportHtml.push('      <td style="width: 34%; text-align: right; padding: 2px;"><strong>No. of Wrong:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]</td>');
                viewportHtml.push('    </tr>');
                viewportHtml.push('    <tr>');
                viewportHtml.push('      <td style="text-align: left; padding: 2px;"><strong>Faculty:</strong> ' + escapeHtml(testDetails.teacherName) + ' (@' + escapeHtml(truncUser) + ')</td>');
                viewportHtml.push('      <td style="text-align: center; padding: 2px;"><strong>Marks Obtained:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]</td>');
                viewportHtml.push('      <td style="text-align: right; padding: 2px;"><strong>Mode:</strong> Normal (Paper Mode)</td>');
                viewportHtml.push('    </tr>');
                viewportHtml.push('  </table>');
                viewportHtml.push('</div>');

                // Candidate Details Area
                viewportHtml.push('<div style="border: 1px dashed #94a3b8; padding: 6px 10px; margin-bottom: 14px; font-size: 9.5px; color: #475569;">');
                viewportHtml.push('  <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;">');
                viewportHtml.push('    <span><strong>Candidate Name:</strong> ________________________________</span>');
                viewportHtml.push('    <span><strong>UID / Roll No:</strong> ____________________</span>');
                viewportHtml.push('    <span><strong>Invigilator Sign:</strong> ________________</span>');
                viewportHtml.push('  </div>');
                viewportHtml.push('</div>');
            }
        }

        // Questions on this page
        viewportHtml.push('<div style="display: flex; flex-direction: column; gap: ' + (isMinimal ? '6px' : '12px') + ';">');
        items.forEach(function(item) {
            var q = item.q;
            var num = item.num;

            if (isMinimal) {
                // Minimal Mode Item
                viewportHtml.push('<div style="margin-bottom: 6px; font-size: ' + fontSize + 'px; line-height: 1.35; color: #1e293b;">');
                if (q.question_image) {
                    // Image-based Question: 50% left (question and options), 50% right (image)
                    viewportHtml.push('  <table style="width: 100%; border-collapse: collapse;">');
                    viewportHtml.push('    <tr>');
                    viewportHtml.push('      <td style="width: 50%; vertical-align: top; padding-right: 10px;">');
                    viewportHtml.push('        <div style="margin-bottom: 4px;">');
                    viewportHtml.push('          <strong style="color: #0f172a; margin-right: 4px;">Q' + num + '.</strong>');
                    viewportHtml.push('          <span style="font-weight: ' + qWeight + '; font-style: ' + qStyle + '; text-decoration: ' + qDecor + '; white-space: pre-wrap;">' + escapeHtml(q.question_text) + '</span>');
                    viewportHtml.push('        </div>');
                    viewportHtml.push('        <div style="font-size: ' + Math.max(9, fontSize - 0.5) + 'px;">');
                    viewportHtml.push('          <div style="margin-bottom: 2px;"><strong style="color: #0f172a;">(A)</strong> ' + escapeHtml(q.option_a) + '</div>');
                    viewportHtml.push('          <div style="margin-bottom: 2px;"><strong style="color: #0f172a;">(B)</strong> ' + escapeHtml(q.option_b) + '</div>');
                    viewportHtml.push('          <div style="margin-bottom: 2px;"><strong style="color: #0f172a;">(C)</strong> ' + escapeHtml(q.option_c) + '</div>');
                    viewportHtml.push('          <div style="margin-bottom: 2px;"><strong style="color: #0f172a;">(D)</strong> ' + escapeHtml(q.option_d) + '</div>');
                    viewportHtml.push('        </div>');
                    viewportHtml.push('      </td>');
                    viewportHtml.push('      <td style="width: 50%; vertical-align: middle; text-align: center;">');
                    viewportHtml.push('        <img src="' + escapeHtml(q.question_image) + '" style="max-height: 180px; max-width: 98%; border: 1px solid #cbd5e1; border-radius: 4px;" alt="Diagram">');
                    viewportHtml.push('      </td>');
                    viewportHtml.push('    </tr>');
                    viewportHtml.push('  </table>');
                } else {
                    // Text-only question: full-width
                    viewportHtml.push('  <div style="margin-bottom: 3px;">');
                    viewportHtml.push('    <strong style="color: #0f172a; margin-right: 4px;">Q' + num + '.</strong>');
                    viewportHtml.push('    <span style="font-weight: ' + qWeight + '; font-style: ' + qStyle + '; text-decoration: ' + qDecor + '; white-space: pre-wrap;">' + escapeHtml(q.question_text) + '</span>');
                    viewportHtml.push('  </div>');
                    viewportHtml.push('  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px; font-size: ' + Math.max(9, fontSize - 0.5) + 'px; margin-top: 3px;">');
                    viewportHtml.push('    <div><strong style="color: #0f172a;">(A)</strong> ' + escapeHtml(q.option_a) + '</div>');
                    viewportHtml.push('    <div><strong style="color: #0f172a;">(B)</strong> ' + escapeHtml(q.option_b) + '</div>');
                    viewportHtml.push('    <div><strong style="color: #0f172a;">(C)</strong> ' + escapeHtml(q.option_c) + '</div>');
                    viewportHtml.push('    <div><strong style="color: #0f172a;">(D)</strong> ' + escapeHtml(q.option_d) + '</div>');
                    viewportHtml.push('  </div>');
                }
                viewportHtml.push('  <hr style="border: 0; border-top: 1px solid #cbd5e1; margin: 8px 0;">');
                viewportHtml.push('</div>');

            } else {
                // Normal Mode Item (Spacious Boxed Card)
                viewportHtml.push('<div style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; background: #ffffff; font-size: ' + fontSize + 'px; line-height: 1.5; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">');
                if (q.question_image) {
                    // Image-based Question: 50% left (question and options), 50% right (larger image)
                    viewportHtml.push('  <table style="width: 100%; border-collapse: collapse;">');
                    viewportHtml.push('    <tr>');
                    viewportHtml.push('      <td style="width: 50%; vertical-align: top; padding-right: 14px;">');
                    viewportHtml.push('        <div style="margin-bottom: 6px;">');
                    viewportHtml.push('          <span style="font-weight: 800; color: #0f172a; margin-right: 4px;">Q' + num + '.</span>');
                    viewportHtml.push('          <span style="font-weight: ' + qWeight + '; font-style: ' + qStyle + '; text-decoration: ' + qDecor + '; white-space: pre-wrap;">' + escapeHtml(q.question_text) + '</span>');
                    viewportHtml.push('        </div>');
                    viewportHtml.push('        <div style="display: flex; flex-direction: column; gap: 5px; margin-top: 6px;">');
                    viewportHtml.push('          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; font-size: ' + Math.max(9, fontSize - 1) + 'px;"><span style="display: inline-block; width: 17px; height: 17px; line-height: 17px; text-align: center; background: #e2e8f0; color: #0f172a; font-weight: 800; border-radius: 50%; margin-right: 5px; font-size: 9.5px;">A</span> ' + escapeHtml(q.option_a) + '</div>');
                    viewportHtml.push('          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; font-size: ' + Math.max(9, fontSize - 1) + 'px;"><span style="display: inline-block; width: 17px; height: 17px; line-height: 17px; text-align: center; background: #e2e8f0; color: #0f172a; font-weight: 800; border-radius: 50%; margin-right: 5px; font-size: 9.5px;">B</span> ' + escapeHtml(q.option_b) + '</div>');
                    viewportHtml.push('          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; font-size: ' + Math.max(9, fontSize - 1) + 'px;"><span style="display: inline-block; width: 17px; height: 17px; line-height: 17px; text-align: center; background: #e2e8f0; color: #0f172a; font-weight: 800; border-radius: 50%; margin-right: 5px; font-size: 9.5px;">C</span> ' + escapeHtml(q.option_c) + '</div>');
                    viewportHtml.push('          <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 4px 8px; font-size: ' + Math.max(9, fontSize - 1) + 'px;"><span style="display: inline-block; width: 17px; height: 17px; line-height: 17px; text-align: center; background: #e2e8f0; color: #0f172a; font-weight: 800; border-radius: 50%; margin-right: 5px; font-size: 9.5px;">D</span> ' + escapeHtml(q.option_d) + '</div>');
                    viewportHtml.push('        </div>');
                    viewportHtml.push('      </td>');
                    viewportHtml.push('      <td style="width: 50%; vertical-align: middle; text-align: center;">');
                    viewportHtml.push('        <img src="' + escapeHtml(q.question_image) + '" style="max-height: 220px; max-width: 98%; border: 1px solid #cbd5e1; border-radius: 5px;" alt="Diagram">');
                    viewportHtml.push('      </td>');
                    viewportHtml.push('    </tr>');
                    viewportHtml.push('  </table>');
                } else {
                    // Text-only: full-width with 2-column options
                    viewportHtml.push('  <div style="margin-bottom: 6px;">');
                    viewportHtml.push('    <span style="font-weight: 800; color: #0f172a; margin-right: 4px;">Q' + num + '.</span>');
                    viewportHtml.push('    <span style="font-weight: ' + qWeight + '; font-style: ' + qStyle + '; text-decoration: ' + qDecor + '; white-space: pre-wrap;">' + escapeHtml(q.question_text) + '</span>');
                    viewportHtml.push('  </div>');
                    viewportHtml.push('  <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; font-size: ' + Math.max(9.5, fontSize - 0.5) + 'px;">');
                    viewportHtml.push('    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 6px 10px; display: flex; align-items: center; gap: 6px;"><span style="width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; color: #0f172a; font-weight: 800; font-size: 10px; display: inline-flex; align-items: center; justify-content: center;">A</span> ' + escapeHtml(q.option_a) + '</div>');
                    viewportHtml.push('    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 6px 10px; display: flex; align-items: center; gap: 6px;"><span style="width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; color: #0f172a; font-weight: 800; font-size: 10px; display: inline-flex; align-items: center; justify-content: center;">B</span> ' + escapeHtml(q.option_b) + '</div>');
                    viewportHtml.push('    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 6px 10px; display: flex; align-items: center; gap: 6px;"><span style="width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; color: #0f172a; font-weight: 800; font-size: 10px; display: inline-flex; align-items: center; justify-content: center;">C</span> ' + escapeHtml(q.option_c) + '</div>');
                    viewportHtml.push('    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 6px 10px; display: flex; align-items: center; gap: 6px;"><span style="width: 18px; height: 18px; border-radius: 50%; background: #e2e8f0; color: #0f172a; font-weight: 800; font-size: 10px; display: inline-flex; align-items: center; justify-content: center;">D</span> ' + escapeHtml(q.option_d) + '</div>');
                    viewportHtml.push('  </div>');
                }
                viewportHtml.push('</div>');
            }
        });
        viewportHtml.push('</div>'); // End questions container
        viewportHtml.push('</div>'); // End page body
        viewportHtml.push(makeFooter(pageNum)); // Footer for this page
        viewportHtml.push('</div>'); // End .pdf-sheet
    });

    // Answer Key Dedicated Page if requested
    if (pdfConfig.includeAnswers) {
        var keyPageNum = totalPages;
        viewportHtml.push('<div class="pdf-sheet">');
        viewportHtml.push(watermarkHtml);
        viewportHtml.push('<div style="flex: 1; position: relative; z-index: 2;">');
        viewportHtml.push('  <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px;">');
        viewportHtml.push('    <h3 style="margin: 0 0 4px 0; font-size: 16px; color: #0f172a; text-transform: uppercase; font-weight: 800;">CONFIDENTIAL &bull; OFFICIAL ANSWER KEY</h3>');
        viewportHtml.push('    <div style="font-size: 11px; color: #64748b;">' + escapeHtml(testDetails.title) + ' &bull; Evaluator Copy</div>');
        viewportHtml.push('  </div>');
        viewportHtml.push('  <table style="width: 100%; border-collapse: collapse; font-size: 10px; text-align: center;">');
        viewportHtml.push('    <thead><tr style="background: #0f172a; color: #fff;"><th style="padding: 6px;">Q No</th><th style="padding: 6px;">Correct Option</th><th style="padding: 6px;">Marks (+)</th><th style="padding: 6px;">Negative (-)</th><th style="padding: 6px; text-align: left;">Key Statement</th></tr></thead>');
        viewportHtml.push('    <tbody>');
        questionsData.forEach(function(q, idx) {
            var correctKey = (q.correct_option || 'A').toUpperCase();
            var textKey = 'option_' + correctKey.toLowerCase();
            var textVal = q[textKey] || '—';
            viewportHtml.push('    <tr style="border-bottom: 1px solid #cbd5e1;' + (idx % 2 === 1 ? ' background: #f8fafc;' : '') + '">');
            viewportHtml.push('      <td style="padding: 6px;"><strong>Q' + (idx + 1) + '</strong></td>');
            viewportHtml.push('      <td style="padding: 6px; font-weight: 800; color: #059669; font-size: 12px;">' + correctKey + '</td>');
            viewportHtml.push('      <td style="padding: 6px;">+' + q.marks + '</td>');
            viewportHtml.push('      <td style="padding: 6px;">-' + q.negative_marks + '</td>');
            viewportHtml.push('      <td style="padding: 6px; text-align: left;">' + escapeHtml(textVal.substring(0, 45)) + '...</td>');
            viewportHtml.push('    </tr>');
        });
        viewportHtml.push('    </tbody>');
        viewportHtml.push('  </table>');
        viewportHtml.push('</div>');
        viewportHtml.push(makeFooter(keyPageNum));
        viewportHtml.push('</div>'); // End .pdf-sheet
    }

    viewport.innerHTML = viewportHtml.join('');

    var summaryElem = document.getElementById('pdfPreviewSummary');
    if (summaryElem) {
        summaryElem.innerHTML = 'Ready to export &bull; ' + totalPages + ' Page' + (totalPages > 1 ? 's' : '') + ' (' + (isMinimal ? 'Minimal' : 'Normal') + ') &bull; A4 Vector Render';
    }
}

// Download PDF with current configs
function downloadConfiguredPdf() {
    var url = '../pdf/question_paper.php?test_id=' + testDetails.id +
        '&mode=' + encodeURIComponent(pdfConfig.mode) +
        '&font_size=' + pdfConfig.fontSize +
        '&bold=' + (pdfConfig.bold ? 1 : 0) +
        '&italic=' + (pdfConfig.italic ? 1 : 0) +
        '&underline=' + (pdfConfig.underline ? 1 : 0) +
        '&include_answers=' + (pdfConfig.includeAnswers ? 1 : 0);

    if (window.ModusUI && ModusUI.toast) {
        ModusUI.toast('Generating ' + pdfConfig.mode.toUpperCase() + ' PDF question paper...', 'info', 3000);
    }

    window.location.href = url;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePdfPreviewModal();
    }
});
</script>
<?php endif; ?>

<?php layout_footer(); ?>
