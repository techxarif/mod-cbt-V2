<?php

// ============================================================
// MODUS CBT — Teacher Settings & Preferences
// Profile editing, Avatar upload, Themes, Fonts, & Session Logout
// ============================================================

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/layout.php';
requireTeacher();

$teacher = currentTeacher();
$teacher_id = $teacher['id'];
$base = app_base_url();

$success_msg = '';
$error_msg   = '';

// Fetch fresh teacher data
$stmt = $pdo->prepare("SELECT * FROM teachers WHERE id = ? LIMIT 1");
$stmt->execute([$teacher_id]);
$teacher_db = $stmt->fetch();

if (!$teacher_db) {
    header('Location: ' . $base . 'auth/logout.php');
    exit;
}

// Handle Form Submissions
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name     = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');

        if ($name === '' || $username === '') {
            $error_msg = 'Name and username cannot be empty.';
        } else {
            // Check username collision
            $chk = $pdo->prepare("SELECT id FROM teachers WHERE username = ? AND id != ? LIMIT 1");
            $chk->execute([$username, $teacher_id]);
            if ($chk->fetch()) {
                $error_msg = 'This username is already taken by another account.';
            } else {
                // Handle Avatar upload
                $avatar_path = $teacher_db['avatar'];

                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['avatar']['tmp_name'];
                    $orig_name = $_FILES['avatar']['name'];
                    $size = $_FILES['avatar']['size'];
                    $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

                    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (!in_array($ext, $allowed_exts, true)) {
                        $error_msg = 'Invalid image type. Allowed: JPG, PNG, WEBP, GIF.';
                    } elseif ($size > 5 * 1024 * 1024) {
                        $error_msg = 'Image file too large. Maximum size is 5MB.';
                    } else {
                        $target_dir = dirname(__DIR__) . '/uploads/avatars/';
                        if (!is_dir($target_dir)) {
                            @mkdir($target_dir, 0755, true);
                        }

                        $new_filename = 'avatar_' . $teacher_id . '_' . time() . '.' . $ext;
                        $target_file = $target_dir . $new_filename;

                        if (move_uploaded_file($tmp_name, $target_file)) {
                            // Delete old avatar if present
                            if (!empty($avatar_path)) {
                                $old_file = dirname(__DIR__) . '/' . ltrim($avatar_path, '/');
                                if (file_exists($old_file) && is_file($old_file)) {
                                    @unlink($old_file);
                                }
                            }
                            $avatar_path = 'uploads/avatars/' . $new_filename;
                            $_SESSION['teacher_avatar'] = $avatar_path;
                        } else {
                            $error_msg = 'Failed to upload profile image.';
                        }
                    }
                }

                if ($error_msg === '') {
                    $upd = $pdo->prepare("UPDATE teachers SET name = ?, username = ?, avatar = ? WHERE id = ?");
                    $upd->execute([$name, $username, $avatar_path, $teacher_id]);

                    $_SESSION['teacher_name'] = $name;
                    $_SESSION['username']     = $username;
                    $_SESSION['teacher_avatar'] = $avatar_path;
                    $teacher_db['name']       = $name;
                    $teacher_db['username']   = $username;
                    $teacher_db['avatar']     = $avatar_path;

                    $success_msg = 'Profile details updated successfully!';
                }
            }
        }
    } elseif ($action === 'remove_avatar') {
        if (!empty($teacher_db['avatar'])) {
            $old_file = dirname(__DIR__) . '/' . ltrim($teacher_db['avatar'], '/');
            if (file_exists($old_file) && is_file($old_file)) {
                @unlink($old_file);
            }
            $upd = $pdo->prepare("UPDATE teachers SET avatar = NULL WHERE id = ?");
            $upd->execute([$teacher_id]);
            $teacher_db['avatar'] = null;
            unset($_SESSION['teacher_avatar']);
            $success_msg = 'Profile image removed.';
        }
    }
}

layout_header('Settings — MODUS CBT', 'settings');
?>

