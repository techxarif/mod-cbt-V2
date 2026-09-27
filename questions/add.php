<?php
// questions/add.php — Add Question with Auto-Option Parsing & Exam Preview
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
requireTeacher();

$test_id = int_param('test_id', 0);
if ($test_id <= 0) {
    redirect('index.php');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found');
}

$error   = '';
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();

    $question_text  = trim(str_param('question_text', '', 'post'));
    $option_a       = trim(str_param('option_a', '', 'post'));
    $option_b       = trim(str_param('option_b', '', 'post'));
    $option_c       = trim(str_param('option_c', '', 'post'));
    $option_d       = trim(str_param('option_d', '', 'post'));
    $correct_option = strtoupper(str_param('correct_option', 'A', 'post'));
    $marks          = (float)($_POST['marks'] ?? $test['total_marks'] ?? MARKS_CORRECT);
    $negative_marks = (float)($_POST['negative_marks'] ?? $test['negative_marks'] ?? MARKS_WRONG);

    // Server-side fallback auto-detection if options were left blank
    if ($question_text !== '' && ($option_a === '' || $option_b === '' || $option_c === '' || $option_d === '')) {
        $clean_stem = function($raw) {
            return preg_replace('/^(?:Q(?:uestion)?[\.\:\s\-]*\d*[\.\:\-\)]*|\d+[\.\)])\s*/i', '', trim($raw));
        };

        // 1. Priority 1: Sequential A, B, C, D (case-insensitive)
        $pat_abcd = '/([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?[Aa][\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?[Bb][\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?[Cc][\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?[Dd][\)\]\.\:]\s*)([\s\S]*)$/';
        if (preg_match($pat_abcd, $question_text, $m)) {
            $question_text = $clean_stem($m[1]);
            $option_a = trim($m[2]);
            $option_b = trim($m[3]);
            $option_c = trim($m[4]);
            $option_d = trim($m[5]);
        } else {
            // 2. Priority 2: Sequential 1, 2, 3, 4
            $pat_1234 = '/([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?1[\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?2[\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?3[\)\]\.\:]\s*)([\s\S]*?)(?:^|\n|\s{2,}|\t)(?:[\(\[]?4[\)\]\.\:]\s*)([\s\S]*)$/';
            if (preg_match($pat_1234, $question_text, $m)) {
                $question_text = $clean_stem($m[1]);
                $option_a = trim($m[2]);
                $option_b = trim($m[3]);
                $option_c = trim($m[4]);
                $option_d = trim($m[5]);
            }
        }
    }

    if ($question_text === '' || $option_a === '' || $option_b === '' || $option_c === '' || $option_d === '') {
        $error = 'Please fill in the question text and all four options.';
    } elseif (!in_array($correct_option, ['A', 'B', 'C', 'D'], true)) {
        $error = 'Please select a valid correct option (A, B, C, or D).';
    } else {
        // Image upload with validation from includes/upload.php
        $image_path = null;
        if (!empty($_FILES['image']['name'])) {
            $upload_err = '';
            $uploaded = upload_question_image($_FILES['image'], $upload_err);
            if ($uploaded === false && $upload_err !== '') {
                $error = $upload_err;
            } else {
                $image_path = $uploaded;
            }
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO questions (question_text, question_image, option_a, option_b, option_c, option_d, correct_option, marks, negative_marks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $question_text,
                    $image_path,
                    $option_a,
                    $option_b,
                    $option_c,
                    $option_d,
                    $correct_option,
                    $marks,
                    $negative_marks
                ]);
                $question_id = (int)$pdo->lastInsertId();

                // Determine next order number
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(question_order), 0) + 1 FROM test_questions WHERE test_id = ?");
                $stmt->execute([$test_id]);
                $next_order = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare("INSERT INTO test_questions (test_id, question_id, question_order) VALUES (?, ?, ?)");
                $stmt->execute([$test_id, $question_id, $next_order]);

                $pdo->commit();
                redirect("index.php?test_id=$test_id");
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($image_path) {
                    safe_unlink($image_path);
                }
                $ref = app_log('error', 'Add question error: ' . $e->getMessage());
                $error = 'Failed to add question. Ref: ' . $ref;
            }
        }
    }
}

