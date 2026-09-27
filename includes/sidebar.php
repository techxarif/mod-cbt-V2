<?php
// includes/sidebar.php — Modular sidebar component
$teacher = currentTeacher();
$app_name = defined('APP_NAME') ? APP_NAME : 'MODUS CBT';
$base     = app_base_url();
$active   = $active_nav ?? '';

$nav_items = [
    ['href' => 'dashboard/index.php',   'label' => 'Dashboard',     'icon' => '⌂', 'key' => 'dashboard'],
    ['href' => 'students/index.php',    'label' => 'Students',      'icon' => '♙', 'key' => 'students'],
    ['href' => 'tests/index.php',       'label' => 'Tests',         'icon' => '▣', 'key' => 'tests'],
    ['href' => 'questions/index.php',   'label' => 'Questions',     'icon' => '☷', 'key' => 'questions'],
    ['href' => 'assignments/index.php', 'label' => 'Assignments',   'icon' => '↔', 'key' => 'assignments'],
    ['href' => 'exam_control/index.php','label' => 'Exam Control',  'icon' => '▶', 'key' => 'exam_control'],
    ['href' => 'results/index.php',     'label' => 'Results',       'icon' => '▤', 'key' => 'results'],
    ['href' => 'qr/index.php',          'label' => 'Faculty QR',    'icon' => '⌗', 'key' => 'qr'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-logo">
            <img src="<?= $base ?>assets/img/logo.png" alt="Modus Logo" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
            <span class="fallback-logo" style="display:none; width:100%; height:100%; place-items:center; background:var(--green); color:#04120b; font-weight:900; border-radius:9px;">M</span>
        </div>
        <div class="sidebar-brand-name">
            <?= e($app_name) ?>
            <small>Examination System</small>
        </div>
        <button class="sidebar-close-btn" onclick="toggleSidebar()" aria-label="Close menu">
            ✕
        </button>
    </div>
    <div class="sidebar-label">Workspace</div>
    <nav class="sidebar-nav">
        <?php foreach ($nav_items as $item): ?>
            <a href="<?= $base . $item['href'] ?>" class="nav-item <?= $active === $item['key'] ? 'active' : '' ?>">
                <span class="ico"><?= $item['icon'] ?></span>
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <?php if (!empty($teacher['avatar']) && file_exists(dirname(__DIR__) . '/' . $teacher['avatar'])): ?>
                <div class="sidebar-avatar" style="background: none; overflow: hidden; padding: 0;">
                    <img src="<?= $base . e($teacher['avatar']) ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
            <?php else: ?>
                <div class="sidebar-avatar"><?= avatar_initial($teacher['name']) ?></div>
            <?php endif; ?>
            <div class="sidebar-username">
                <?= e($teacher['name']) ?>
                <small>Teacher / Administrator</small>
            </div>
        </div>
        <a href="<?= $base ?>settings/index.php" class="nav-item settings-link <?= $active === 'settings' ? 'active' : '' ?>">
            <span class="ico">⚙</span>
            Settings
        </a>
    </div>
</aside>