<div class="settings-header">
    <div class="settings-header-title">
        <h1>Workspace Settings & Preferences</h1>
        <p>Manage your teacher profile identity, accent themes, typography, and active session.</p>
    </div>
    <div class="settings-actions">
        <a href="<?= $base ?>settings/about.php" class="btn btn-secondary">
            About Us & Ecosystem
        </a>
    </div>
</div>

<?php if ($success_msg): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        <strong>✓ Success:</strong> <?= e($success_msg) ?>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="alert alert-error" style="margin-bottom: 20px;">
        <strong>✕ Error:</strong> <?= e($error_msg) ?>
    </div>
<?php endif; ?>

<!-- Ecosystem Showcase Banner -->
<div class="ecosystem-banner">
    <div class="ecosystem-banner-content">
        <h3>Modus Engineering & Academic Ecosystem</h3>
        <p>Explore sibling products engineered by our team: Faculty by Modus (NEET/JEE OS) and Tracker by Modus (Flutter/Firebase study OS).</p>
    </div>
    <a href="<?= $base ?>settings/about.php" class="btn btn-primary btn-sm">
        Meet Developers & Products →
    </a>
</div>

<div class="settings-grid">

    <!-- Card 1: Teacher Profile Details -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">👤</div>
            <div class="settings-card-title">
                <h3>Profile & Identity</h3>
                <p>Manage your teacher display credentials and avatar photo</p>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" action="<?= $base ?>settings/index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="avatar-upload-wrap">
                <div class="avatar-preview-box" id="avatarPreviewBox">
                    <?php if (!empty($teacher_db['avatar']) && file_exists(dirname(__DIR__) . '/' . $teacher_db['avatar'])): ?>
                        <img src="<?= $base . e($teacher_db['avatar']) ?>" alt="Avatar" id="avatarImgPreview">
                    <?php else: ?>
                        <span id="avatarInitialText"><?= avatar_initial($teacher_db['name']) ?></span>
                        <img src="" alt="Avatar" id="avatarImgPreview" style="display: none;">
                    <?php endif; ?>
                </div>
                <div class="avatar-upload-info">
                    <h4>Profile Picture</h4>
                    <p>PNG, JPG, WEBP up to 5MB.</p>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <label class="btn btn-secondary btn-sm" style="cursor: pointer;">
                            <span>📁</span> Choose Photo
                            <input type="file" name="avatar" id="avatarInput" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                        </label>
                        <?php if (!empty($teacher_db['avatar'])): ?>
                            <button type="submit" name="action" value="remove_avatar" class="btn btn-danger btn-sm" onclick="return confirm('Remove current profile picture?');">
                                Remove
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" style="font-size: 13px; font-weight: 600; color: var(--text); display: block; margin-bottom: 6px;">Full Name</label>
                <input type="text" name="name" class="form-input" style="width: 100%; padding: 10px 14px; background: var(--panel2); border: 1px solid var(--line); border-radius: 8px; color: var(--text); font-size: 13.5px;" value="<?= e($teacher_db['name']) ?>" required>
            </div>

            <div class="form-group" style="margin-bottom: 22px;">
                <label class="form-label" style="font-size: 13px; font-weight: 600; color: var(--text); display: block; margin-bottom: 6px;">Username</label>
                <input type="text" name="username" class="form-input" style="width: 100%; padding: 10px 14px; background: var(--panel2); border: 1px solid var(--line); border-radius: 8px; color: var(--text); font-size: 13.5px;" value="<?= e($teacher_db['username']) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                Save Profile Changes
            </button>
        </form>
    </div>

    <!-- Card 2: Visual Themes & Appearance -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon">🎨</div>
            <div class="settings-card-title">
                <h3>Theme & Appearance</h3>
                <p>Customize accent color palettes and application typography</p>
            </div>
        </div>

        <div>
            <label style="font-size: 13px; font-weight: 700; color: var(--text); display: block; margin-bottom: 6px;">Accent Color Palette</label>
            <p style="font-size: 12px; color: var(--muted); margin-bottom: 10px;">Select your preferred workspace accent color. Changes apply instantly across all pages.</p>

            <div class="theme-picker-grid" id="themePicker">
                <!-- Modus Emerald (Default) -->
                <div class="theme-card" data-theme-val="emerald" onclick="switchTheme('emerald')">
                    <div class="theme-color-dot" style="background: #20e596; box-shadow: 0 0 10px #20e596;"></div>
                    <div class="theme-card-name">Emerald</div>
                </div>

                <!-- Focus Amber -->
                <div class="theme-card" data-theme-val="amber" onclick="switchTheme('amber')">
                    <div class="theme-color-dot" style="background: #f59e0b; box-shadow: 0 0 10px #f59e0b;"></div>
                    <div class="theme-card-name">Amber</div>
                </div>

                <!-- Cyber Cyan -->
                <div class="theme-card" data-theme-val="cyan" onclick="switchTheme('cyan')">
                    <div class="theme-color-dot" style="background: #06b6d4; box-shadow: 0 0 10px #06b6d4;"></div>
                    <div class="theme-card-name">Cyan</div>
                </div>

                <!-- Royal Violet -->
                <div class="theme-card" data-theme-val="violet" onclick="switchTheme('violet')">
                    <div class="theme-color-dot" style="background: #a855f7; box-shadow: 0 0 10px #a855f7;"></div>
                    <div class="theme-card-name">Violet</div>
                </div>

                <!-- Crimson Red -->
                <div class="theme-card" data-theme-val="crimson" onclick="switchTheme('crimson')">
                    <div class="theme-color-dot" style="background: #ef4444; box-shadow: 0 0 10px #ef4444;"></div>
                    <div class="theme-card-name">Crimson</div>
                </div>

                <!-- Ocean Blue -->
                <div class="theme-card" data-theme-val="ocean" onclick="switchTheme('ocean')">
                    <div class="theme-color-dot" style="background: #3b82f6; box-shadow: 0 0 10px #3b82f6;"></div>
                    <div class="theme-card-name">Ocean</div>
                </div>

                <!-- Rose Quartz -->
                <div class="theme-card" data-theme-val="rose" onclick="switchTheme('rose')">
                    <div class="theme-color-dot" style="background: #ec4899; box-shadow: 0 0 10px #ec4899;"></div>
                    <div class="theme-card-name">Rose</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 24px; padding-top: 18px; border-top: 1px solid var(--line);">
            <label style="font-size: 13px; font-weight: 700; color: var(--text); display: block; margin-bottom: 6px;">Application Font</label>
            <p style="font-size: 12px; color: var(--muted); margin-bottom: 10px;">Select your preferred typeface for headings, labels, and telemetry.</p>

            <div class="font-picker-grid" id="fontPicker">
                <!-- Inter -->
                <div class="font-card" data-font-val="inter" onclick="switchFont('inter')">
                    <div class="font-card-preview" style="font-family: 'Inter', sans-serif;">Ag 12</div>
                    <div class="font-card-name">Inter UI</div>
                </div>

                <!-- Outfit -->
                <div class="font-card" data-font-val="outfit" onclick="switchFont('outfit')">
                    <div class="font-card-preview" style="font-family: 'Outfit', sans-serif;">Ag 12</div>
                    <div class="font-card-name">Outfit</div>
                </div>

                <!-- Plus Jakarta Sans -->
                <div class="font-card" data-font-val="plus-jakarta" onclick="switchFont('plus-jakarta')">
                    <div class="font-card-preview" style="font-family: 'Plus Jakarta Sans', sans-serif;">Ag 12</div>
                    <div class="font-card-name">Plus Jakarta</div>
                </div>

                <!-- JetBrains Mono -->
                <div class="font-card" data-font-val="jetbrains" onclick="switchFont('jetbrains')">
                    <div class="font-card-preview" style="font-family: 'JetBrains Mono', monospace;">Ag 12</div>
                    <div class="font-card-name">JetBrains Mono</div>
                </div>

                <!-- System UI -->
                <div class="font-card" data-font-val="system" onclick="switchFont('system')">
                    <div class="font-card-preview" style="font-family: system-ui;">Ag 12</div>
                    <div class="font-card-name">System UI</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Account Session & Logout -->
    <div class="settings-card">
        <div class="settings-card-header">
            <div class="settings-card-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">⎋</div>
            <div class="settings-card-title">
                <h3>Account Session</h3>
                <p>Manage your active authenticated teacher session</p>
            </div>
        </div>

        <div style="background: var(--panel2); border: 1px solid var(--line); border-radius: 12px; padding: 18px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--green); box-shadow: 0 0 8px var(--green);"></div>
                <div style="font-size: 13.5px; font-weight: 700; color: var(--text);">Active Session</div>
            </div>
            <div style="font-size: 13px; color: var(--muted); line-height: 1.6;">
                Logged in as <strong style="color: var(--text);"><?= e($teacher_db['name']) ?></strong> (<span style="color: var(--green);">@<?= e($teacher_db['username']) ?></span>)<br>
                Role: <strong style="color: var(--text);">Administrator / Faculty</strong>
            </div>
        </div>

        <p style="font-size: 12.5px; color: var(--muted); line-height: 1.5; margin-bottom: 20px;">
            Signing out will securely terminate your current administrative session on this device. You will need to log in again to access the dashboard and test management.
        </p>

        <div style="margin-top: auto;">
            <button type="button" class="btn btn-danger" style="width: 100%; padding: 12px 18px; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="confirmLogout()">
                <span>⎋</span> Log Out of Modus CBT
            </button>
        </div>
    </div>