$page_title = 'Add Question — ' . APP_NAME;
layout_header($page_title, 'questions');
?>

<div class="page-content" style="max-width: 860px;">
    <div class="page-header">
        <div>
            <h1 class="page-title">Add Question</h1>
            <p class="page-subtitle">Adding to <strong><?= e($test['title']) ?></strong></p>
        </div>
        <a href="index.php?test_id=<?= $test['id'] ?>" class="btn btn-secondary">← Back to Questions</a>
    </div>

    <?php if ($error): ?>
        <?= alert_box('error', $error) ?>
    <?php endif; ?>

    <div class="card" style="padding: 26px;">
        <form method="POST" enctype="multipart/form-data" id="addQuestionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="test_id" value="<?= $test['id'] ?>">

            <!-- Question Statement & Auto-Parser -->
            <div class="form-group" style="margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 8px;">
                    <label class="form-label" style="margin-bottom: 0; font-weight: 700;">Question Statement</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="btnAutoParse" onclick="runAutoDetection()" title="Automatically detects and extracts question text and 4 options from clipboard text" style="display: inline-flex; align-items: center; gap: 7px; font-weight: 600;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--green);"><path d="m21.64 3.64-1.28-1.28a1.21 1.21 0 0 0-1.72 0L2.36 18.64a1.21 1.21 0 0 0 0 1.72l1.28 1.28a1.2 1.2 0 0 0 1.72 0L21.64 5.36a1.2 1.2 0 0 0 0-1.72Z"/><path d="m14 7 3 3"/><path d="M5 6v4"/><path d="M19 14v4"/><path d="M10 2v2"/><path d="M7 8H3"/><path d="M21 16h-4"/><path d="M11 3H9"/></svg>
                        Auto-Detect & Parse Options
                    </button>
                </div>
                <textarea name="question_text" id="questionTextInput" class="form-textarea" placeholder="Type or paste your question here... (If your text contains options like A), B), C), D) or 1), 2), 3), 4), our auto-detector will classify them automatically!)" rows="5" required autofocus style="line-height: 1.6; font-size: 14px;"><?= e($_POST['question_text'] ?? '') ?></textarea>
                <div style="font-size: 11.5px; color: var(--muted); margin-top: 5px;">
                    💡 <em>Tip: You can paste a full question with options. Options starting with A), a), 1), I), A., 1., etc. will be detected automatically!</em>
                </div>
            </div>

            <!-- Custom Styled File Upload Zone -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" style="font-weight: 700; margin-bottom: 8px; display: block;">Question Diagram / Image (Optional — PNG, JPG, WEBP)</label>
                
                <div class="custom-file-upload" id="fileDropZone">
                    <input type="file" name="image" id="questionImageInput" accept="image/*" style="display: none;" onchange="handleFileSelected(this)">
                    
                    <div class="file-upload-row">
                        <label for="questionImageInput" class="file-upload-btn">
                            <span>📷</span> Choose Image File
                        </label>
                        <span class="file-upload-hint" id="fileUploadHint">or drag & drop image here / paste from clipboard (Ctrl+V)</span>
                    </div>

                    <!-- Selected Preview Area (Hidden by default until image chosen) -->
                    <div class="file-preview-wrap" id="filePreviewWrap" style="display: none !important;">
                        <img src="" class="file-preview-thumb" id="filePreviewThumb">
                        <div class="file-preview-info">
                            <div class="file-preview-name" id="filePreviewName">image.png</div>
                            <div class="file-preview-size" id="filePreviewSize">0 KB</div>
                        </div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSelectedFile()" style="margin-left: auto;">
                            ✕ Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Options Grid (A, B, C, D) -->
            <div style="margin-bottom: 20px;">
                <div style="font-size: 13px; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <span>📝</span> Answer Options (4 Required)
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: rgba(32, 229, 150, 0.15); color: var(--green); padding: 2px 7px; border-radius: 4px; font-weight: 800;">A</span>
                            Option A
                        </label>
                        <input type="text" name="option_a" id="optionAInput" class="form-input" placeholder="Option A statement" value="<?= e($_POST['option_a'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: rgba(85, 168, 255, 0.15); color: #55a8ff; padding: 2px 7px; border-radius: 4px; font-weight: 800;">B</span>
                            Option B
                        </label>
                        <input type="text" name="option_b" id="optionBInput" class="form-input" placeholder="Option B statement" value="<?= e($_POST['option_b'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: rgba(255, 180, 84, 0.15); color: #ffb454; padding: 2px 7px; border-radius: 4px; font-weight: 800;">C</span>
                            Option C
                        </label>
                        <input type="text" name="option_c" id="optionCInput" class="form-input" placeholder="Option C statement" value="<?= e($_POST['option_c'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 6px;">
                            <span style="background: rgba(168, 85, 247, 0.15); color: #c084fc; padding: 2px 7px; border-radius: 4px; font-weight: 800;">D</span>
                            Option D
                        </label>
                        <input type="text" name="option_d" id="optionDInput" class="form-input" placeholder="Option D statement" value="<?= e($_POST['option_d'] ?? '') ?>" required>
                    </div>
                </div>
            </div>

            <!-- Scoring and Correct Option Controls -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-top: 14px; padding-top: 18px; border-top: 1px solid var(--line);">
                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Designated Correct Option</label>
                    <select name="correct_option" id="correctOptionSelect" class="form-select">
                        <option value="A" <?= ($_POST['correct_option'] ?? '') === 'A' ? 'selected' : '' ?>>Option A</option>
                        <option value="B" <?= ($_POST['correct_option'] ?? '') === 'B' ? 'selected' : '' ?>>Option B</option>
                        <option value="C" <?= ($_POST['correct_option'] ?? '') === 'C' ? 'selected' : '' ?>>Option C</option>
                        <option value="D" <?= ($_POST['correct_option'] ?? '') === 'D' ? 'selected' : '' ?>>Option D</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Positive Marks (+)</label>
                    <input type="number" step="0.25" name="marks" id="marksInput" class="form-input" value="<?= (float)($test['total_marks'] ?? 4.0) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-weight: 700;">Negative Marks (-)</label>
                    <input type="number" step="0.25" name="negative_marks" id="negMarksInput" class="form-input" value="<?= (float)($test['negative_marks'] ?? 1.0) ?>" required>
                </div>
            </div>

            <div style="margin-top: 28px; display: flex; justify-content: flex-end; gap: 12px; flex-wrap: wrap;">
                <a href="index.php?test_id=<?= $test['id'] ?>" class="btn btn-secondary">Cancel</a>
                <button type="button" class="btn btn-primary" onclick="openExamPreviewModal()">
                    <span>👁</span> Preview & Save Question
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================================
     EXAM-STYLE QUESTION PREVIEW MODAL (Aesthetic Popup)
     ============================================================ -->
