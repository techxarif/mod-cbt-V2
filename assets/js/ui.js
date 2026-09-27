/**
 * MODUS CBT — UI Helpers, Theme System, Animated Modals & Mobile Drawer
 */

// Immediate Theme & Font Application from Local Storage
(function() {
    try {
        var savedTheme = localStorage.getItem('modus_theme') || 'emerald';
        var savedFont = localStorage.getItem('modus_font') || 'inter';
        document.documentElement.setAttribute('data-theme', savedTheme);
        document.documentElement.setAttribute('data-font', savedFont);
    } catch(e) {}
})();

window.ModusUI = window.ModusUI || {};

// Disable Right-Click Universally Across Application
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
}, false);

// ----------------------------------------------------
// Mobile Sidebar Drawer
// ----------------------------------------------------
window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
    if (overlay) {
        overlay.classList.toggle('active');
    }
};
ModusUI.toggleSidebar = window.toggleSidebar;

// Close on Escape or Outside Click
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            if (sidebar && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                if (overlay) overlay.classList.remove('active');
            }
        }
    });
});

// ----------------------------------------------------
// Animated Pop-Up Modal (Replaces browser alerts)
// ----------------------------------------------------
ModusUI.modal = function(opts) {
    var existing = document.getElementById('modusModalBackdrop');
    if (existing) existing.remove();

    var icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    var type = opts.type || 'info';
    var iconChar = icons[type] || 'ℹ';

    var backdrop = document.createElement('div');
    backdrop.id = 'modusModalBackdrop';
    backdrop.className = 'modus-modal-backdrop';

    var hasCancel = !!opts.cancelText;

    backdrop.innerHTML = [
        '<div class="modus-modal-card">',
            '<div class="modus-modal-body">',
                '<div class="modus-modal-icon-badge ' + type + '">' + iconChar + '</div>',
                '<h3 class="modus-modal-title">' + (opts.title || 'System Notice') + '</h3>',
                '<div class="modus-modal-message">' + (opts.message || '') + '</div>',
                '<div class="modus-modal-actions">',
                    hasCancel ? '<button type="button" class="modus-modal-btn secondary" id="modusModalCancel">' + (opts.cancelText || 'Cancel') + '</button>' : '',
                    '<button type="button" class="modus-modal-btn primary" id="modusModalConfirm">' + (opts.confirmText || 'Understood') + '</button>',
                '</div>',
            '</div>',
        '</div>'
    ].join('');

    document.body.appendChild(backdrop);
    requestAnimationFrame(function() {
        backdrop.classList.add('active');
    });

    function close(cb) {
        backdrop.classList.remove('active');
        setTimeout(function() {
            backdrop.remove();
            if (typeof cb === 'function') cb();
        }, 260);
    }

    var confirmBtn = backdrop.querySelector('#modusModalConfirm');
    if (confirmBtn) {
        confirmBtn.onclick = function() {
            close(opts.onConfirm);
        };
        confirmBtn.focus();
    }

    var cancelBtn = backdrop.querySelector('#modusModalCancel');
    if (cancelBtn) {
        cancelBtn.onclick = function() {
            close(opts.onCancel);
        };
    }

    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
            close(opts.onCancel || opts.onConfirm);
        }
    });

    function onEsc(e) {
        if (e.key === 'Escape') {
            document.removeEventListener('keydown', onEsc);
            close(opts.onCancel || opts.onConfirm);
        }
    }
    document.addEventListener('keydown', onEsc);
};

ModusUI.alert = function(message, title, type) {
    title = title || 'Notice';
    type = type || 'info';
    return new Promise(function(resolve) {
        ModusUI.modal({
            title: title,
            message: message,
            type: type,
            confirmText: 'Understood',
            onConfirm: function() { resolve(true); }
        });
    });
};

ModusUI.confirm = function(message, title, options) {
    title = title || 'Confirmation';
    options = options || {};
    return new Promise(function(resolve) {
        ModusUI.modal({
            title: title,
            message: message,
            type: options.type || 'warning',
            confirmText: options.confirmText || 'Confirm',
            cancelText: options.cancelText || 'Cancel',
            onConfirm: function() { resolve(true); },
            onCancel: function() { resolve(false); }
        });
    });
};

// ----------------------------------------------------
// Toast Notifications
// ----------------------------------------------------
ModusUI.toast = function(message, type, duration) {
    type = type || 'info';
    duration = duration || 3600;

    var container = document.getElementById('modusToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'modusToastContainer';
        container.className = 'modus-toast-container';
        document.body.appendChild(container);
    }

    var icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };

    var toast = document.createElement('div');
    toast.className = 'modus-toast ' + type;
    toast.innerHTML = [
        '<span class="modus-toast-icon">' + (icons[type] || 'ℹ') + '</span>',
        '<span class="modus-toast-message">' + message + '</span>',
        '<button type="button" class="modus-toast-close">&times;</button>'
    ].join('');

    container.appendChild(toast);

    requestAnimationFrame(function() {
        toast.classList.add('show');
    });

    var timer = setTimeout(removeToast, duration);

    function removeToast() {
        clearTimeout(timer);
        toast.classList.remove('show');
        setTimeout(function() {
            toast.remove();
        }, 320);
    }

    var closeBtn = toast.querySelector('.modus-toast-close');
    if (closeBtn) closeBtn.onclick = removeToast;
};

// ----------------------------------------------------
// OVERRIDE Native window.alert
// Transforms ALL existing and future browser alerts into
// modern, animated aesthetic glassmorphism modals!
// ----------------------------------------------------
window.alert = function(msg) {
    var str = String(msg);
    var type = 'info';
    var lower = str.toLowerCase();
    if (lower.indexOf('error') !== -1 || lower.indexOf('failed') !== -1 || lower.indexOf('invalid') !== -1) {
        type = 'error';
    } else if (lower.indexOf('success') !== -1 || lower.indexOf('saved') !== -1 || lower.indexOf('updated') !== -1) {
        type = 'success';
    } else if (lower.indexOf('warning') !== -1 || lower.indexOf('caution') !== -1 || lower.indexOf('wait') !== -1) {
        type = 'warning';
    }
    ModusUI.alert(str, 'System Alert', type);
};
