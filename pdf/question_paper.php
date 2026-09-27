<?php

// ============================================================
// MODUS CBT — Question Paper PDF Generator
// Generates Normal (Aesthetic & Watermarked) or Minimal (Paper Saver) PDF
// ============================================================

require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$teacher = currentTeacher();
$teacherName = $teacher['name'] ?? 'Faculty';
$teacherUsername = $teacher['username'] ?? 'Teacher';

$test_id = int_param('test_id', 0);
if ($test_id <= 0) {
    abort_html(400, 'Invalid Request', 'Please specify a valid test ID.');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found', 'The requested test could not be found.');
}

// Fetch all questions for this test
$questions = get_test_questions($test_id, true);
if (empty($questions)) {
    abort_html(400, 'No Questions Found', 'This examination has no questions added yet.');
}

// Options & Customizations from request
$mode = strtolower(trim(str_param('mode', 'normal')));
if (!in_array($mode, ['normal', 'minimal'], true)) {
    $mode = 'normal';
}

$fontSize = int_param('font_size', $mode === 'minimal' ? 10 : 12);
if ($fontSize < 8 || $fontSize > 20) {
    $fontSize = $mode === 'minimal' ? 10 : 12;
}

$isBold = int_param('bold', 0) === 1;
$isItalic = int_param('italic', 0) === 1;
$isUnderline = int_param('underline', 0) === 1;
$includeAnswers = int_param('include_answers', 0) === 1;

$watermarkText = trim(str_param('watermark', ''));
if ($watermarkText === '') {
    $watermarkText = $teacherUsername . ' • CBT by Modus';
}

/**
 * Convert local image to base64 Data URI for rock-solid Dompdf compatibility
 */