</div>

<script>
// Avatar preview handler
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var img = document.getElementById('avatarImgPreview');
            var initial = document.getElementById('avatarInitialText');
            if (img) {
                img.src = e.target.result;
                img.style.display = 'block';
            }
            if (initial) {
                initial.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Theme Switcher Logic
function switchTheme(theme) {
    localStorage.setItem('modus_theme', theme);
    document.documentElement.setAttribute('data-theme', theme);
    highlightSelectedTheme(theme);
    if (window.ModusUI && ModusUI.toast) {
        ModusUI.toast('Theme set to ' + theme.toUpperCase(), 'success', 2000);
    }
}

function highlightSelectedTheme(theme) {
    var cards = document.querySelectorAll('.theme-card');
    cards.forEach(function(card) {
        if (card.getAttribute('data-theme-val') === theme) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}

// Font Switcher Logic
function switchFont(font) {
    localStorage.setItem('modus_font', font);
    document.documentElement.setAttribute('data-font', font);
    highlightSelectedFont(font);
    if (window.ModusUI && ModusUI.toast) {
        ModusUI.toast('Font set to ' + font, 'info', 2000);
    }
}

function highlightSelectedFont(font) {
    var cards = document.querySelectorAll('.font-card');
    cards.forEach(function(card) {
        if (card.getAttribute('data-font-val') === font) {
            card.classList.add('active');
        } else {
            card.classList.remove('active');
        }
    });
}

// Logout Confirmation
function confirmLogout() {
    if (window.ModusUI && ModusUI.confirm) {
        ModusUI.confirm('Are you sure you want to end your current session and log out?', 'Log Out', {
            type: 'warning',
            confirmText: 'Log Out',
            cancelText: 'Stay'
        }).then(function(confirmed) {
            if (confirmed) {
                window.location.href = '<?= $base ?>auth/logout.php';
            }
        });
    } else {
        if (confirm('Are you sure you want to log out?')) {
            window.location.href = '<?= $base ?>auth/logout.php';
        }
    }
}

// Init active states on page load
document.addEventListener('DOMContentLoaded', function() {
    var curTheme = localStorage.getItem('modus_theme') || 'emerald';
    var curFont = localStorage.getItem('modus_font') || 'inter';
    highlightSelectedTheme(curTheme);
    highlightSelectedFont(curFont);
});
</script>

<?php
layout_footer();
