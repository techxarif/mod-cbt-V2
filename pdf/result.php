<?php
// pdf/result.php — Export Examination Result PDF
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$test_id = int_param('test_id', 0);
if ($test_id <= 0) {
    abort_html(400, 'Invalid Request', 'Please specify a test ID.');
}

$test = get_test($test_id);
if (!$test) {
    abort_html(404, 'Test Not Found');
}

$results = get_test_results($test_id);
$stats   = result_stats($results);

function result_pdf_html(array $test, array $results, array $stats): string
{
    $appName = defined('APP_NAME') ? APP_NAME : 'MODUS CBT';
    $generatedAt = date('d M Y, H:i');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 25px 30px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
            .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 18px; }
            .title { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
            .subtitle { font-size: 11px; color: #64748b; }
            .info-grid { width: 100%; margin-bottom: 18px; }
            .info-grid td { padding: 4px; font-size: 11px; }
            .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
            .stats-table td { background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px; text-align: center; font-size: 11px; }
            .stats-table th { background: #0f172a; color: #fff; padding: 6px; font-size: 10px; text-transform: uppercase; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 10px; }
            .data-table th { background: #1e293b; color: #fff; padding: 6px 8px; text-align: left; }
            .data-table td { border-bottom: 1px solid #e2e8f0; padding: 6px 8px; }
            .data-table tr:nth-child(even) td { background: #f8fafc; }
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 5px; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="title"><?= htmlspecialchars($appName) ?></div>
            <div class="subtitle">Official Examination Result & Merit List</div>
        </div>

        <table class="info-grid">
            <tr>
                <td><strong>Exam Title:</strong> <?= htmlspecialchars($test['title']) ?></td>
                <td style="text-align: right;"><strong>Date Generated:</strong> <?= $generatedAt ?></td>
            </tr>
            <tr>
                <td><strong>Duration:</strong> <?= (int)$test['duration_minutes'] ?> Minutes</td>
                <td style="text-align: right;"><strong>Scoring:</strong> +<?= (float)$test['total_marks'] ?> / -<?= (float)$test['negative_marks'] ?></td>
            </tr>
        </table>

        <table class="stats-table">
            <tr>
                <th>Total Candidates</th>
                <th>Submitted</th>
                <th>Highest Score</th>
                <th>Average Score</th>
                <th>Lowest Score</th>
            </tr>
            <tr>
                <td><strong><?= $stats['total'] ?></strong></td>
                <td><strong><?= $stats['submitted'] ?></strong></td>
                <td style="color: #059669;"><strong><?= format_score($stats['highest']) ?></strong></td>
                <td><strong><?= format_score($stats['average']) ?></strong></td>
                <td style="color: #dc2626;"><strong><?= format_score($stats['lowest']) ?></strong></td>
            </tr>
        </table>

        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 35px;">Rank</th>
                    <th>UID</th>
                    <th>Candidate Name</th>
                    <th>Mobile</th>
                    <th style="text-align: center;">Correct</th>
                    <th style="text-align: center;">Wrong</th>
                    <th style="text-align: center;">Skipped</th>
                    <th style="text-align: right;">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $rank = 0;
                foreach ($results as $r): 
                    if ($r['status'] === 'submitted') $rank++;
                ?>
                    <tr>
                        <td><?= $r['status'] === 'submitted' ? "#$rank" : '—' ?></td>
                        <td><?= htmlspecialchars($r['uid']) ?></td>
                        <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                        <td><?= htmlspecialchars($r['mobile'] ?? '—') ?></td>
                        <td style="text-align: center; color: #059669;"><?= (int)$r['correct_answers'] ?></td>
                        <td style="text-align: center; color: #dc2626;"><?= (int)$r['wrong_answers'] ?></td>
                        <td style="text-align: center;"><?= (int)$r['unanswered'] ?></td>
                        <td style="text-align: right;"><strong><?= $r['score'] !== null ? format_score($r['score']) : '—' ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            Generated securely by <?= htmlspecialchars($appName) ?> examination system. Confidential report.
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

try {
    $html = result_pdf_html($test, $results, $stats);

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $cleanTitle = preg_replace('/[^a-zA-Z0-9_-]/', '_', $test['title']);
    $dompdf->stream("result_{$cleanTitle}.pdf", ['Attachment' => false]);
} catch (Throwable $e) {
    $ref = app_log('error', 'PDF generation error: ' . $e->getMessage());
    abort_html(500, 'PDF Generation Failed', 'Ref: ' . $ref);
}