function embed_image_base64(?string $relPath): ?string
{
    if (!extension_loaded('gd')) {
        return null;
    }
    if (empty($relPath)) {
        return null;
    }
    $absPath = dirname(__DIR__) . '/' . ltrim($relPath, '/');
    if (!file_exists($absPath) || !is_file($absPath)) {
        return null;
    }
    $mime = mime_content_type($absPath) ?: 'image/jpeg';
    $data = @file_get_contents($absPath);
    if (!$data) {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

// Pre-process questions images
foreach ($questions as &$q) {
    $q['image_data_uri'] = embed_image_base64($q['question_image'] ?? null);
}
unset($q);

// Embed application logo for footer branding
$logoDataUri = embed_image_base64('assets/img/logo.png');

// Calculate total marks across questions if not set on test
$totalCalculatedMarks = 0;
foreach ($questions as $q) {
    $totalCalculatedMarks += (float)$q['marks'];
}

/**
 * Generate PDF HTML markup
 */
function generate_question_paper_html(
    array $test,
    array $questions,
    string $mode,
    int $fontSize,
    bool $isBold,
    bool $isItalic,
    bool $isUnderline,
    string $watermarkText,
    bool $includeAnswers,
    string $teacherName,
    string $teacherUsername,
    float $totalCalculatedMarks,
    ?string $logoDataUri = null
): string {
    $appName = 'CBT by Modus';
    $generatedAt = date('d M Y, H:i');
    $qFontWeight = $isBold ? 'bold' : 'normal';
    $qFontStyle = $isItalic ? 'italic' : 'normal';
    $qTextDecoration = $isUnderline ? 'underline' : 'none';

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($test['title']) ?> — Question Paper</title>
        <style>
            @page {
                margin: <?= $mode === 'minimal' ? '18px 22px 38px 22px' : '26px 30px 45px 30px' ?>;
            }

            body {
                font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
                font-size: <?= $fontSize ?>px;
                line-height: <?= $mode === 'minimal' ? '1.35' : '1.5' ?>;
                color: #111827;
                background: #ffffff;
            }

            .paper-footer {
                position: fixed;
                bottom: <?= $mode === 'minimal' ? '-26px' : '-32px' ?>;
                left: 0;
                right: 0;
                height: 22px;
                font-size: 8px;
                color: #64748b;
                border-top: 1px solid #cbd5e1;
                padding-top: 4px;
            }

            /* Diagonal Watermark for both Normal & Minimal Modes */
            .watermark {
                position: fixed;
                top: 40%;
                left: -15%;
                right: -15%;
                text-align: center;
                transform: rotate(-32deg);
                font-size: 34px;
                font-weight: 700;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 5px;
                text-decoration: none !important;
                text-decoration-line: none !important;
                border: none !important;
                border-bottom: none !important;
                outline: none !important;
                z-index: 9999;
                opacity: 0.08;
            }

            /* Header Section */
            .paper-header {
                text-align: center;
                border-bottom: 2px solid #1e293b;
                padding-bottom: <?= $mode === 'minimal' ? '8px' : '12px' ?>;
                margin-bottom: <?= $mode === 'minimal' ? '10px' : '16px' ?>;
            }
            .brand-tag {
                font-size: 11px;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 1.5px;
                color: #059669;
                margin-bottom: 2px;
            }
            .exam-title {
                font-size: <?= $mode === 'minimal' ? '17px' : '20px' ?>;
                font-weight: 800;
                color: #0f172a;
                margin: 2px 0 6px 0;
            }

            /* Metadata Grid */
            .meta-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: <?= $mode === 'minimal' ? '8px' : '14px' ?>;
                font-size: <?= max(9, $fontSize - 1) ?>px;
            }
            .meta-table td {
                padding: 3px 4px;
                vertical-align: middle;
            }

            /* Candidate Details Box */
            .candidate-box {
                border: 1px dashed #94a3b8;
                padding: 6px 10px;
                margin-bottom: <?= $mode === 'minimal' ? '10px' : '16px' ?>;
                font-size: 9.5px;
                color: #475569;
            }

            /* Questions Section */
            .question-item {
                page-break-inside: avoid;
                <?php if ($mode === 'normal'): ?>
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                padding: 12px 14px;
                margin-bottom: 14px;
                background: #ffffff;
                <?php else: ?>
                margin-bottom: 8px;
                padding-bottom: 8px;
                border-bottom: 1px solid #cbd5e1;
                <?php endif; ?>
            }

            .q-header {
                margin-bottom: 6px;
            }

            .q-num {
                font-weight: 800;
                color: #0f172a;
                display: inline-block;
                margin-right: 4px;
            }

            .q-marks-pill {
                float: right;
                font-size: 9px;
                font-weight: 700;
                background: #f1f5f9;
                color: #475569;
                padding: 2px 6px;
                border-radius: 4px;
                border: 1px solid #e2e8f0;
            }

            .q-text {
                font-weight: <?= $qFontWeight ?>;
                font-style: <?= $qFontStyle ?>;
                text-decoration: <?= $qTextDecoration ?>;
                line-height: <?= $mode === 'minimal' ? '1.35' : '1.5' ?>;
                color: #1e293b;
                white-space: pre-wrap;
                word-wrap: break-word;
            }

            .q-image-wrap {
                margin: 8px 0;
                text-align: center;
            }
            .q-image {
                max-width: <?= $mode === 'minimal' ? '65%' : '85%' ?>;
                max-height: <?= $mode === 'minimal' ? '160px' : '220px' ?>;
                border: 1px solid #cbd5e1;
                border-radius: 4px;
            }

            /* Options Layout */
            <?php if ($mode === 'normal'): ?>
            .options-table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 6px;
                margin-top: 6px;
            }
            .option-cell {
                width: 50%;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 5px;
                padding: 6px 10px;
                font-size: <?= max(9.5, $fontSize - 0.5) ?>px;
                vertical-align: middle;
            }
            .option-marker {
                display: inline-block;
                width: 18px;
                height: 18px;
                line-height: 18px;
                text-align: center;
                background: #e2e8f0;
                color: #0f172a;
                font-weight: 800;
                border-radius: 50%;
                margin-right: 6px;
                font-size: 10px;
            }
            <?php else: ?>
            .options-compact {
                margin-top: 4px;
                font-size: <?= max(9, $fontSize - 0.5) ?>px;
            }
            .opt-col {
                display: inline-block;
                width: 48%;
                margin-bottom: 3px;
                vertical-align: top;
            }
            .opt-badge {
                font-weight: 800;
                color: #0f172a;
                margin-right: 3px;
            }
            <?php endif; ?>

            .option-split-item {
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                padding: 4px 7px;
                margin-top: 4px;
                font-size: <?= max(9, $fontSize - 1) ?>px;
            }

            /* Answer Key Page */
            .answer-key-section {
                page-break-before: always;
                margin-top: 20px;
            }
            .answer-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
                font-size: 10px;
            }
            .answer-table th {
                background: #0f172a;
                color: #fff;
                padding: 6px;
                text-align: center;
                font-weight: 700;
            }
            .answer-table td {
                border: 1px solid #cbd5e1;
                padding: 6px;
                text-align: center;
            }
            .answer-table tr:nth-child(even) td {
                background: #f8fafc;
            }

            /* Footer */
            .paper-footer {
                position: fixed;
                bottom: <?= $mode === 'minimal' ? '-26px' : '-32px' ?>;
                left: 0;
                right: 0;
                height: 22px;
                font-size: 8px;
                color: #64748b;
                border-top: 1px solid #cbd5e1;
                padding-top: 4px;
            }
        </style>
    </head>
    <body>

        <!-- Diagonal Watermark (Rendered in both Normal & Minimal PDF) -->
        <div class="watermark" style="text-decoration: none !important; text-decoration-line: none !important; border: none !important; border-bottom: none !important;">
            <?= htmlspecialchars($watermarkText) ?>
        </div>

        <?php if ($mode === 'normal'): ?>
            <!-- Normal Mode Institutional Header -->
            <div class="paper-header">
                <div class="brand-tag"><?= htmlspecialchars($appName) ?> — Official Examination Paper</div>
                <div class="exam-title"><?= htmlspecialchars($test['title']) ?></div>
                <table class="meta-table">
                    <tr>
                        <td style="width: 32%; text-align: left;">
                            <strong>Duration:</strong> <?= (int)$test['duration_minutes'] ?> Minutes
                        </td>
                        <td style="width: 34%; text-align: center;">
                            <strong>No. of Correct:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]
                        </td>
                        <td style="width: 34%; text-align: right;">
                            <strong>No. of Wrong:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]
                        </td>
                    </tr>
                    <tr>
                        <td style="text-align: left;">
                            <strong>Faculty:</strong> <?= htmlspecialchars($teacherName) ?> (@<?= htmlspecialchars($teacherUsername) ?>)
                        </td>
                        <td style="text-align: center;">
                            <strong>Marks Obtained:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ]
                        </td>
                        <td style="text-align: right;">
                            <strong>Date:</strong> <?= $generatedAt ?>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Normal Candidate Fill-in Area -->
            <div class="candidate-box">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="width: 38%;"><strong>Candidate Name:</strong> ________________________________</td>
                        <td style="width: 32%;"><strong>UID / Roll No:</strong> ______________________</td>
                        <td style="width: 30%; text-align: right;"><strong>Invigilator Sign:</strong> ________________</td>
                    </tr>
                </table>
            </div>
        <?php else: ?>
            <!-- Minimal Mode: One-Line Candidate Header (Saves Max Page Space) -->
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px; font-size: <?= max(9, $fontSize - 1) ?>px; color: #1e293b;">
                <tr>
                    <td style="width: 46%; text-align: left;">
                        <strong>Name:</strong> ________________________________
                    </td>
                    <td style="width: 18%; text-align: center;">
                        <strong>Correct:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]
                    </td>
                    <td style="width: 18%; text-align: center;">
                        <strong>Wrong:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]
                    </td>
                    <td style="width: 18%; text-align: right;">
                        <strong>Score:</strong> [ &nbsp;&nbsp;&nbsp;&nbsp; ]
                    </td>
                </tr>
            </table>
            <hr style="border: 0; border-top: 1.5px solid #0f172a; margin: 4px 0 8px 0;">
        <?php endif; ?>

        <!-- Questions List -->
        <div class="questions-container">
            <?php foreach ($questions as $idx => $q): 
                $qNumber = $idx + 1;
            ?>
                <div class="question-item">
                    <?php if (!empty($q['image_data_uri'])): ?>
                        <!-- Image-based Question: 50% Left (Question & Options), 50% Right (Larger Diagram) -->
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="width: 50%; vertical-align: top; padding-right: <?= $mode === 'minimal' ? '10px' : '14px' ?>;">
                                    <div style="margin-bottom: <?= $mode === 'minimal' ? '5px' : '8px' ?>;">
                                        <span class="q-num">Q<?= $qNumber ?>.</span>
                                        <span class="q-text"><?= htmlspecialchars($q['question_text']) ?></span>
                                    </div>

                                    <?php if ($mode === 'normal'): ?>
                                        <div style="margin-top: 6px;">
                                            <div class="option-split-item"><span class="option-marker">A</span> <?= htmlspecialchars($q['option_a']) ?></div>
                                            <div class="option-split-item"><span class="option-marker">B</span> <?= htmlspecialchars($q['option_b']) ?></div>
                                            <div class="option-split-item"><span class="option-marker">C</span> <?= htmlspecialchars($q['option_c']) ?></div>
                                            <div class="option-split-item"><span class="option-marker">D</span> <?= htmlspecialchars($q['option_d']) ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div style="margin-top: 4px; font-size: <?= max(9, $fontSize - 0.5) ?>px;">
                                            <div style="margin-bottom: 2px;"><span class="opt-badge">(A)</span> <?= htmlspecialchars($q['option_a']) ?></div>
                                            <div style="margin-bottom: 2px;"><span class="opt-badge">(B)</span> <?= htmlspecialchars($q['option_b']) ?></div>
                                            <div style="margin-bottom: 2px;"><span class="opt-badge">(C)</span> <?= htmlspecialchars($q['option_c']) ?></div>
                                            <div style="margin-bottom: 2px;"><span class="opt-badge">(D)</span> <?= htmlspecialchars($q['option_d']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="width: 50%; vertical-align: middle; text-align: center;">
                                    <img src="<?= $q['image_data_uri'] ?>" style="max-width: 98%; max-height: <?= $mode === 'minimal' ? '180px' : '230px' ?>; border: 1px solid #cbd5e1; border-radius: 5px; display: inline-block;" alt="Question Diagram">
                                </td>
                            </tr>
                        </table>
                    <?php else: ?>
                        <!-- Text-only Question: Standard Full-Width Question followed by 2-Column Options -->
                        <div class="q-header" style="margin-bottom: <?= $mode === 'minimal' ? '4px' : '8px' ?>;">
                            <span class="q-num">Q<?= $qNumber ?>.</span>
                            <span class="q-text"><?= htmlspecialchars($q['question_text']) ?></span>
                        </div>

                        <?php if ($mode === 'normal'): ?>
                            <!-- Normal Mode: Spacious 2-Column Options Table -->
                            <table class="options-table">
                                <tr>
                                    <td class="option-cell">
                                        <span class="option-marker">A</span>
                                        <?= htmlspecialchars($q['option_a']) ?>
                                    </td>
                                    <td class="option-cell">
                                        <span class="option-marker">B</span>
                                        <?= htmlspecialchars($q['option_b']) ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="option-cell">
                                        <span class="option-marker">C</span>
                                        <?= htmlspecialchars($q['option_c']) ?>
                                    </td>
                                    <td class="option-cell">
                                        <span class="option-marker">D</span>
                                        <?= htmlspecialchars($q['option_d']) ?>
                                    </td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <!-- Minimal Mode: Compact 2-Column Grid (Paper Saver) -->
                            <div class="options-compact">
                                <div class="opt-col"><span class="opt-badge">(A)</span> <?= htmlspecialchars($q['option_a']) ?></div>
                                <div class="opt-col"><span class="opt-badge">(B)</span> <?= htmlspecialchars($q['option_b']) ?></div>
                                <div class="opt-col"><span class="opt-badge">(C)</span> <?= htmlspecialchars($q['option_c']) ?></div>
                                <div class="opt-col"><span class="opt-badge">(D)</span> <?= htmlspecialchars($q['option_d']) ?></div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($includeAnswers): ?>
            <!-- Optional Official Answer Key Section -->
            <div class="answer-key-section">
                <div style="text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px;">
                    <h3 style="margin: 0; font-size: 15px; color: #0f172a;">CONFIDENTIAL — OFFICIAL ANSWER KEY</h3>
                    <div style="font-size: 10px; color: #64748b;"><?= htmlspecialchars($test['title']) ?> &bull; For Faculty & Evaluators Only</div>
                </div>

                <table class="answer-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Q No.</th>
                            <th style="width: 25%;">Correct Option</th>
                            <th style="width: 20%;">Positive Marks</th>
                            <th style="width: 20%;">Negative Marks</th>
                            <th style="width: 20%;">Answer Statement</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($questions as $idx => $q): 
                            $correctKey = strtoupper($q['correct_option'] ?? 'A');
                            $correctTextKey = 'option_' . strtolower($correctKey);
                            $correctText = $q[$correctTextKey] ?? '—';
                        ?>
                            <tr>
                                <td><strong>Q<?= $idx + 1 ?></strong></td>
                                <td style="font-weight: 800; color: #059669; font-size: 12px;"><?= htmlspecialchars($correctKey) ?></td>
                                <td>+<?= (float)$q['marks'] ?></td>
                                <td>-<?= (float)$q['negative_marks'] ?></td>
                                <td style="text-align: left; font-size: 9.5px;"><?= htmlspecialchars(mb_strimwidth($correctText, 0, 45, '...')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php
        // Truncate long username and teacher name in footer with "..."
        $displayUsername = $teacherUsername;
        if (mb_strlen($displayUsername) > 12) {
            $displayUsername = mb_substr($displayUsername, 0, 10) . '...';
        }
        $displayTeacherName = $teacherName;
        if (mb_strlen($displayTeacherName) > 18) {
            $displayTeacherName = mb_substr($displayTeacherName, 0, 15) . '...';
        }
        $facultyFooter = 'Faculty: ' . $displayTeacherName . ' (@' . $displayUsername . ')';
        ?>

        <div class="paper-footer">
            <table style="width: 100%; border-collapse: collapse; font-size: 8px; color: #64748b;">
                <tr>
                    <td style="text-align: left; vertical-align: middle;">
                        <?php if (!empty($logoDataUri)): ?>
                            <img src="<?= $logoDataUri ?>" style="height: 12px; vertical-align: -2px; margin-right: 4px;" alt="Logo">
                        <?php endif; ?>
                        <strong style="color: #0f172a;"><?= htmlspecialchars($appName) ?></strong> &bull; <?= htmlspecialchars($facultyFooter) ?>
                    </td>
                    <td style="text-align: right; vertical-align: middle; width: 120px;">
                        <!-- Page number dynamically rendered by Dompdf canvas script -->
                    </td>
                </tr>
            </table>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

try {
    $html = generate_question_paper_html(
        $test,
        $questions,
        $mode,
        $fontSize,
        $isBold,
        $isItalic,
        $isUnderline,
        $watermarkText,
        $includeAnswers,
        $teacherName,
        $teacherUsername,
        $totalCalculatedMarks,
        $logoDataUri
    );

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Dynamically render page numbering on all pages via canvas
    $canvas = $dompdf->getCanvas();
    $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($mode) {
        $text = "Page $pageNumber of $pageCount";
        $font = $fontMetrics->get_font('Helvetica', 'normal');
        $size = 8;
        $width = $fontMetrics->getTextWidth($text, $font, $size);
        $marginRight = ($mode === 'minimal') ? 22 : 30;
        $x = $canvas->get_width() - $marginRight - $width;
        $y = ($mode === 'minimal') ? 822 : 820;
        $canvas->text($x, $y, $text, $font, $size, [0.39, 0.45, 0.55]);
    });

    // Stream download
    $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $test['title']);
    $filename = "Question_Paper_{$cleanTitle}_{$mode}.pdf";

    if (PHP_SAPI === 'cli') {
        echo "CLI SUCCESS! Generated PDF bytes: " . strlen($dompdf->output()) . PHP_EOL;
        exit(0);
    }

    $dompdf->stream($filename, ['Attachment' => !$isInline]);
    exit;

} catch (Throwable $e) {
    $ref = app_log('error', 'Question Paper PDF generation error: ' . $e->getMessage());
    abort_html(500, 'PDF Generation Failed', 'Unable to generate Question Paper PDF. Ref: ' . $ref);
}