<div class="modus-modal-backdrop" id="questionPreviewModal" onclick="if(event.target===this) closeExamPreviewModal()">
    <div class="modus-modal-card" style="max-width: 680px; width: 95%; max-height: 90vh; overflow-y: auto; text-align: left; border-radius: 16px;">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.02);">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="background: rgba(32, 229, 150, 0.15); color: var(--green); padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px;">EXAM PANEL PREVIEW</span>
                <span id="previewMarksTag" style="font-size: 12px; color: var(--muted); font-weight: 600;">+4 / -1 Marks</span>
            </div>
            <button type="button" style="background: none; border: none; color: var(--muted); font-size: 22px; cursor: pointer; line-height: 1; padding: 4px 8px; border-radius: 6px;" onclick="closeExamPreviewModal()" title="Close Preview">&times;</button>
        </div>

        <div style="padding: 24px 26px;">
            <!-- Question text statement -->
            <div id="previewStatement" style="font-size: 15px; line-height: 1.7; color: var(--text); font-weight: 600; margin-bottom: 18px; white-space: pre-wrap; word-break: break-word;"></div>

            <!-- Question image preview -->
            <div id="previewImageContainer" style="display: none; margin-bottom: 22px; text-align: center; background: #070c13; padding: 14px; border-radius: 10px; border: 1px solid var(--line);">
                <img id="previewImageSrc" src="" alt="Question Diagram" style="max-width: 100%; max-height: 320px; border-radius: 6px; object-fit: contain;">
            </div>

            <!-- Options Preview List -->
            <div style="display: flex; flex-direction: column; gap: 10px;" id="previewOptionsList">
                <!-- Dynamically generated options -->
            </div>
        </div>

        <div style="padding: 16px 26px; background: rgba(10, 15, 22, 0.85); border-top: 1px solid var(--line); display: flex; justify-content: flex-end; gap: 12px; align-items: center;">
            <button type="button" class="btn btn-secondary" onclick="closeExamPreviewModal()">
                ← Edit Further
            </button>
            <button type="button" class="btn btn-primary" onclick="confirmAndSubmitForm()">
                ✓ Confirm & Add Question
            </button>
        </div>
    </div>
