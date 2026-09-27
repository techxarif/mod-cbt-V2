<?php
// results/student.php — Detailed Candidate Exam Breakdown
require_once __DIR__ . '/../includes/bootstrap.php';
requireTeacher();

$student_test_id = int_param('student_test_id', 0);
if ($student_test_id <= 0) {
    redirect('index.php');
}

try {
    $stmt = $pdo->prepare("
        SELECT st.*, s.name AS student_name, s.uid, s.mobile,
               t.id AS test_id, t.title AS test_title, t.total_marks AS mark_per_q, t.negative_marks AS neg_per_q,
               t.duration_minutes
        FROM student_tests st
        INNER JOIN students s ON s.id = st.student_id
        INNER JOIN tests t ON t.id = st.test_id
        WHERE st.id = ?
        LIMIT 1
    ");
    $stmt->execute([$student_test_id]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        abort_html(404, 'Attempt Not Found');
    }

    // Load questions and student's answers
    $stmt = $pdo->prepare("
        SELECT q.id, q.question_text, q.question_image, q.option_a, q.option_b, q.option_c, q.option_d,
               q.correct_option, tq.question_order,
               a.selected_option, a.is_correct, a.marks_awarded, a.answered_at
        FROM test_questions tq
        INNER JOIN questions q ON q.id = tq.question_id
        LEFT JOIN answers a ON a.question_id = q.id AND a.student_test_id = ?
        WHERE tq.test_id = ?
        ORDER BY tq.question_order ASC
    ");
    $stmt->execute([$student_test_id, $attempt['test_id']]);
    $breakdown = $stmt->fetchAll();
} catch (Throwable $e) {
    $ref = app_log('error', 'Student result error: ' . $e->getMessage());
    abort_html(500, 'Error Loading Result', 'Ref: ' . $ref);
}

$page_title = 'Candidate Report — ' . e($attempt['student_name']);
require_once __DIR__ . '/../includes/layout.php';
layout_header($page_title, 'results');
?>

<div class="page-content">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?= e($attempt['student_name']) ?> (<?= e($attempt['uid']) ?>)</h1>
            <p class="page-subtitle">Examination: <strong><?= e($attempt['test_title']) ?></strong></p>
        </div>
        <a href="index.php?test_id=<?= $attempt['test_id'] ?>" class="btn btn-secondary">← Back to Leaderboard</a>
    </div>

    <!-- Candidate Score Overview -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" style="color: <?= (float)$attempt['score'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                <?= format_score($attempt['score']) ?>
            </div>
            <div class="stat-label">Total Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--success);"><?= (int)$attempt['correct_answers'] ?></div>
            <div class="stat-label">Correct Answers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--danger);"><?= (int)$attempt['wrong_answers'] ?></div>
            <div class="stat-label">Wrong Answers</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color: var(--text-muted);"><?= (int)$attempt['unanswered'] ?></div>
            <div class="stat-label">Skipped Questions</div>
        </div>
    </div>

    <!-- Question by Question Breakdown -->
    <h2 style="font-size: 18px; font-weight: 600; margin-bottom: 16px;">Question Responses</h2>

    <div style="display: flex; flex-direction: column; gap: 16px;">
        <?php foreach ($breakdown as $index => $item): 
            $selected = $item['selected_option'];
            $correct  = $item['correct_option'];
            $isCorrect = $item['is_correct'] === 1;
            $isWrong   = $item['is_correct'] === 0 && $selected !== null && $selected !== '';
            $isSkipped = $selected === null || $selected === '';
        ?>
            <div class="card" style="margin: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <span class="badge" style="background: var(--bg-surface);">Q<?= $index + 1 ?></span>
                    <div>
                        <?php if ($isCorrect): ?>
                            <span class="badge badge-success">+<?= format_score($item['marks_awarded']) ?> Correct</span>
                        <?php elseif ($isWrong): ?>
                            <span class="badge badge-danger"><?= format_score($item['marks_awarded']) ?> Wrong</span>
                        <?php else: ?>
                            <span class="badge badge-draft">Skipped (0.00)</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
                    <?= nl2br(e($item['question_text'])) ?>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px; font-size: 13px;">
                    <?php foreach (['A', 'B', 'C', 'D'] as $opt): 
                        $optText = $item['option_' . strtolower($opt)];
                        $isSelected = $selected === $opt;
                        $isKey      = $correct === $opt;
                        $bg = 'var(--bg-secondary)';
                        $border = 'var(--border-subtle)';
                        if ($isKey) {
                            $bg = 'var(--success-light)';
                            $border = 'var(--success)';
                        } elseif ($isSelected && !$isKey) {
                            $bg = 'var(--danger-light)';
                            $border = 'var(--danger)';
                        }
                    ?>
                        <div style="padding: 8px 12px; border-radius: var(--radius-sm); background: <?= $bg ?>; border: 1px solid <?= $border ?>;">
                            <strong><?= $opt ?>:</strong> <?= e($optText) ?>
                            <?php if ($isSelected): ?>
                                <span style="font-weight: 700; color: <?= $isKey ? 'var(--success)' : 'var(--danger)' ?>;">(Candidate)</span>
                            <?php endif; ?>
                            <?php if ($isKey): ?>
                                <span style="font-weight: 700; color: var(--success);">(Key)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php layout_footer(); ?>
