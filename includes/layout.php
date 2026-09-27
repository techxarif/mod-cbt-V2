<?php

// ============================================================
// MODUS CBT — Shared Layout Functions
// layout_header(), layout_footer(), alert_box()
// Used by all Teacher Dashboard / Admin pages.
// Includes complete CSS assets and responsive Mobile Hamburger Drawer.
// ============================================================

function layout_header(string $title, string $active = ''): void
{
    $teacher  = currentTeacher();
    $app_name = defined('APP_NAME') ? APP_NAME : 'MODUS CBT';
    $base     = app_base_url();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?></title>
        <link rel="icon" type="image/png" href="<?= $base ?>assets/img/logo.png">
        <link rel="shortcut icon" type="image/png" href="<?= $base ?>assets/img/logo.png">
        <link rel="apple-touch-icon" href="<?= $base ?>assets/img/logo.png">

        <!-- Fonts & Design Tokens -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&family=Outfit:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Complete CSS Suite -->
        <link rel="stylesheet" href="<?= $base ?>assets/css/style.css">
        <link rel="stylesheet" href="<?= $base ?>assets/css/admin.css">
        <link rel="stylesheet" href="<?= $base ?>assets/css/dashboard.css">
        <link rel="stylesheet" href="<?= $base ?>assets/css/settings.css">
        <link rel="stylesheet" href="<?= $base ?>assets/css/responsive.css">

        <!-- Anti-FOUC Theme Initializer -->
        <script>
        (function() {
            var t = localStorage.getItem('modus_theme') || 'emerald';
            var f = localStorage.getItem('modus_font') || 'inter';
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-font', f);
        })();
        </script>
    </head>

    <body>

        <!-- Mobile Drawer Overlay Backdrop -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <!-- Mobile Top Bar with Hamburger for all Teacher Pages -->
        <header class="mobile-topbar">
            <a href="<?= $base ?>dashboard/index.php" class="mobile-brand">
                <div class="mobile-brand-logo">
                    <img src="<?= $base ?>assets/img/logo.png" alt="Modus Logo" class="brand-logo-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                    <span class="fallback-logo" style="display:none; width:100%; height:100%; place-items:center; background:var(--green); color:#04120b; font-weight:900; border-radius:9px;">M</span>
                </div>
                <div class="mobile-brand-name"><?= e($app_name) ?></div>
            </a>
            <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
                ☰
            </button>
        </header>

        <div class="admin-layout">

            <!-- =============================================
                 SIDEBAR DRAWER (Desktop fixed + Mobile slide-in)
                 ============================================= -->

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

                    <?php
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

                    foreach ($nav_items as $item):
                        $is_active = $active === $item['key'];
                    ?>
                        <a
                            href="<?= $base . $item['href'] ?>"
                            class="nav-item <?= $is_active ? 'active' : '' ?>"
                        >
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


            <!-- =============================================
                 MAIN CONTENT AREA
                 ============================================= -->

            <main class="admin-main">

                <div class="admin-content">
    <?php
}


function layout_footer(): void
{
    $base = app_base_url();
    ?>
                </div>

            </main>

        </div>

        <script src="<?= $base ?>assets/js/app.js"></script>
        <script src="<?= $base ?>assets/js/ui.js"></script>
        <script>
        function toggleSidebar() {
            var sb = document.getElementById('sidebar');
            var ov = document.getElementById('sidebarOverlay');
            if (sb) sb.classList.toggle('open');
            if (ov) ov.classList.toggle('active');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                var sb = document.getElementById('sidebar');
                var ov = document.getElementById('sidebarOverlay');
                if (sb && sb.classList.contains('open')) {
                    sb.classList.remove('open');
                    if (ov) ov.classList.remove('active');
                }
            }
        });
        </script>

    </body>

    </html>
    <?php
}


function alert_box(string $type, string $message): string
{
    $classes = match ($type) {
        'success' => 'alert alert-success',
        'error'   => 'alert alert-error',
        'warning' => 'alert alert-warning',
        'info'    => 'alert alert-info',
        default   => 'alert',
    };

    return '<div class="' . $classes . '">' . e($message) . '</div>';
}