</div>

<script>
// ============================================================
// 1. FILE UPLOAD & DRAG/DROP & CLIPBOARD PASTE
// ============================================================
var currentImageFile = null;

function handleFileSelected(input) {
    if (input.files && input.files[0]) {
        setQuestionImage(input.files[0]);
    }
}

function setQuestionImage(file) {
    if (!file.type.match(/^image\//)) {
        if (window.ModusUI && ModusUI.alert) {
            ModusUI.alert('Only image files (PNG, JPG, WEBP, GIF) are allowed.', 'Invalid File', 'error');
        } else {
            alert('Only image files are allowed.');
        }
        return;
    }

    if (file.size > 5 * 1024 * 1024) {
        if (window.ModusUI && ModusUI.alert) {
            ModusUI.alert('Image size exceeds 5MB limit.', 'File Too Large', 'error');
        } else {
            alert('File exceeds 5MB.');
        }
        return;
    }

    currentImageFile = file;

    var reader = new FileReader();
    reader.onload = function(e) {
        var thumb = document.getElementById('filePreviewThumb');
        thumb.src = e.target.result;
        thumb.alt = file.name;
        document.getElementById('filePreviewName').textContent = file.name;
        document.getElementById('filePreviewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
        var wrap = document.getElementById('filePreviewWrap');
        wrap.style.setProperty('display', 'flex', 'important');
        wrap.classList.add('active');
        document.getElementById('fileUploadHint').style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeSelectedFile() {
    var input = document.getElementById('questionImageInput');
    input.value = '';
    currentImageFile = null;
    var wrap = document.getElementById('filePreviewWrap');
    wrap.style.setProperty('display', 'none', 'important');
    wrap.classList.remove('active');
    var thumb = document.getElementById('filePreviewThumb');
    thumb.removeAttribute('src');
    thumb.removeAttribute('alt');
    document.getElementById('fileUploadHint').style.display = '';
}

// Drag & Drop
var dropZone = document.getElementById('fileDropZone');
['dragenter', 'dragover'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, function(e) {
        e.preventDefault();
        dropZone.classList.add('dragover');
    }, false);
});

['dragleave', 'drop'].forEach(function(eventName) {
    dropZone.addEventListener(eventName, function(e) {
        e.preventDefault();
        dropZone.classList.remove('dragover');
    }, false);
});

dropZone.addEventListener('drop', function(e) {
    var dt = e.dataTransfer;
    if (dt && dt.files && dt.files.length > 0) {
        var file = dt.files[0];
        setQuestionImage(file);
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        document.getElementById('questionImageInput').files = dataTransfer.files;
    }
});

// Clipboard Image Paste support
document.addEventListener('paste', function(e) {
    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            var blob = items[i].getAsFile();
            setQuestionImage(blob);
            var dt = new DataTransfer();
            dt.items.add(blob);
            document.getElementById('questionImageInput').files = dt.files;
            if (window.ModusUI && ModusUI.toast) {
                ModusUI.toast('Image attached from clipboard!', 'success');
            }
            break;
        }
    }
});

// ============================================================
// 2. INTELLIGENT OPTION PARSING & CLASSIFICATION
// ============================================================
function parseOptionsFromText(fullText) {
    if (!fullText || !fullText.trim()) return null;
    var text = fullText.trim();

    // Check for Answer key at the bottom (e.g. Ans: B, Answer: (C), Key: 2)
    var detectedAnswer = null;
    var ansMatch = text.match(/(?:\n|\s+)(?:Ans(?:wer)?|Key)\s*[\:\-\=]?\s*[\(\[]?([A-Da-d1-4]|(?:[iI]{1,3}|[iI][vV]))[\)\]\.]?/i);
    if (ansMatch) {
        var rawAns = ansMatch[1].toUpperCase();
        var mapAns = { '1': 'A', '2': 'B', '3': 'C', '4': 'D', 'I': 'A', 'II': 'B', 'III': 'C', 'IV': 'D' };
        detectedAnswer = mapAns[rawAns] || rawAns;
        text = text.substring(0, ansMatch.index).trim();
    }

    function cleanStem(rawStem) {
        return rawStem.replace(/^(?:Q(?:uestion)?[\.\:\s\-]*\d*[\.\:\-\)]*|\d+[\.\)])\s*/i, '').trim();
    }

    // 1. Priority 1: Sequential A, B, C, D (case-insensitive)
    // Supports standard lines, single spaces, and glued formats like "transparent?A) WoodB) CardboardC) GlassD) Iron"
    var patABCD = /([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[Aa][\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[Bb][\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[Cc][\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[Dd][\)\]\.\:]\s*)([\s\S]*)$/;
    var m = text.match(patABCD);
    if (m) {
        var stem = cleanStem(m[1]);
        return {
            status: 'success',
            question: stem,
            options: [m[2].trim(), m[3].trim(), m[4].trim(), m[5].trim()],
            detectedAnswer: detectedAnswer
        };
    }

    // 2. Priority 2: Sequential numbers 1, 2, 3, 4
    var pat1234 = /([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?1[\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?2[\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?3[\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?4[\)\]\.\:]\s*)([\s\S]*)$/;
    m = text.match(pat1234);
    if (m) {
        var stem = cleanStem(m[1]);
        return {
            status: 'success',
            question: stem,
            options: [m[2].trim(), m[3].trim(), m[4].trim(), m[5].trim()],
            detectedAnswer: detectedAnswer
        };
    }

    // 3. Priority 3: Sequential Roman numerals I, II, III, IV (when no A-D or 1-4 present)
    var patRoman = /([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[iI][\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?(?:ii|II)[\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?(?:iii|III)[\)\]\.\:]\s*)([\s\S]*?)(?:^|\s+|(?<=[^\s]))(?:[\(\[]?(?:iv|IV)[\)\]\.\:]\s*)([\s\S]*)$/;
    m = text.match(patRoman);
    if (m) {
        var stem = cleanStem(m[1]);
        return {
            status: 'success',
            question: stem,
            options: [m[2].trim(), m[3].trim(), m[4].trim(), m[5].trim()],
            detectedAnswer: detectedAnswer
        };
    }

    // 4. Check for overflow if user provided > 4 letter options
    var letterMatches = text.match(/(?:^|\s+|(?<=[^\s]))(?:[\(\[]?[A-Za-z1-9][\)\]\.\:]\s*)/g);
    if (letterMatches && letterMatches.length > 4) {
        return { status: 'overflow', count: letterMatches.length };
    }

    return null;
}

function runAutoDetection() {
    var textarea = document.getElementById('questionTextInput');
    var rawText = textarea.value;

    var result = parseOptionsFromText(rawText);
    if (!result) {
        if (window.ModusUI && ModusUI.alert) {
            ModusUI.alert('Could not detect 4 distinct options in the question text. Please make sure options start with A), B), C), D) or 1), 2), 3), 4).', 'Option Detection', 'info');
        } else {
            alert('Could not detect options.');
        }
        return;
    }

    if (result.status === 'overflow') {
        var msg = 'Detected ' + result.count + ' options in the text. Modus CBT supports 4 standard options (A, B, C, D). Please adjust the text or add manually.';
        if (window.ModusUI && ModusUI.alert) {
            ModusUI.alert(msg, 'Multiple Options Detected', 'warning');
        } else {
            alert(msg);
        }
        return;
    }

    if (result.status === 'success') {
        if (result.question) {
            textarea.value = result.question;
        }
        document.getElementById('optionAInput').value = result.options[0];
        document.getElementById('optionBInput').value = result.options[1];
        document.getElementById('optionCInput').value = result.options[2];
        document.getElementById('optionDInput').value = result.options[3];

        if (result.detectedAnswer && ['A', 'B', 'C', 'D'].indexOf(result.detectedAnswer) !== -1) {
            document.getElementById('correctOptionSelect').value = result.detectedAnswer;
        }

        if (window.ModusUI && ModusUI.toast) {
            ModusUI.toast('Parsed question and 4 options successfully!', 'success', 3000);
        }
    }
}

// Auto-parse on paste if text contains option indicators
document.getElementById('questionTextInput').addEventListener('paste', function(e) {
    setTimeout(function() {
        var text = document.getElementById('questionTextInput').value;
        if (text && (text.indexOf('(A)') !== -1 || text.indexOf('A)') !== -1 || text.indexOf('A.') !== -1 || text.indexOf('(1)') !== -1 || text.indexOf('1)') !== -1)) {
            runAutoDetection();
        }
    }, 80);
});

// ============================================================
// 3. EXAM-STYLE PREVIEW MODAL BEFORE SAVING
// ============================================================
function openExamPreviewModal() {
    var qText = document.getElementById('questionTextInput').value.trim();
    var optA = document.getElementById('optionAInput').value.trim();
    var optB = document.getElementById('optionBInput').value.trim();
    var optC = document.getElementById('optionCInput').value.trim();
    var optD = document.getElementById('optionDInput').value.trim();
    var correct = document.getElementById('correctOptionSelect').value;
    var marks = document.getElementById('marksInput').value;
    var negMarks = document.getElementById('negMarksInput').value;

    if (!qText || !optA || !optB || !optC || !optD) {
        if (window.ModusUI && ModusUI.alert) {
            ModusUI.alert('Please complete the question text and all 4 options before previewing.', 'Incomplete Question', 'warning');
        } else {
            alert('Please fill question and all 4 options.');
        }
        return;
    }

    // Populate Modal Content
    document.getElementById('previewMarksTag').textContent = '+' + marks + ' / -' + negMarks + ' Marks';
    document.getElementById('previewStatement').textContent = qText;

    // Image preview
    var thumbSrc = document.getElementById('filePreviewThumb').src;
    var hasImg = document.getElementById('filePreviewWrap').classList.contains('active');
    var imgContainer = document.getElementById('previewImageContainer');
    var imgEl = document.getElementById('previewImageSrc');

    if (hasImg && thumbSrc) {
        imgEl.src = thumbSrc;
        imgContainer.style.display = 'block';
    } else {
        imgContainer.style.display = 'none';
        imgEl.src = '';
    }

    // Generate Options List
    var opts = [
        { key: 'A', text: optA },
        { key: 'B', text: optB },
        { key: 'C', text: optC },
        { key: 'D', text: optD }
    ];

    var listHtml = '';
    opts.forEach(function(o) {
        var isCorrect = (o.key === correct);
        var borderStyle = isCorrect ? 'border: 2px solid var(--green); background: rgba(32, 229, 150, 0.08);' : 'border: 1px solid var(--line); background: var(--panel2);';
        var badge = isCorrect ? '<span style="margin-left: auto; background: var(--green); color: #04120b; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 999px;">✓ CORRECT OPTION</span>' : '';

        listHtml += [
            '<div style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; ' + borderStyle + '">',
                '<span style="width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; background: rgba(255, 255, 255, 0.06); color: var(--text);">' + o.key + '</span>',
                '<span style="font-size: 14px; color: var(--text); font-weight: 500;">' + escapeHtml(o.text) + '</span>',
                badge,
            '</div>'
        ].join('');
    });

    document.getElementById('previewOptionsList').innerHTML = listHtml;

    // Show modal
    var modal = document.getElementById('questionPreviewModal');
    modal.classList.add('active');
}

function closeExamPreviewModal() {
    var modal = document.getElementById('questionPreviewModal');
    modal.classList.remove('active');
}

function confirmAndSubmitForm() {
    closeExamPreviewModal();
    document.getElementById('addQuestionForm').submit();
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeExamPreviewModal();
    }
});

function escapeHtml(string) {
    var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };
    return String(string).replace(/[&<>"']/g, function (s) {
        return entityMap[s];
    });
}
</script>

<?php layout_footer(); ?>
